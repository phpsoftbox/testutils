<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

/**
 * SQL сброса тестовой БД для конкретного драйвера.
 *
 * Таблицы и счётчики передаются как уже экранированные идентификаторы.
 */
interface ResetDriverInterface
{
    /**
     * Настраивает сессию соединения сброса (таймауты ожидания блокировок).
     */
    public function configureSession(ResetConnectionInterface $connection): void;

    /**
     * Завершает все сессии тестовой БД, кроме текущей.
     */
    public function terminateForeignSessions(ResetConnectionInterface $connection, string $database): void;

    /**
     * Экранированное имя служебной таблицы reset.
     */
    public function serviceTable(): string;

    /**
     * Очищаемые таблицы тестовой БД (без служебной).
     *
     * @return list<string>
     */
    public function tables(ResetConnectionInterface $connection, string $database): array;

    /**
     * Текущие значения счётчиков: идентификатор → значение (null — последовательность не использовалась).
     *
     * @return array<string, int|null>
     */
    public function counters(ResetConnectionInterface $connection, string $database): array;

    /**
     * Таблицы, в которых есть строки.
     *
     * @param list<string> $tables
     * @return list<string>
     */
    public function tablesWithRows(ResetConnectionInterface $connection, array $tables): array;

    /**
     * Очищает таблицы и возвращает счётчики к эталону.
     *
     * @param list<string> $tables
     * @param array<string, int|null> $counters счётчики к возврату: идентификатор → эталон
     */
    public function clean(ResetConnectionInterface $connection, array $tables, array $counters): void;

    /**
     * Создаёт служебную таблицу reset.
     */
    public function createServiceTable(ResetConnectionInterface $connection): void;
}
