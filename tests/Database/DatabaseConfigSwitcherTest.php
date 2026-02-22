<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseConfigSwitcher;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseConfigSwitcher::class)]
#[CoversMethod(DatabaseConfigSwitcher::class, 'applyTestConfig')]
final class DatabaseConfigSwitcherTest extends TestCase
{
    /**
     * Проверяет, что тестовый DSN подставляется для read/write подключений.
     *
     * @see DatabaseConfigSwitcher::applyTestConfig()
     * @see DatabaseReloaderConfig::fromDatabaseConfig()
     */
    #[Test]
    public function applyTestConfigUpdatesReadAndWriteConnections(): void
    {
        $databaseConfig = [
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'write' => [
                        'dsn' => 'postgres://user:pass@localhost:5432/app',
                    ],
                    'read' => [
                        'dsn' => 'postgres://user:pass@localhost:5432/app',
                    ],
                ],
            ],
        ];

        // Готовим конфиг перезагрузчика и применяем тестовые DSN.
        $reloaderConfig = DatabaseReloaderConfig::fromDatabaseConfig($databaseConfig, ['default']);
        $switcher       = new DatabaseConfigSwitcher($reloaderConfig);

        $testConfig = $switcher->applyTestConfig($databaseConfig);

        $this->assertSame(
            'postgres://user:pass@localhost:5432/app_autotests',
            $testConfig['connections']['main']['write']['dsn'],
        );
        $this->assertSame(
            'postgres://user:pass@localhost:5432/app_autotests',
            $testConfig['connections']['main']['read']['dsn'],
        );
    }
}
