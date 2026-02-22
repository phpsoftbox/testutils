<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseTransactionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseTransactionManager::class)]
#[CoversMethod(DatabaseTransactionManager::class, 'supports')]
final class DatabaseTransactionManagerTest extends TestCase
{
    /**
     * Проверяет, что sqlite file поддерживается, а memory нет.
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
}
