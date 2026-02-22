<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture;

use RuntimeException;

use function array_key_exists;

final class ReferenceStore
{
    /**
     * @var array<string, mixed>
     */
    private array $references = [];

    public function set(string $key, mixed $value): void
    {
        $this->references[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->references);
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw new RuntimeException('Fixture reference not found: ' . $key);
        }

        return $this->references[$key];
    }

    public function getOrNull(string $key): mixed
    {
        return $this->references[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->references;
    }

    public function clear(): void
    {
        $this->references = [];
    }
}
