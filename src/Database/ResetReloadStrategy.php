<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use JsonException;
use PhpSoftBox\TestUtils\Database\Reset\DumpSchemaHasher;
use PhpSoftBox\TestUtils\Database\Reset\MariaDbResetDriver;
use PhpSoftBox\TestUtils\Database\Reset\PdoResetConnectionFactory;
use PhpSoftBox\TestUtils\Database\Reset\PostgresResetDriver;
use PhpSoftBox\TestUtils\Database\Reset\ResetConnectionFactoryInterface;
use PhpSoftBox\TestUtils\Database\Reset\ResetConnectionInterface;
use PhpSoftBox\TestUtils\Database\Reset\ResetDriverInterface;
use PhpSoftBox\TestUtils\Database\Reset\ResetState;
use PhpSoftBox\TestUtils\Database\Reset\ResetStateRegistry;
use Throwable;

use function array_key_exists;
use function copy;
use function dirname;
use function file_get_contents;
use function getmypid;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function sprintf;
use function touch;
use function unlink;

use const JSON_THROW_ON_ERROR;

/**
 * Сброс тестовой БД без пересоздания схемы.
 *
 * MariaDB/PostgreSQL: схема загружается из дампа один раз на процесс (и только если хеш дампа изменился),
 * перед тестом очищаются таблицы с данными и возвращаются сдвинутые счётчики.
 * SQLite: перед тестом тестовый файл заменяется копией шаблона.
 */
final class ResetReloadStrategy implements ReloadStrategyInterface
{
    /**
     * Меняется при изменении формата служебной таблицы или логики подготовки — схема перезагрузится.
     */
    public const string VERSION = '1';

    public const string SERVICE_TABLE = '_test_utils_reset';

    private const string SQLITE_TEMPLATE_SUFFIX = '.reset-template';

    public function __construct(
        private readonly DatabaseReloaderConfig $config,
        private readonly CommandRunnerInterface $runner = new ProcessCommandRunner(),
        private readonly ResetConnectionFactoryInterface $connectionFactory = new PdoResetConnectionFactory(),
        private readonly DumpSchemaHasher $hasher = new DumpSchemaHasher(),
    ) {
    }

    public function reload(DatabaseReloaderConnection $connection): void
    {
        $connection->assertDifferentDatabases();

        if ($connection->driver() === 'sqlite') {
            $this->reloadSqlite($connection);

            return;
        }

        $state = ResetStateRegistry::get($connection->testDsn);
        if ($state !== null && $this->isSchemaIntact($state)) {
            $this->reset($state, $this->testDatabase($connection));

            return;
        }

        ResetStateRegistry::forget($connection->testDsn);
        ResetStateRegistry::set($connection->testDsn, $this->prepare($connection));
    }

    /**
     * Подготовлена ли схема подключения в текущем процессе.
     */
    public static function isPrepared(DatabaseReloaderConnection $connection): bool
    {
        return ResetStateRegistry::has($connection->testDsn);
    }

