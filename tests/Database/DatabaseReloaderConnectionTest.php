<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseReloaderConnection::class)]
#[CoversMethod(DatabaseReloaderConnection::class, 'assertDifferentDatabases')]
final class DatabaseReloaderConnectionTest extends TestCase
{
    /**
     * Проверяет, что одинаковые main/test БД запрещены для сетевых драйверов.
     *
     * @see DatabaseReloaderConnection::assertDifferentDatabases()
     * @see DatabaseReloaderException::class
     */
    #[Test]
    public function assertDifferentDatabasesRejectsSameDatabase(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'postgres://user:pass@localhost:5432/app',
            'postgres://user:pass@localhost:5432/app',
        );

        // Должно выброситься исключение из-за совпадающих БД.
        $this->expectException(DatabaseReloaderException::class);
        $connection->assertDifferentDatabases();
    }
}
