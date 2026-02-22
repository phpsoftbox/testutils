<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Snapshot;

use function array_key_exists;
use function array_pop;
use function ctype_digit;
use function explode;
use function is_array;
use function is_string;
use function str_contains;

final class ArrayPath
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $paths
     * @return array<string, mixed>
     */
    public static function forget(array $data, array $paths): array
    {
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            self::forgetPath($data, $path);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function forgetPath(array &$data, string $path): void
    {
        $segments = str_contains($path, '.') ? explode('.', $path) : [$path];
        $last     = array_pop($segments);

        if ($last === null) {
            return;
        }

        $current = & $data;
        foreach ($segments as $segment) {
            $key = self::normalizeSegment($segment);
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return;
            }

            $current = & $current[$key];
        }

        $key = self::normalizeSegment($last);
        if (is_array($current) && array_key_exists($key, $current)) {
            unset($current[$key]);
        }
    }

    private static function normalizeSegment(string $segment): int|string
    {
        if ($segment !== '' && ctype_digit($segment)) {
            return (int) $segment;
        }

        return $segment;
    }
}
