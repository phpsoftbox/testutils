<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\Database\Exception\ConfigurationException;
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
     * Проверяет, что конфигурация reloader формирует тестовый DSN с заданным суффиксом базы.
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
     * Проверяет, что метод withModeAndConnections возвращает новую конфигурацию с выбранным режимом и набором подключений.
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



    /**
     * Проверяет, что при уже суффиксированном тестовом DSN суффикс не добавляется повторно.
     */
    #[Test]
    public function fromDatabaseConfigDoesNotDoubleSuffixWhenTestingDsnAlreadyHasSuffix(): void
    {
        $config = [
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'mariadb://user:pass@localhost:3306/app_autotests',
                ],
            ],
        ];

        $reloaderConfig = DatabaseReloaderConfig::fromDatabaseConfig($config, ['default'], '_autotests');

        $this->assertCount(1, $reloaderConfig->connections);
        $connection = $reloaderConfig->connections[0];

        $this->assertSame('mariadb://user:pass@localhost:3306/app', $connection->mainDsn);
        $this->assertSame('mariadb://user:pass@localhost:3306/app_autotests', $connection->testDsn);
    }



    /**
     * Проверяет, что при пустом суффиксе и отсутствии явного имени тестовой базы выбрасывается исключение конфигурации.
     */
    #[Test]
    public function fromDatabaseConfigThrowsWhenSuffixIsEmptyAndTestDatabaseIsNotExplicitlyProvided(): void
    {
        $config = [
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'mariadb://user:pass@localhost:3306/app',
                ],
            ],
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'Test database suffix must not be empty when test database name is not explicitly provided.',
        );

        DatabaseReloaderConfig::fromDatabaseConfig($config, ['default'], '');
    }



    /**
     * Проверяет, что пустой суффикс допустим, если имя тестовой базы указано явно.
     */
    #[Test]
    public function fromDatabaseConfigAllowsEmptySuffixWhenTestDatabaseIsExplicitlyProvided(): void
    {
        $config = [
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'mariadb://user:pass@localhost:3306/app',
                ],
            ],
        ];

        $reloaderConfig = DatabaseReloaderConfig::fromDatabaseConfig(
            databaseConfig: $config,
            connectionNames: ['default'],
            testSuffix: '',
            testDatabaseNames: ['default' => 'app_tests'],
        );

        $this->assertCount(1, $reloaderConfig->connections);
        $connection = $reloaderConfig->connections[0];

        $this->assertSame('mariadb://user:pass@localhost:3306/app', $connection->mainDsn);
        $this->assertSame('mariadb://user:pass@localhost:3306/app_tests', $connection->testDsn);
    }
}
