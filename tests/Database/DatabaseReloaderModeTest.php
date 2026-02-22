<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseReloader;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderException;
use PhpSoftBox\TestUtils\Database\DatabaseTransactionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function count;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function unlink;

#[CoversClass(DatabaseReloader::class)]
#[CoversMethod(DatabaseReloader::class, 'reload')]
#[CoversMethod(DatabaseReloader::class, 'withMode')]
#[CoversMethod(DatabaseReloader::class, 'mode')]
final class DatabaseReloaderModeTest extends TestCase
{
    /**
     * Проверяет, что в режиме transaction reloader использует транзакционную стратегию сброса состояния.
     */
    #[Test]
    public function usesTransactionStrategy(): void
    {
        $runner = new FakeCommandRunner(false);

        $manager = new DatabaseTransactionManager($runner);

        $tmpDir = sys_get_temp_dir() . '/psb-test-utils-mode-tests';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $dumpFile = $tmpDir . '/default-postgres.sql';
        file_put_contents($dumpFile, "-- existing --\n");

        $connection = new DatabaseReloaderConnection(
            'default',
            'postgres://user:pass@localhost:5432/app',
            'postgres://user:pass@localhost:5432/app_autotests',
        );

        $config = new DatabaseReloaderConfig([$connection], $tmpDir, keepDumpFiles: true, mode: 'transaction');

        $reloader = new DatabaseReloader($config, $runner, $manager);

        $reloader->reload($connection);

        $this->assertCount(2, $runner->commands);

        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }
    }

    /**
     * Проверяет, что в режиме transaction при отсутствии дампа сначала выполняется инициализация из dump-стратегии.
     */
    #[Test]
    public function transactionModeBootstrapsFromDumpWhenMissing(): void
    {
        $runner = new FakeCommandRunner();

        $manager = new DatabaseTransactionManager($runner);

        $tmpDir = sys_get_temp_dir() . '/psb-test-utils-mode-tests';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $dumpFile = $tmpDir . '/default-mariadb.sql';
        if (file_exists($dumpFile)) {
            unlink($dumpFile);
        }

        $connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );

        $config = new DatabaseReloaderConfig([$connection], $tmpDir, keepDumpFiles: true, mode: 'transaction');

        $reloader = new DatabaseReloader($config, $runner, $manager);

        $reloader->reload($connection);

        $dumpCommands = array_filter(
            $runner->commands,
            static fn ($command): bool => $command->stdoutFile === $dumpFile,
        );

        $this->assertTrue(file_exists($dumpFile));
        $this->assertCount(1, $dumpCommands);
        $this->assertGreaterThanOrEqual(5, count($runner->commands));
    }

    /**
     * Проверяет, что в режиме transaction без доступного адаптера транзакций выбрасывается ошибка.
     */
    #[Test]
    public function failsWithoutTransactionAdapter(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'postgres://user:pass@localhost:5432/app',
            'postgres://user:pass@localhost:5432/app_autotests',
        );

        $config = new DatabaseReloaderConfig([$connection], '', keepDumpFiles: false, mode: 'transaction');

        $reloader = new DatabaseReloader($config, new FakeCommandRunner(false));

        $this->expectException(DatabaseReloaderException::class);
        $reloader->reload($connection);
    }

    /**
     * Проверяет, что reloader отклоняет неизвестный режим перезагрузки базы данных.
     */
    #[Test]
    public function rejectsUnsupportedMode(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'postgres://user:pass@localhost:5432/app',
            'postgres://user:pass@localhost:5432/app_autotests',
        );

        $config = new DatabaseReloaderConfig([$connection], '', keepDumpFiles: false, mode: 'dump');

        $reloader = new DatabaseReloader($config, new FakeCommandRunner(false));

        $this->expectException(DatabaseReloaderException::class);
        $reloader->withMode('invalid-mode');
    }
}
