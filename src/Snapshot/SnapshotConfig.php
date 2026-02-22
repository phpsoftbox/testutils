<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Snapshot;

final class SnapshotConfig
{
    /**
     * @param array<int, string> $excludedKeys
     * @param array<int, string> $classPrefixesToStrip
     */
    public function __construct(
        private readonly string $basePath,
        private readonly string $testClass,
        private readonly array $excludedKeys = [],
        private readonly array $classPrefixesToStrip = [],
        private readonly bool $autoCreate = true,
        private readonly bool $autoUpdateOnMismatch = true,
    ) {
    }

    public static function forTestClass(string $basePath, string $testClass): self
    {
        return new self($basePath, $testClass);
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function testClass(): string
    {
        return $this->testClass;
    }

    /**
     * @return array<int, string>
     */
    public function excludedKeys(): array
    {
        return $this->excludedKeys;
    }

    /**
     * @return array<int, string>
     */
    public function classPrefixesToStrip(): array
    {
        return $this->classPrefixesToStrip;
    }

    public function autoCreate(): bool
    {
        return $this->autoCreate;
    }

    public function autoUpdateOnMismatch(): bool
    {
        return $this->autoUpdateOnMismatch;
    }

    /**
     * @param array<int, string> $keys
     */
    public function withExcludedKeys(array $keys): self
    {
        return new self(
            $this->basePath,
            $this->testClass,
            $keys,
            $this->classPrefixesToStrip,
            $this->autoCreate,
            $this->autoUpdateOnMismatch,
        );
    }

    /**
     * @param array<int, string> $prefixes
     */
    public function withClassPrefixesToStrip(array $prefixes): self
    {
        return new self(
            $this->basePath,
            $this->testClass,
            $this->excludedKeys,
            $prefixes,
            $this->autoCreate,
            $this->autoUpdateOnMismatch,
        );
    }

    public function withAutoCreate(bool $autoCreate): self
    {
        return new self(
            $this->basePath,
            $this->testClass,
            $this->excludedKeys,
            $this->classPrefixesToStrip,
            $autoCreate,
            $this->autoUpdateOnMismatch,
        );
    }

    public function withAutoUpdateOnMismatch(bool $autoUpdateOnMismatch): self
    {
        return new self(
            $this->basePath,
            $this->testClass,
            $this->excludedKeys,
            $this->classPrefixesToStrip,
            $this->autoCreate,
            $autoUpdateOnMismatch,
        );
    }
}
