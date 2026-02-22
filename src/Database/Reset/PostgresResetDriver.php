<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

use PhpSoftBox\TestUtils\Database\ResetReloadStrategy;

use function implode;
use function sprintf;
use function str_replace;

final class PostgresResetDriver extends AbstractResetDriver
{
    private const string SERVICE_SCHEMA = 'public';

    private const string USER_SCHEMAS_CONDITION = "
        schemaname NOT IN ('pg_catalog', 'information_schema')
        AND schemaname NOT LIKE 'pg\\_toast%'
        AND schemaname NOT LIKE 'pg\\_temp\\_%'
    ";

    public function configureSession(ResetConnectionInterface $connection): void
    {
        $connection->execute("
            SET lock_timeout = '5s'
        ");
    }

    public function terminateForeignSessions(ResetConnectionInterface $connection, string $database): void
    {
        $connection->fetchAll('
            SELECT pg_terminate_backend(pid)
            FROM pg_stat_activity
            WHERE datname = ? AND pid <> pg_backend_pid()
        ', [$database]);
    }

    public function serviceTable(): string
    {
        return $this->quote(self::SERVICE_SCHEMA, ResetReloadStrategy::SERVICE_TABLE);
    }

    public function tables(ResetConnectionInterface $connection, string $database): array
    {
        $condition = self::USER_SCHEMAS_CONDITION;

        $rows = $connection->fetchAll("
            SELECT schemaname, tablename
            FROM pg_tables
            WHERE {$condition}
                AND NOT (schemaname = ? AND tablename = ?)
            ORDER BY schemaname, tablename
        ", [self::SERVICE_SCHEMA, ResetReloadStrategy::SERVICE_TABLE]);

        $tables = [];
        foreach ($rows as $row) {
            $tables[] = $this->quote((string) $row['schemaname'], (string) $row['tablename']);
        }

        return $tables;
    }

    public function counters(ResetConnectionInterface $connection, string $database): array
    {
        $condition = self::USER_SCHEMAS_CONDITION;

        $rows = $connection->fetchAll("
            SELECT schemaname, sequencename, last_value
            FROM pg_sequences
            WHERE {$condition}
        ");

        $counters = [];
        foreach ($rows as $row) {
            $key            = $this->quote((string) $row['schemaname'], (string) $row['sequencename']);
            $counters[$key] = $row['last_value'] === null ? null : (int) $row['last_value'];
        }

        return $counters;
    }

    public function clean(ResetConnectionInterface $connection, array $tables, array $counters): void
    {
        if ($tables !== []) {
            // TRUNCATE не перезапускает последовательности без RESTART IDENTITY: их возвращаем к эталону ниже.
            $connection->execute(sprintf('TRUNCATE TABLE %s CASCADE', implode(', ', $tables)));
        }

        foreach ($counters as $sequence => $value) {
            if ($value === null) {
                // Последовательность ещё не использовалась: RESTART возвращает стартовое значение и is_called = false.
                $connection->execute(sprintf('ALTER SEQUENCE %s RESTART', $sequence));

                continue;
            }

            $connection->execute('
                SELECT setval(?::regclass, ?, true)
            ', [$sequence, $value]);
        }
    }

    private function quote(string $schema, string $name): string
    {
        return '"' . str_replace('"', '""', $schema) . '"."' . str_replace('"', '""', $name) . '"';
    }
}
