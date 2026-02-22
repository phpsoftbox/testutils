<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

interface TransactionAdapterInterface
{
    public function supports(DatabaseReloaderConnection $connection): bool;

    public function begin(DatabaseReloaderConnection $connection): void;

    public function rollback(DatabaseReloaderConnection $connection): void;
}
