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

#[CoversClass(DatabaseReloader::class)]
#[CoversMethod(DatabaseReloader::class, 'reload')]
final class DatabaseReloaderModeTest extends TestCase
{
    /**
     * Проверяет, что режим transaction использует транзакции.
     */
    #[Test]
    public function usesTransactionStrategy(): void
    {
        $runner = new FakeCommandRunner(false);

        $manager = new DatabaseTransactionManager($runner);

        $connection = new DatabaseReloaderConnection(
            'default',
            'postgres://user:pass@localhost:5432/app',
            'postgres://user:pass@localhost:5432/app_autotests',
        );

        $config = new DatabaseReloaderConfig([$connection], '', keepDumpFiles: false, mode: 'transaction');

        $reloader = new DatabaseReloader($config, $runner, $manager);

        $reloader->reload($connection);

        $this->assertCount(2, $runner->commands);
    }

    /**
     * Проверяет, что без адаптера транзакций выбрасывается исключение.
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
}
