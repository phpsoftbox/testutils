<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

interface ReloadStrategyInterface
{
    public function reload(DatabaseReloaderConnection $connection): void;
}
