<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseReloaderConfig::class)]
#[CoversMethod(DatabaseReloaderConfig::class, 'fromDatabaseConfig')]
#[CoversMethod(DatabaseReloaderConfig::class, 'withConnections')]
#[CoversMethod(DatabaseReloaderConfig::class, 'withMode')]
final class DatabaseReloaderConfigTest extends TestCase
{
    /**
     * Проверяет, что тестовый DSN формируется с суффиксом для default-подключения.
     *
     * @see DatabaseReloaderConfig::fromDatabaseConfig()
     */
    #[Test]
    public function fromDatabaseConfigBuildsTestDsnWithSuffix(): void
    {
        $config = [
            // Базовая конфигурация соединения.
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'mariadb://user:pass@localhost:3306/app',
                ],
            ],
        ];

        $reloaderConfig = DatabaseReloaderConfig::fromDatabaseConfig($config, ['default'], '_autotests');

        // Убедимся, что исходный и тестовый DSN корректны.
        $this->assertCount(1, $reloaderConfig->connections);
        $connection = $reloaderConfig->connections[0];

        $this->assertSame('mariadb://user:pass@localhost:3306/app', $connection->mainDsn);
        $this->assertSame('mariadb://user:pass@localhost:3306/app_autotests', $connection->testDsn);
        $this->assertSame('dump', $reloaderConfig->mode);
    }

    /**
     * Проверяет смену режима и фильтрацию подключений.
     *
     * @see DatabaseReloaderConfig::withMode()
     * @see DatabaseReloaderConfig::withConnections()
     */
    #[Test]
    public function withModeAndConnectionsFiltersConfig(): void
    {
        $config = [
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'mariadb://user:pass@localhost:3306/app',
                ],
                'search' => [
                    'dsn' => 'postgres://user:pass@localhost:5432/app',
                ],
            ],
        ];

        $reloaderConfig = DatabaseReloaderConfig::fromDatabaseConfig($config, ['default', 'search']);

        $filtered = $reloaderConfig
            ->withMode('transaction')
            ->withConnections(['search']);

        $this->assertSame('transaction', $filtered->mode);
        $this->assertCount(1, $filtered->connections);
        $this->assertSame('search', $filtered->connections[0]->name);
    }
}
