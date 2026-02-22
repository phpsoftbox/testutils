<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\Database\Connection\Connection;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

final class FakeConnectionManager implements ConnectionManagerInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function connection(string $name = 'default'): Connection
    {
        return $this->connection;
    }

    public function read(string $name = 'default'): Connection
    {
        return $this->connection;
    }

    public function write(string $name = 'default'): Connection
    {
        return $this->connection;
    }
}
