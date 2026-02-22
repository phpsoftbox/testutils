<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderException;
use PhpSoftBox\TestUtils\Database\Reset\DumpSchemaHasher;
use PhpSoftBox\TestUtils\Database\Reset\MariaDbResetDriver;
use PhpSoftBox\TestUtils\Database\Reset\ResetStateRegistry;
use PhpSoftBox\TestUtils\Database\ResetReloadStrategy;
use PhpSoftBox\TestUtils\Tests\Database\FakeCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function array_values;
use function file_put_contents;
use function json_encode;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

use const JSON_THROW_ON_ERROR;

#[CoversClass(ResetReloadStrategy::class)]
#[CoversClass(MariaDbResetDriver::class)]
#[CoversMethod(ResetReloadStrategy::class, 'reload')]
#[CoversMethod(ResetReloadStrategy::class, 'isPrepared')]
final class ResetReloadStrategyTest extends TestCase
{
    private const string DUMP = "-- MariaDB dump\nCREATE TABLE `a` (id int);\n-- Dump completed on 2026-09-22\n";

    private string $dumpDirectory;

    private DatabaseReloaderConnection $connection;

    protected function setUp(): void
    {
        ResetStateRegistry::clear();

        $this->dumpDirectory = sys_get_temp_dir() . '/psb-test-utils-reset-' . uniqid('', true);
        mkdir($this->dumpDirectory, 0775, true);
        file_put_contents($this->dumpDirectory . '/default-mariadb.sql', self::DUMP);

        $this->connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );
    }

    protected function tearDown(): void
    {
        ResetStateRegistry::clear();
    }

    /**
     * Проверим, что без служебной таблицы схема загружается из дампа, а хеш и эталон счётчиков сохраняются.
     *
     * @see ResetReloadStrategy::reload()
     * @see ResetReloadStrategy::isPrepared()
     */
    #[Test]
    public function loadsSchemaAndStoresBaselineWhenServiceTableMissing(): void
    {
        // Первое соединение не находит служебную таблицу, второе открывается после загрузки дампа.
        $db      = $this->mariaDbConnection(counters: ['a' => 1251, 'b' => 1]);
        $factory = new FakeResetConnectionFactory([new RuntimeException('Table doesn\'t exist'), $db]);
        $runner  = new FakeCommandRunner(false);

        $this->strategy($runner, $factory)->reload($this->connection);

        self::assertCount(1, $this->loadCommands($runner));
        self::assertTrue(ResetReloadStrategy::isPrepared($this->connection));

        // После загрузки: настройка сессии, служебная таблица, запись хеша и эталона.
        self::assertSame([
            'SET SESSION lock_wait_timeout = 5',
            'CREATE TABLE `_test_utils_reset` ( hash VARCHAR(64) NOT NULL, counters TEXT NOT NULL )',
            'INSERT INTO `_test_utils_reset` (hash, counters) VALUES (?, ?)',
        ], $db->executedSql());
        self::assertSame(
            [$this->hash(), json_encode(['`a`' => 1251, '`b`' => 1], JSON_THROW_ON_ERROR)],
            $db->executed[2]['params'] ?? null,
        );
    }

    /**
     * Проверим, что при совпадении сохранённого хеша схема не перезагружается.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function skipsSchemaLoadWhenStoredHashMatches(): void
    {
        $db     = $this->storedConnection(['a' => 1251]);
        $runner = new FakeCommandRunner(false);

        $this->strategy($runner, new FakeResetConnectionFactory([$db]))->reload($this->connection);

        self::assertSame([], $this->loadCommands($runner));
        self::assertSame(['SET SESSION lock_wait_timeout = 5'], $db->executedSql());
    }

    /**
     * Проверим, что очищаются только таблицы со строками, а несдвинутые счётчики не трогаются.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function cleansOnlyTablesWithRows(): void
    {
        // Строки есть только в таблице `b` (индекс 1).
        $db = $this->storedConnection(['a' => 1251], dirtyIndexes: [1]);

        $this->strategy(new FakeCommandRunner(false), new FakeResetConnectionFactory([$db]))->reload($this->connection);

        self::assertSame([
            'SET SESSION lock_wait_timeout = 5',
            'SET FOREIGN_KEY_CHECKS = 0',
            'START TRANSACTION',
            'DELETE FROM `b`',
            'COMMIT',
            'SET FOREIGN_KEY_CHECKS = 1',
        ], $db->executedSql());
    }

    /**
     * Проверим, что пустая таблица со сдвинутым счётчиком не очищается: счётчик возвращается к эталону.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function restoresShiftedCounterWithoutCleaningEmptyTable(): void
    {
        // Тест вставил и удалил строку: таблица пуста, но счётчик 1251 → 1260.
        $db = $this->storedConnection(['a' => 1251], currentCounters: ['a' => 1260]);

        $this->strategy(new FakeCommandRunner(false), new FakeResetConnectionFactory([$db]))->reload($this->connection);

        self::assertSame([
            'SET SESSION lock_wait_timeout = 5',
            'SET FOREIGN_KEY_CHECKS = 0',
            'ALTER TABLE `a` AUTO_INCREMENT = 1251',
            'SET FOREIGN_KEY_CHECKS = 1',
        ], $db->executedSql());
    }

    /**
     * Проверим, что перед очисткой завершаются чужие сессии тестовой БД.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function terminatesForeignSessionsBeforeCleaning(): void
    {
        $db = $this->storedConnection(['a' => 1251]);
        $db->respond('information_schema.PROCESSLIST', [['ID' => 101], ['ID' => 102]]);

        $this->strategy(new FakeCommandRunner(false), new FakeResetConnectionFactory([$db]))->reload($this->connection);

        self::assertSame(['SET SESSION lock_wait_timeout = 5', 'KILL 101', 'KILL 102'], $db->executedSql());
    }

    /**
     * Проверим, что при подготовленной схеме повторный сброс не открывает новое соединение и не читает дамп.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function reusesPreparedStateOnNextReload(): void
    {
        $db      = $this->storedConnection(['a' => 1251]);
        $factory = new FakeResetConnectionFactory([$db]);
        $runner  = new FakeCommandRunner(false);

        $this->strategy($runner, $factory)->reload($this->connection);
        // Новый экземпляр стратегии — как после resetApp(): состояние берётся из реестра.
        $this->strategy($runner, $factory)->reload($this->connection);

        self::assertSame(1, $factory->connects);
        self::assertSame([], $this->loadCommands($runner));
    }

    /**
     * Проверим, что если служебная таблица пропала (базу пересоздал класс в dump), схема готовится заново.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function preparesAgainWhenServiceTableDisappears(): void
    {
        $db      = $this->storedConnection(['a' => 1251]);
        $fresh   = $this->mariaDbConnection(counters: ['a' => 1251]);
        $factory = new FakeResetConnectionFactory([$db]);
        $runner  = new FakeCommandRunner(false);

        $this->strategy($runner, $factory)->reload($this->connection);

        // База удалена: соединение реестра разорвано, служебной таблицы нет.
        $db->respond('SELECT hash FROM', new RuntimeException('Server has gone away'));
        $factory->push(new RuntimeException('Unknown database'))->push($fresh);

        $this->strategy($runner, $factory)->reload($this->connection);

        self::assertCount(1, $this->loadCommands($runner));
    }

    /**
     * Проверим, что при другом сохранённом хеше схема перезагружается из дампа.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function reloadsSchemaWhenStoredHashDiffers(): void
    {
        $stale = new FakeResetConnection()->respond('SELECT hash, counters FROM', [
            ['hash' => 'stale', 'counters' => '{}'],
        ]);
        $runner = new FakeCommandRunner(false);

        $this->strategy($runner, new FakeResetConnectionFactory([$stale, $this->mariaDbConnection()]))
            ->reload($this->connection);

        self::assertCount(1, $this->loadCommands($runner));
    }

    /**
     * Проверим, что дамп с данными отклоняется с подсказкой использовать режим dump.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function rejectsDumpWithData(): void
    {
        file_put_contents($this->dumpDirectory . '/default-mariadb.sql', self::DUMP . "INSERT INTO `a` VALUES (1);\n");

        $this->expectException(DatabaseReloaderException::class);
        $this->expectExceptionMessage('Use dump mode');

        $this->strategy(new FakeCommandRunner(false), new FakeResetConnectionFactory())->reload($this->connection);
    }

    private function strategy(FakeCommandRunner $runner, FakeResetConnectionFactory $factory): ResetReloadStrategy
    {
        $config = new DatabaseReloaderConfig([$this->connection], $this->dumpDirectory, keepDumpFiles: true);

        return new ResetReloadStrategy($config, $runner, $factory);
    }

    /**
     * Соединение с подготовленной схемой: служебная таблица хранит актуальный хеш и эталон.
     *
     * @param array<string, int> $baseline
     * @param list<int> $dirtyIndexes
     * @param array<string, int>|null $currentCounters
     */
    private function storedConnection(array $baseline, array $dirtyIndexes = [], ?array $currentCounters = null): FakeResetConnection
    {
        $quoted = [];
        foreach ($baseline as $table => $value) {
            $quoted['`' . $table . '`'] = $value;
        }

        return $this->mariaDbConnection($currentCounters ?? $baseline, $dirtyIndexes)
            ->respond('SELECT hash, counters FROM', [
                ['hash' => $this->hash(), 'counters' => json_encode($quoted, JSON_THROW_ON_ERROR)],
            ])
            ->respond('SELECT hash FROM', [['hash' => $this->hash()]]);
    }

    /**
     * @param array<string, int> $counters
     * @param list<int> $dirtyIndexes
     */
    private function mariaDbConnection(array $counters = [], array $dirtyIndexes = []): FakeResetConnection
    {
        $counterRows = [];
        foreach ($counters as $table => $value) {
            $counterRows[] = ['TABLE_NAME' => $table, 'AUTO_INCREMENT' => $value];
        }

        $dirtyRows = [];
        foreach ($dirtyIndexes as $index) {
            $dirtyRows[] = ['table_index' => $index];
        }

        return new FakeResetConnection()
            ->respond('ORDER BY TABLE_NAME', [['TABLE_NAME' => 'a'], ['TABLE_NAME' => 'b']])
            ->respond('AUTO_INCREMENT IS NOT NULL', $counterRows)
            ->respond('AS table_index', $dirtyRows);
    }

    private function hash(): string
    {
        return new DumpSchemaHasher()->hash(self::DUMP, 'mariadb', ResetReloadStrategy::VERSION);
    }

    /**
     * @return list<object>
     */
    private function loadCommands(FakeCommandRunner $runner): array
    {
        $dumpFile = $this->dumpDirectory . '/default-mariadb.sql';

        return array_values(array_filter(
            $runner->commands,
            static fn (object $command): bool => $command->stdinFile === $dumpFile,
        ));
    }
}
