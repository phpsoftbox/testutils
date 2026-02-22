<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PDO;
use PhpSoftBox\TestUtils\Database\Reset\MariaDbResetDriver;
use PhpSoftBox\TestUtils\Database\ResetReloadStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;

/**
 * БД — сервис docker-compose, пользователь — root (нужны права на CREATE/DROP DATABASE); переопределение: TEST_UTILS_MARIADB_DSN.
 */
#[CoversClass(ResetReloadStrategy::class)]
#[CoversClass(MariaDbResetDriver::class)]
#[CoversMethod(ResetReloadStrategy::class, 'reload')]
final class ResetReloadStrategyMariaDbIntegrationTest extends AbstractResetIntegrationTestCase
{
    protected function dsnEnvironmentVariable(): string
    {
        return 'TEST_UTILS_MARIADB_DSN';
    }

    protected function defaultDsn(): string
    {
        return 'mariadb://root:root@mariadb:3306/psb_test_utils_reset';
    }

    protected function schemaDump(): string
    {
        return <<<'SQL'
            -- MariaDB dump
            CREATE TABLE `parents` (
              `id` int NOT NULL AUTO_INCREMENT,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=1251;
            CREATE TABLE `children` (
              `id` int NOT NULL AUTO_INCREMENT,
              `parent_id` int NOT NULL,
              PRIMARY KEY (`id`),
              CONSTRAINT `children_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=158;
            CREATE TABLE `tags` (
              `code` varchar(10) NOT NULL,
              PRIMARY KEY (`code`)
            ) ENGINE=InnoDB;
            -- Dump completed on 2026-09-22

            SQL;
    }

    protected function insertRelatedRows(PDO $pdo): void
    {
        $pdo->exec('
            INSERT INTO parents () VALUES ()
        ');
        $pdo->exec('
            INSERT INTO children (parent_id) VALUES (LAST_INSERT_ID())
        ');
        $pdo->exec("
            INSERT INTO tags (code) VALUES ('a')
        ");
    }

    protected function insertAndDeleteRow(PDO $pdo): void
    {
        $pdo->exec('
            INSERT INTO parents () VALUES ()
        ');
        $pdo->exec('
            DELETE FROM parents
        ');
    }

    protected function counters(PDO $pdo): array
    {
        $rows = $pdo->query("
            SELECT TABLE_NAME, AUTO_INCREMENT
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND AUTO_INCREMENT IS NOT NULL AND TABLE_NAME <> '_test_utils_reset'
            ORDER BY TABLE_NAME
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $counters = [];
        foreach ($rows as $table => $value) {
            $counters[(string) $table] = (int) $value;
        }

        return $counters;
    }

    protected function rowCount(PDO $pdo): int
    {
        return (int) $pdo->query('
            SELECT (SELECT COUNT(*) FROM parents) + (SELECT COUNT(*) FROM children) + (SELECT COUNT(*) FROM tags)
        ')->fetchColumn();
    }
}
