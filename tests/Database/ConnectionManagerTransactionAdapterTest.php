<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PDO;
use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Driver\SqliteDriver;
use PhpSoftBox\TestUtils\Database\ConnectionManagerTransactionAdapter;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionManagerTransactionAdapter::class)]
#[CoversMethod(ConnectionManagerTransactionAdapter::class, 'supports')]
#[CoversMethod(ConnectionManagerTransactionAdapter::class, 'begin')]
#[CoversMethod(ConnectionManagerTransactionAdapter::class, 'rollback')]
final class ConnectionManagerTransactionAdapterTest extends TestCase
{
    /**
     * Проверяет, что адаптер поддерживает любые подключения.
     */
    #[Test]
    public function supportsAlwaysReturnsTrue(): void
    {
        $adapter = new ConnectionManagerTransactionAdapter(new FakeConnectionManager($this->makeConnection()));

        $connection = new DatabaseReloaderConnection(
            'default',
            'sqlite:///tmp/main.sqlite',
            'sqlite:///tmp/test.sqlite',
        );

        $this->assertTrue($adapter->supports($connection));
    }

    /**
     * Проверяет, что begin/rollback управляют транзакцией через Connection.
     */
    #[Test]
    public function beginAndRollbackManageTransaction(): void
    {
        $connection = $this->makeConnection();
        $adapter    = new ConnectionManagerTransactionAdapter(new FakeConnectionManager($connection));

        $reloadConnection = new DatabaseReloaderConnection(
            'default',
            'sqlite:///tmp/main.sqlite',
            'sqlite:///tmp/test.sqlite',
        );

        $this->assertFalse($connection->pdo()->inTransaction());

        $adapter->begin($reloadConnection);
        $this->assertTrue($connection->pdo()->inTransaction());

        $adapter->rollback($reloadConnection);
        $this->assertFalse($connection->pdo()->inTransaction());
    }

    private function makeConnection(): Connection
    {
        $pdo = new PDO('sqlite::memory:');

        return new Connection($pdo, new SqliteDriver());
    }
}
