<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

use PDOException;
use PhpSoftBox\TestUtils\Database\ResetReloadStrategy;

use function is_numeric;
use function sprintf;
use function str_contains;
use function str_replace;
use function stripos;

/**
 * Сброс для MariaDB и MySQL: сервер определяется по `VERSION()`, а не по имени драйвера в DSN.
 */
final class MariaDbResetDriver extends AbstractResetDriver
{
    public function configureSession(ResetConnectionInterface $connection): void
    {
        $connection->execute('
            SET SESSION lock_wait_timeout = 5
        ');

        // MySQL 8 кеширует information_schema.TABLES.AUTO_INCREMENT на information_schema_stats_expiry (86400 с):
        // без отключения кеша сдвиг счётчика не виден и счётчик не возвращается. В MariaDB переменной нет.
        if ($this->isMySql($connection)) {
            $connection->execute('
                SET SESSION information_schema_stats_expiry = 0
            ');
        }
    }

    public function terminateForeignSessions(ResetConnectionInterface $connection, string $database): void
    {
        $rows = $connection->fetchAll(
            '
                SELECT ID
                FROM information_schema.PROCESSLIST
                WHERE DB = ?
                    AND ID <> CONNECTION_ID()
            ',
            [$database],
        );

        foreach ($rows as $row) {
            $processId = $row['ID'] ?? null;
            if (!is_numeric($processId)) {
                continue;
            }

            try {
                $connection->execute(sprintf('KILL %d', (int) $processId));
            } catch (PDOException $exception) {
                // Сессия могла завершиться сама между SELECT и KILL.
                if (!str_contains($exception->getMessage(), '1094')) {
                    throw $exception;
                }
            }
        }
    }

    public function serviceTable(): string
    {
        return $this->quote(ResetReloadStrategy::SERVICE_TABLE);
    }

    public function tables(ResetConnectionInterface $connection, string $database): array
    {
        $rows = $connection->fetchAll(
            '
                SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                    AND TABLE_TYPE = \'BASE TABLE\'
                    AND TABLE_NAME <> ?
                ORDER BY TABLE_NAME
            ',
            [$database, ResetReloadStrategy::SERVICE_TABLE],
        );

        $tables = [];
        foreach ($rows as $row) {
            $tables[] = $this->quote((string) $row['TABLE_NAME']);
        }

        return $tables;
    }

    public function counters(ResetConnectionInterface $connection, string $database): array
    {
        $rows = $connection->fetchAll(
            '
                SELECT TABLE_NAME, AUTO_INCREMENT
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                    AND TABLE_TYPE = \'BASE TABLE\'
                    AND TABLE_NAME <> ?
                    AND AUTO_INCREMENT IS NOT NULL
            ',
            [$database, ResetReloadStrategy::SERVICE_TABLE],
        );

        $counters = [];
        foreach ($rows as $row) {
            $counters[$this->quote((string) $row['TABLE_NAME'])] = (int) $row['AUTO_INCREMENT'];
        }

        return $counters;
    }

    public function clean(ResetConnectionInterface $connection, array $tables, array $counters): void
    {
        $connection->execute('
            SET FOREIGN_KEY_CHECKS = 0
        ');

        try {
            // DELETE (DML) одной транзакцией — один коммит; по замеру дешевле TRUNCATE, который пересоздаёт
            // tablespace. DELETE не меняет AUTO_INCREMENT, поэтому возвращаются только сдвинутые счётчики.
            if ($tables !== []) {
                $connection->execute('
                    START TRANSACTION
                ');
                foreach ($tables as $table) {
                    $connection->execute(sprintf('DELETE FROM %s', $table));
                }
                $connection->execute('
                    COMMIT
                ');
            }

            foreach ($counters as $table => $value) {
                $connection->execute(sprintf('ALTER TABLE %s AUTO_INCREMENT = %d', $table, (int) $value));
            }
        } finally {
            $connection->execute('
                SET FOREIGN_KEY_CHECKS = 1
            ');
        }
    }

    private function isMySql(ResetConnectionInterface $connection): bool
    {
        $rows    = $connection->fetchAll('SELECT VERSION() AS version');
        $version = (string) ($rows[0]['version'] ?? '');

        return $version !== '' && stripos($version, 'mariadb') === false;
    }

    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
