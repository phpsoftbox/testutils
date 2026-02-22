<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseTransactionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

#[CoversClass(DatabaseTransactionManager::class)]
#[CoversMethod(DatabaseTransactionManager::class, 'supports')]
#[CoversMethod(DatabaseTransactionManager::class, 'begin')]
final class DatabaseTransactionManagerTest extends TestCase
{
    /**
     * Проверяет, что менеджер транзакций поддерживает только файловые SQLite-подключения.
     */
    #[Test]
    public function supportsSqliteFileOnly(): void
    {
        $runner = new FakeCommandRunner(false);

        $manager = new DatabaseTransactionManager($runner);

        $fileConnection = new DatabaseReloaderConnection(
            'default',
            'sqlite:///tmp/main.sqlite',
            'sqlite:///tmp/test.sqlite',
        );

        $memoryConnection = new DatabaseReloaderConnection(
            'default',
            'sqlite:///tmp/main.sqlite',
            'sqlite:///:memory:',
        );

        $this->assertTrue($manager->supports($fileConnection));
        $this->assertFalse($manager->supports($memoryConnection));
    }



    /**
     * Проверяет, что при запуске транзакционного режима для MariaDB применяется подключение с skip-ssl.
     */
    #[Test]
    public function beginUsesSkipSslForMariadbConnection(): void
    {
        $runner = new FakeCommandRunner(false);

        $manager = new DatabaseTransactionManager($runner);

        $connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );

        $manager->begin($connection);

        $this->assertCount(1, $runner->commands);
        $this->assertSame('mysql', $runner->commands[0]->command[0] ?? null);
        $this->assertTrue(in_array('--skip-ssl', $runner->commands[0]->command, true));
    }
}
