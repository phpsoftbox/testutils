<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use RuntimeException;

final class ConnectionManagerTransactionAdapter implements TransactionAdapterInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
    ) {
    }

    public function supports(DatabaseReloaderConnection $connection): bool
    {
        return true;
    }

    public function begin(DatabaseReloaderConnection $connection): void
    {
        $conn = $this->resolveConnection($connection);

        $this->resetTransaction($conn);
        $this->beginTransaction($conn);
    }

    public function rollback(DatabaseReloaderConnection $connection): void
    {
        $conn = $this->resolveConnection($connection);

        $this->resetTransaction($conn);
    }

    private function resolveConnection(DatabaseReloaderConnection $connection): Connection
    {
        $conn = $this->connections->write($connection->name);
        if (!$conn instanceof Connection) {
            throw new RuntimeException('Transaction adapter expects a Connection instance.');
        }

        return $conn;
    }

    private function beginTransaction(Connection $connection): void
    {
        $connection->beginTransactionManual();
    }

    private function rollbackTransaction(Connection $connection): void
    {
        $connection->rollbackTransactionManual();
    }

    private function resetTransaction(Connection $connection): void
    {
        $this->rollbackTransaction($connection);

        $pdo = $connection->pdo();
        while ($pdo->inTransaction()) {
            $this->rollbackTransaction($connection);
        }
    }
}
