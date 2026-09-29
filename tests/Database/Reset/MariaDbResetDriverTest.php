<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PhpSoftBox\TestUtils\Database\Reset\MariaDbResetDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MariaDbResetDriver::class)]
#[CoversMethod(MariaDbResetDriver::class, 'configureSession')]
final class MariaDbResetDriverTest extends TestCase
{
    /**
     * Проверим, что на MySQL сессия отключает кеш статистики information_schema.
     *
     * @see MariaDbResetDriver::configureSession()
     */
    #[Test]
    public function disablesStatsCacheOnMySql(): void
    {
        $connection = new FakeResetConnection()->respond('VERSION()', [['version' => '8.4.11']]);

        new MariaDbResetDriver()->configureSession($connection);

        self::assertContains('SET SESSION information_schema_stats_expiry = 0', $connection->executedSql());
    }

    /**
     * Проверим, что на MariaDB переменная не выставляется: такой переменной там нет.
     *
     * @see MariaDbResetDriver::configureSession()
     */
    #[Test]
    public function keepsSessionOnMariaDb(): void
    {
        $connection = new FakeResetConnection()->respond('VERSION()', [['version' => '11.8.6-MariaDB-ubu2404']]);

        new MariaDbResetDriver()->configureSession($connection);

        self::assertSame(['SET SESSION lock_wait_timeout = 5'], $connection->executedSql());
    }
}
