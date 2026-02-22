<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;

use function explode;
use function str_contains;

final class SingleConnectionManager implements ConnectionManagerInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $inner,
    ) {
    }

    public function connection(string $name = 'default'): ConnectionInterface
    {
        return $this->inner->write($this->normalize($name));
    }

    public function read(string $name = 'default'): ConnectionInterface
    {
        return $this->inner->write($this->normalize($name));
    }

    public function write(string $name = 'default'): ConnectionInterface
    {
        return $this->inner->write($this->normalize($name));
    }

    private function normalize(string $name): string
    {
        if (str_contains($name, '.')) {
            [$group] = explode('.', $name, 2);

            return $group;
        }

        return $name;
    }
}
