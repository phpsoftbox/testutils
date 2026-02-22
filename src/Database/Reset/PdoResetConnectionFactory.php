<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

use PDO;
use PhpSoftBox\Database\Contracts\DriverInterface;
use PhpSoftBox\Database\Driver\MariaDbDriver;
use PhpSoftBox\Database\Driver\MySqlDriver;
use PhpSoftBox\Database\Driver\PostgresDriver;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderException;

use function array_replace;
use function sprintf;

final class PdoResetConnectionFactory implements ResetConnectionFactoryInterface
{
    public function connect(DatabaseReloaderConnection $connection): ResetConnectionInterface
    {
        $dsn    = $connection->test;
        $driver = $this->driver($dsn->driver);

        $pdo = new PDO(
            $driver->pdoDsn($dsn),
            $dsn->user,
            $dsn->password,
            array_replace($driver->defaultPdoOptions(), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]),
        );

        return new PdoResetConnection($pdo);
    }

    private function driver(string $name): DriverInterface
    {
        return match ($name) {
            'mariadb'           => new MariaDbDriver(),
            'mysql'             => new MySqlDriver(),
            'postgres', 'pgsql' => new PostgresDriver(),
            default             => throw new DatabaseReloaderException(sprintf(
                'Reset connection is not supported for driver "%s".',
                $name,
            )),
        };
    }
}
