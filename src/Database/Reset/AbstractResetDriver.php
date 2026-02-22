<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

use function implode;
use function is_numeric;
use function sprintf;

abstract class AbstractResetDriver implements ResetDriverInterface
{
    final public function tablesWithRows(ResetConnectionInterface $connection, array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        $branches = [];
        foreach ($tables as $index => $table) {
            $branches[] = sprintf('(SELECT %d AS table_index FROM %s LIMIT 1)', $index, $table);
        }

        $dirty = [];
        foreach ($connection->fetchAll(implode(' UNION ALL ', $branches)) as $row) {
            $index = $row['table_index'] ?? null;
            if (is_numeric($index) && isset($tables[(int) $index])) {
                $dirty[] = $tables[(int) $index];
            }
        }

        return $dirty;
    }

    final public function createServiceTable(ResetConnectionInterface $connection): void
    {
        $connection->execute("
            CREATE TABLE {$this->serviceTable()} (
                hash VARCHAR(64) NOT NULL,
                counters TEXT NOT NULL
            )
        ");
    }
}
