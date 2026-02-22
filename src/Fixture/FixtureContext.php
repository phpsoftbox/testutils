<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Fixture;

use RuntimeException;

use function array_key_exists;

final class FixtureContext
{
    /**
     * @var array<string, mixed>
     */
    private array $services;

    /**
     * @var array<string, true>
     */
    private array $loadedOnce = [];

    /**
     * @param array<string, mixed> $services
     */
    public function __construct(
        ?ReferenceStore $references = null,
        array $services = [],
    ) {
        $this->references = $references ?? new ReferenceStore();
        $this->services   = $services;
    }

    private readonly ReferenceStore $references;

    public function refs(): ReferenceStore
    {
        return $this->references;
    }

    public function hasService(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }

    public function service(string $id): mixed
    {
        if (!$this->hasService($id)) {
            throw new RuntimeException('Fixture service not found: ' . $id);
        }

        return $this->services[$id];
    }

    public function setService(string $id, mixed $service): void
    {
        $this->services[$id] = $service;
    }

    public function wasLoadedOnce(string $key): bool
    {
        return isset($this->loadedOnce[$key]);
    }

    public function markLoadedOnce(string $key): void
    {
        $this->loadedOnce[$key] = true;
    }
}
