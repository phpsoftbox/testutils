<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

/**
 * Состояние reset на процесс: экземпляры стратегии и контейнер между тестами пересоздаются.
 *
 * Ключ — DSN тестовой БД.
 */
final class ResetStateRegistry
{
    /**
     * @var array<string, ResetState>
     */
    private static array $states = [];

    /**
     * @var array<string, string>
     */
    private static array $sqliteTemplates = [];

    public static function get(string $testDsn): ?ResetState
    {
        return self::$states[$testDsn] ?? null;
    }

    public static function set(string $testDsn, ResetState $state): void
    {
        self::$states[$testDsn] = $state;
    }

    public static function forget(string $testDsn): void
    {
        unset(self::$states[$testDsn], self::$sqliteTemplates[$testDsn]);
    }

    public static function sqliteTemplate(string $testDsn): ?string
    {
        return self::$sqliteTemplates[$testDsn] ?? null;
    }

    public static function setSqliteTemplate(string $testDsn, string $templateFile): void
    {
        self::$sqliteTemplates[$testDsn] = $templateFile;
    }

    public static function has(string $testDsn): bool
    {
        return isset(self::$states[$testDsn]) || isset(self::$sqliteTemplates[$testDsn]);
    }

    /**
     * Очищает реестр (для тестов самого test-utils).
     */
    public static function clear(): void
    {
        self::$states          = [];
        self::$sqliteTemplates = [];
    }
}
