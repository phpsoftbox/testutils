<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use RuntimeException;

final class TransactionReloadStrategy implements ReloadStrategyInterface
{
    public function __construct(
        private readonly TransactionAdapterInterface
    $manager)
    {
    }

    public function reload(DatabaseReloaderConnection $connection): void
    {
        if (!$this->manager->supports($connection)) {
            throw new RuntimeException('Transaction reload strategy is not supported for this connection.');
        }

        $this->manager->rollback($connection);
        $this->manager->begin($connection);
    }
}
