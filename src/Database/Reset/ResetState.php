<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

/**
 * Подготовленная схема тестовой БД: соединение сброса, хеш дампа и эталон.
 */
final readonly class ResetState
{
    /**
     * @param list<string> $tables
     * @param array<string, int|null> $counters
     */
    public function __construct(
        public ResetConnectionInterface $connection,
        public ResetDriverInterface $driver,
        public string $hash,
        public array $tables,
        public array $counters,
    ) {
    }
}
