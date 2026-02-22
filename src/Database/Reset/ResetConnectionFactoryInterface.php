<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;

interface ResetConnectionFactoryInterface
{
    /**
     * Открывает соединение с тестовой БД подключения.
     */
    public function connect(DatabaseReloaderConnection $connection): ResetConnectionInterface;
}