    /**
     * Подготовка схемы: загрузка дампа, если хеш изменился; иначе — сброс данных.
     */
    private function prepare(DatabaseReloaderConnection $connection): ResetState
    {
        $driver   = $this->driver($connection);
        $database = $this->testDatabase($connection);
        $dump     = new DumpReloadStrategy($this->config, $this->runner);

        $dumpFile = $dump->prepareDumpFile($connection);

        try {
            $hash = $this->dumpHash($connection, $dumpFile);

            $stored = $this->readStoredSchema($connection, $driver);
            if ($stored !== null && $stored['hash'] === $hash) {
                $driver->configureSession($stored['connection']);

                $state = new ResetState(
                    connection: $stored['connection'],
                    driver: $driver,
                    hash: $hash,
                    tables: $driver->tables($stored['connection'], $database),
                    counters: $stored['counters'],
                );

                // База могла остаться грязной после прошлого процесса.
                $this->reset($state, $database);

                return $state;
            }

            // Соединение со старой схемой закроется при пересоздании базы.
            $stored = null;
            $dump->loadDumpFile($connection, $dumpFile);
        } finally {
            $dump->releaseDumpFile($dumpFile);
        }

        $db = $this->connectionFactory->connect($connection);
        $driver->configureSession($db);

        $state = new ResetState(
            connection: $db,
            driver: $driver,
            hash: $hash,
            tables: $driver->tables($db, $database),
            counters: $driver->counters($db, $database),
        );

        $driver->createServiceTable($db);
        $db->execute("
            INSERT INTO {$driver->serviceTable()} (hash, counters) VALUES (?, ?)
        ", [$hash, json_encode($state->counters, JSON_THROW_ON_ERROR)]);

        return $state;
    }

    /**
     * Сброс перед тестом: завершить чужие сессии, очистить таблицы с данными, вернуть сдвинутые счётчики.
     */
    private function reset(ResetState $state, string $database): void
    {
        $db     = $state->connection;
        $driver = $state->driver;

        $driver->terminateForeignSessions($db, $database);

        $tables  = $driver->tablesWithRows($db, $state->tables);
        $current = $driver->counters($db, $database);

        $counters = [];
        foreach ($state->counters as $counter => $value) {
            if (array_key_exists($counter, $current) && $current[$counter] !== $value) {
                $counters[$counter] = $value;
            }
        }

        if ($tables === [] && $counters === []) {
            return;
        }

        $driver->clean($db, $tables, $counters);
    }

    /**
     * Схема на месте и подготовлена тем же дампом: служебная таблица читается и хеш совпадает.
     *
     * Ошибка означает, что базу удалили (класс в dump, вручную) или соединение разорвано.
     */
    private function isSchemaIntact(ResetState $state): bool
    {
        try {
            $rows = $state->connection->fetchAll("
                SELECT hash FROM {$state->driver->serviceTable()}
            ");
        } catch (Throwable) {
            return false;
        }

        return ($rows[0]['hash'] ?? null) === $state->hash;
    }

    /**
     * @return array{connection: ResetConnectionInterface, hash: string, counters: array<string, int|null>}|null
     */
    private function readStoredSchema(DatabaseReloaderConnection $connection, ResetDriverInterface $driver): ?array
    {
        try {
            $db   = $this->connectionFactory->connect($connection);
            $rows = $db->fetchAll("
                SELECT hash, counters FROM {$driver->serviceTable()}
            ");
        } catch (Throwable) {
            // Базы или служебной таблицы нет.
            return null;
        }

        $hash     = $rows[0]['hash'] ?? null;
        $counters = $rows[0]['counters'] ?? null;
        if (!is_string($hash) || !is_string($counters)) {
            return null;
        }

        try {
            $decoded = json_decode($counters, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $baseline = [];
        foreach ($decoded as $counter => $value) {
            if (!is_string($counter) || ($value !== null && !is_int($value))) {
                return null;
            }

            $baseline[$counter] = $value;
        }

        return ['connection' => $db, 'hash' => $hash, 'counters' => $baseline];
    }

    private function dumpHash(DatabaseReloaderConnection $connection, string $dumpFile): string
    {
        $contents = file_get_contents($dumpFile);
        if ($contents === false) {
            throw new DatabaseReloaderException(sprintf('Failed to read dump file "%s".', $dumpFile));
        }

        if ($this->hasher->containsData($contents)) {
            throw new DatabaseReloaderException(sprintf(
                'Dump file "%s" contains data (INSERT/COPY): reset mode supports schema-only dumps. Use dump mode for connection "%s".',
                $dumpFile,
                $connection->name,
            ));
        }

        return $this->hasher->hash($contents, $connection->driver(), self::VERSION);
    }

    private function driver(DatabaseReloaderConnection $connection): ResetDriverInterface
    {
        return match ($connection->driver()) {
            'mariadb', 'mysql'  => new MariaDbResetDriver(),
            'postgres', 'pgsql' => new PostgresResetDriver(),
            default             => throw new DatabaseReloaderException(sprintf(
                'Reset mode is not supported for driver "%s".',
                $connection->driver(),
            )),
        };
    }

    private function testDatabase(DatabaseReloaderConnection $connection): string
    {
        $database = $connection->test->database ?? '';
        if ($database === '') {
            throw new DatabaseReloaderException('Test database name is empty.');
        }

        return $database;
    }

    private function reloadSqlite(DatabaseReloaderConnection $connection): void
    {
        $testPath = $connection->test->path ?? '';
        if ($testPath === '') {
            throw new DatabaseReloaderException('SQLite test database path is empty.');
        }

        if ($testPath === ':memory:') {
            throw new DatabaseReloaderException('Reset mode is not supported for SQLite :memory: database.');
        }

        $template = ResetStateRegistry::sqliteTemplate($connection->testDsn);
        if ($template === null || !is_file($template)) {
            $template = $this->prepareSqliteTemplate($connection, $testPath);
            ResetStateRegistry::setSqliteTemplate($connection->testDsn, $template);
        }

        // copy во временный файл + rename: процесс со старым дескриптором продолжит работать со старым файлом.
        $tmpPath = sprintf('%s.reset-%d.tmp', $testPath, (int) getmypid());
        if (!@copy($template, $tmpPath)) {
            throw new DatabaseReloaderException('Failed to copy SQLite reset template.');
        }

        if (!@rename($tmpPath, $testPath)) {
            @unlink($tmpPath);

            throw new DatabaseReloaderException('Failed to replace SQLite test database file.');
        }

        foreach (['-wal', '-shm'] as $suffix) {
            if (is_file($testPath . $suffix)) {
                unlink($testPath . $suffix);
            }
        }
    }

    /**
     * Шаблон по правилам dump: копия основной БД, иначе копия существующего тестового файла, иначе пустой файл.
     */
    private function prepareSqliteTemplate(DatabaseReloaderConnection $connection, string $testPath): string
    {
        $template = $testPath . self::SQLITE_TEMPLATE_SUFFIX;
        $mainPath = $connection->main->path ?? '';

        $dir = dirname($template);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new DatabaseReloaderException(sprintf('Directory "%s" was not created.', $dir));
        }

        $source = match (true) {
            $mainPath !== '' && is_file($mainPath) => $mainPath,
            is_file($testPath)                     => $testPath,
            default                                => null,
        };

        if ($source === null) {
            if (is_file($template)) {
                unlink($template);
            }

            if (!@touch($template)) {
                throw new DatabaseReloaderException('Failed to create SQLite reset template.');
            }

            return $template;
        }

        if (!@copy($source, $template)) {
            throw new DatabaseReloaderException('Failed to create SQLite reset template.');
        }

        return $template;
    }
}
