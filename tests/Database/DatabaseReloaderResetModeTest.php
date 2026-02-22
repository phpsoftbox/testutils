<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\Command;
use PhpSoftBox\TestUtils\Database\DatabaseReloader;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseTransactionManager;
use PhpSoftBox\TestUtils\Database\Reset\MariaDbResetDriver;
use PhpSoftBox\TestUtils\Database\Reset\ResetState;
use PhpSoftBox\TestUtils\Database\Reset\ResetStateRegistry;
use PhpSoftBox\TestUtils\Tests\Database\Reset\FakeResetConnection;
use PhpSoftBox\TestUtils\Tests\Database\Reset\FakeResetConnectionFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function file_exists;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(DatabaseReloader::class)]
#[CoversMethod(DatabaseReloader::class, 'reload')]
#[CoversMethod(DatabaseReloader::class, 'withMode')]
final class DatabaseReloaderResetModeTest extends TestCase
{
    protected function setUp(): void
    {
        ResetStateRegistry::clear();
    }

    protected function tearDown(): void
    {
        ResetStateRegistry::clear();
    }

    /**
     * Проверим, что режим reset принимается как допустимый.
     *
     * @see DatabaseReloader::withMode()
     */
    #[Test]
    public function withModeAcceptsReset(): void
    {
        $reloader = new DatabaseReloader(new DatabaseReloaderConfig([], ''), new FakeCommandRunner(false));

        self::assertSame('reset', $reloader->withMode('reset')->mode());
    }

    /**
     * Проверим, что в режиме reset временный дамп генерируется один раз — без отдельного bootstrap из dump.
     *
     * @see DatabaseReloader::reload()
     */
    #[Test]
    public function resetModeGeneratesTemporaryDumpOnce(): void
    {
        $dumpFile = sys_get_temp_dir() . '/psb-test-utils/default-mariadb.sql';
        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }

        $connection = $this->connection();
        $config     = new DatabaseReloaderConfig([$connection], '', mode: 'reset');
        $runner     = new FakeCommandRunner();
        // Служебной таблицы нет → загрузка схемы → соединение после загрузки.
        $factory = new FakeResetConnectionFactory([new RuntimeException('Unknown database'), new FakeResetConnection()]);

        new DatabaseReloader($config, $runner, resetConnectionFactory: $factory)->reload($connection);

        $dumpCommands = array_filter(
            $runner->commands,
            static fn (Command $command): bool => $command->stdoutFile === $dumpFile,
        );

        self::assertCount(1, $dumpCommands);
        self::assertFalse(file_exists($dumpFile));
    }

    /**
     * Проверим, что transaction не запускает bootstrap из dump, если схема уже подготовлена через reset.
     *
     * @see DatabaseReloader::reload()
     */
    #[Test]
    public function transactionModeSkipsDumpBootstrapWhenSchemaPrepared(): void
    {
        $dumpFile = sys_get_temp_dir() . '/psb-test-utils/default-mariadb.sql';
        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }

        $connection = $this->connection();
        ResetStateRegistry::set($connection->testDsn, new ResetState(
            connection: new FakeResetConnection(),
            driver: new MariaDbResetDriver(),
            hash: 'hash',
            tables: [],
            counters: [],
        ));

        $config = new DatabaseReloaderConfig([$connection], '', mode: 'transaction');
        $runner = new FakeCommandRunner();

        new DatabaseReloader($config, $runner, new DatabaseTransactionManager($runner))->reload($connection);

        // Только rollback и begin транзакционной стратегии.
        self::assertCount(2, $runner->commands);
    }

    private function connection(): DatabaseReloaderConnection
    {
        return new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );
    }
}
