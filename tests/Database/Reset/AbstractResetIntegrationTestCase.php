<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PDO;
use PDOException;
use PhpSoftBox\TestUtils\Database\DatabaseReloader;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\Reset\ResetStateRegistry;
use PhpSoftBox\TestUtils\Database\ResetReloadStrategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function getenv;
use function is_string;
use function mkdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Проверки reset на настоящей БД из сервисов docker-compose (`make select-testutils` поднимает mariadb и postgres).
 *
 * DSN основной БД можно переопределить переменной окружения. Основная БД не нужна: дамп пишется во временный
 * каталог, а тестовая БД `<основная>_autotests` создаётся самой стратегией.
 */
abstract class AbstractResetIntegrationTestCase extends TestCase
{
    protected DatabaseReloaderConnection $connection;

    protected string $dumpFile;

    protected RecordingCommandRunner $runner;

    private string $dumpDirectory;

    abstract protected function dsnEnvironmentVariable(): string;

    /**
     * DSN сервиса docker-compose, если переменная окружения не задана.
     */
    abstract protected function defaultDsn(): string;

    abstract protected function schemaDump(): string;

    /**
     * Заполняет связанные таблицы и сдвигает счётчики.
     */
    abstract protected function insertRelatedRows(PDO $pdo): void;

    /**
     * Вставляет и удаляет строку: таблица пуста, счётчик сдвинут.
     */
    abstract protected function insertAndDeleteRow(PDO $pdo): void;

    /**
     * @return array<string, int|null>
     */
    abstract protected function counters(PDO $pdo): array;

    abstract protected function rowCount(PDO $pdo): int;

    protected function setUp(): void
    {
        $dsn = getenv($this->dsnEnvironmentVariable());
        if (!is_string($dsn) || $dsn === '') {
            $dsn = $this->defaultDsn();
        }

        ResetStateRegistry::clear();

        $this->connection = new DatabaseReloaderConnection('default', $dsn, $dsn . '_autotests');

        $this->assertServerAvailable();

        $this->dumpDirectory = sys_get_temp_dir() . '/psb-test-utils-reset-it-' . uniqid('', true);
        mkdir($this->dumpDirectory, 0775, true);
        $this->dumpFile = sprintf('%s/default-%s.sql', $this->dumpDirectory, $this->connection->driver());
        file_put_contents($this->dumpFile, $this->schemaDump());

        $this->runner = new RecordingCommandRunner();
    }

    protected function tearDown(): void
    {
        ResetStateRegistry::clear();
    }

    /**
     * Проверим, что после теста с данными в связанных таблицах следующий тест видит пустые таблицы и счётчики из дампа.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function cleansRelatedTablesAndRestoresCountersFromDump(): void
    {
        $this->reset();
        $baseline = $this->counters($this->pdo());

        $this->insertRelatedRows($this->pdo());
        $this->reset();

        $pdo = $this->pdo();
        self::assertSame(0, $this->rowCount($pdo));
        self::assertSame($baseline, $this->counters($pdo));
    }

    /**
     * Проверим, что счётчик пустой таблицы, в которую вставили и из которой удалили строку, возвращается к эталону.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function restoresCounterAfterInsertAndDelete(): void
    {
        $this->reset();
        $baseline = $this->counters($this->pdo());

        $this->insertAndDeleteRow($this->pdo());
        $this->reset();

        self::assertSame($baseline, $this->counters($this->pdo()));
    }

    /**
     * Проверим, что новый процесс с тем же дампом схему не перезагружает, но стартует с пустыми таблицами.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function doesNotReloadUnchangedDumpInNewProcess(): void
    {
        $this->reset();
        $this->insertRelatedRows($this->pdo());

        // Новый процесс: реестр пуст, а дамп сгенерирован заново — отличаются только комментарии.
        ResetStateRegistry::clear();
        file_put_contents($this->dumpFile, "-- regenerated\n" . $this->schemaDump());
        $this->runner = new RecordingCommandRunner();

        $this->reset();

        self::assertSame(0, $this->runner->schemaLoads($this->dumpFile));
        self::assertSame(0, $this->rowCount($this->pdo()));
    }

    /**
     * Проверим, что изменение схемы в дампе перезагружает схему в новом процессе.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function reloadsSchemaWhenDumpChanges(): void
    {
        $this->reset();

        ResetStateRegistry::clear();
        file_put_contents($this->dumpFile, $this->schemaDump() . "CREATE TABLE extra_table (id INT PRIMARY KEY);\n");
        $this->runner = new RecordingCommandRunner();

        $this->reset();

        self::assertSame(1, $this->runner->schemaLoads($this->dumpFile));
    }

    /**
     * Проверим, что класс в dump между классами в reset не ломает эталон: соединение реестра переподключается.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function dumpModeBetweenResetKeepsBaseline(): void
    {
        $this->reset();
        $baseline = $this->counters($this->pdo());

        $this->reloader('dump')->reloadAll();
        $this->insertRelatedRows($this->pdo());
        $this->reset();

        $pdo = $this->pdo();
        self::assertSame(0, $this->rowCount($pdo));
        self::assertSame($baseline, $this->counters($pdo));
    }

    /**
     * Проверим, что незакрытая транзакция другого соединения не блокирует сброс: сессия завершается.
     *
     * @see ResetReloadStrategy::reload()
     */
    #[Test]
    public function openForeignTransactionDoesNotBlockReset(): void
    {
        $this->reset();

        $foreign = $this->pdo();
        $foreign->beginTransaction();
        $this->insertRelatedRows($foreign);

        $this->reset();

        self::assertSame(0, $this->rowCount($this->pdo()));
    }

    protected function pdo(): PDO
    {
        return $this->connect((string) $this->connection->test->database);
    }

    /**
     * Сервер недоступен — тест падает с подсказкой, а не пропускается: без этих проверок reset не подтверждён.
     */
    private function assertServerAvailable(): void
    {
        // К тестовой БД подключиться нельзя до первой загрузки схемы, поэтому проверяем служебную БД сервера.
        $database = $this->connection->driver() === 'postgres' ? 'postgres' : 'information_schema';

        try {
            $this->connect($database);
        } catch (PDOException $exception) {
            self::fail(sprintf(
                'Database server for %s is not available (%s). Start services with `make select-testutils` or set %s.',
                static::class,
                $exception->getMessage(),
                $this->dsnEnvironmentVariable(),
            ));
        }
    }

    private function connect(string $database): PDO
    {
        $test = $this->connection->test;
        $dsn  = $this->connection->driver() === 'postgres'
            ? sprintf('pgsql:host=%s;port=%d;dbname=%s', $test->host, $test->port ?? 5432, $database)
            : sprintf('mysql:host=%s;port=%d;dbname=%s', $test->host, $test->port ?? 3306, $database);

        return new PDO($dsn, $test->user, $test->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
    }

    private function reset(): void
    {
        $this->reloader('reset')->reloadAll();
    }

    private function reloader(string $mode): DatabaseReloader
    {
        $config = new DatabaseReloaderConfig([$this->connection], $this->dumpDirectory, keepDumpFiles: true, mode: $mode);

        return new DatabaseReloader($config, $this->runner);
    }
}
