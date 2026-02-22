<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

/**
 * Собственное соединение стратегии reset с тестовой БД.
 */
interface ResetConnectionInterface
{
    /**
     * @param list<scalar|null> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array;

    /**
     * @param list<scalar|null> $params
     */
    public function execute(string $sql, array $params = []): void;
}
