<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;

use function explode;
use function method_exists;
use function str_contains;
use function trim;

final class SingleConnectionManager implements ConnectionManagerInterface
{
    public function __construct(
        private readonly ConnectionManagerInterface $inner,
        private readonly ?string $defaultAlias = null,
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

    public function reconnect(string $name = 'default'): ConnectionInterface
    {
        $normalized = $this->normalize($name);
        if (method_exists($this->inner, 'reconnect')) {
            return $this->inner->reconnect($normalized);
        }

        return $this->inner->write($normalized);
    }

    private function normalize(string $name): string
    {
        if (str_contains($name, '.')) {
            [$group] = explode('.', $name, 2);
            if ($this->isDefaultAlias($group)) {
                return 'default';
            }

            return $group;
        }

        if ($this->isDefaultAlias($name)) {
            return 'default';
        }

        return $name;
    }

    private function isDefaultAlias(string $name): bool
    {
        $alias = trim((string) $this->defaultAlias);
        if ($alias === '' || $alias === 'default') {
            return false;
        }

        return $name === $alias;
    }
}
