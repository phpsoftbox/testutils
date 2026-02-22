<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PhpSoftBox\Clock\Clock;
use PhpSoftBox\Config\Config;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function date_default_timezone_get;
use function is_dir;
use function is_int;
use function method_exists;
use function mkdir;
use function rmdir;
use function rtrim;
use function scandir;
use function unlink;

final class TestApplication
{
    private static ?ContainerInterface $container                = null;
    private static ?object $app                                  = null;
    private static ?TestApplicationFactory $factory              = null;
    private static ?Config $config                               = null;
    private static ?array $databaseConfig                        = null;
    private static DateTimeInterface|int|string|null $frozenTime = null;

    public static function configure(TestApplicationFactory $factory): void
    {
        self::$factory        = $factory;
        self::$config         = null;
        self::$databaseConfig = null;
    }

    public static function boot(): void
    {
        if (self::$container !== null && self::$app !== null) {
            return;
        }

        if (self::$factory === null) {
            throw new RuntimeException('TestApplicationFactory is not configured.');
        }

        $container = self::$factory->createContainer();
        $app       = self::$factory->createApplication($container);

        self::$container = $container;
        self::$app       = $app;
    }

    public static function container(): ContainerInterface
    {
        self::boot();

        return self::$container;
    }

    public static function app(): object
    {
        self::boot();

        return self::$app;
    }

    public static function reset(): void
    {
        self::$container = null;
        self::$app       = null;
    }

    public static function set(string $id, mixed $value): void
    {
        self::boot();

        if (self::$container !== null && method_exists(self::$container, 'set')) {
            self::$container->set($id, $value);
        }
    }

    public static function config(): Config
    {
        if (self::$config !== null) {
            return self::$config;
        }

        if (self::$factory === null) {
            throw new RuntimeException('TestApplicationFactory is not configured.');
        }

        self::$config = self::$factory->createConfig(readOnly: false);

        return self::$config;
    }

    /**
     * @return array<string, mixed>
     */
    public static function databaseConfig(): array
    {
        if (self::$databaseConfig !== null) {
            return self::$databaseConfig;
        }

        if (self::$factory === null) {
            throw new RuntimeException('TestApplicationFactory is not configured.');
        }

        self::$databaseConfig = self::$factory->createDatabaseConfig();

        return self::$databaseConfig;
    }

    public static function resetConfig(): void
    {
        self::$config         = null;
        self::$databaseConfig = null;
    }

    public static function setConfigValue(string $key, mixed $value): void
    {
        self::config()->set($key, $value);
    }

    public static function setFrozenTime(DateTimeInterface|int|string|null $value): void
    {
        self::$frozenTime = $value;
    }

    public static function frozenTime(): DateTimeInterface|int|string|null
    {
        return self::$frozenTime;
    }

    public static function applyFrozenTime(): void
    {
        if (self::$frozenTime === null) {
            Clock::reset();

            return;
        }

        Clock::freeze(self::normalizeFrozenTime(self::$frozenTime));
    }

    public static function tmpPath(): string
    {
        return self::rootPath() . '/local/tests/tmp';
    }

    public static function clearTmpDirectory(): void
    {
        $path = self::tmpPath();

        if (!is_dir($path)) {
            mkdir($path, 0775, true);

            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                self::removeDirectory($fullPath);
                continue;
            }

            @unlink($fullPath);
        }
    }

    public static function rootPath(): string
    {
        if (self::$factory === null) {
            throw new RuntimeException('TestApplicationFactory is not configured.');
        }

        return rtrim(self::$factory->basePath(), '/');
    }

    public static function clear(): void
    {
        self::$frozenTime     = null;
        self::$config         = null;
        self::$databaseConfig = null;
        self::$container      = null;
        self::$app            = null;
        self::$factory        = null;
    }

    private static function removeDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $fullPath = $path . '/' . $item;
                if (is_dir($fullPath)) {
                    self::removeDirectory($fullPath);
                    continue;
                }

                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }

    private static function normalizeFrozenTime(DateTimeInterface|int|string $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_int($value)) {
            $tz = new DateTimeZone(date_default_timezone_get());

            return new DateTimeImmutable('@' . $value)->setTimezone($tz);
        }

        return new DateTimeImmutable($value);
    }
}
