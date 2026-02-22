<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database\Reset;

use function explode;
use function hash;
use function implode;
use function preg_match;
use function str_starts_with;
use function trim;

/**
 * Хеш дампа схемы, устойчивый к комментариям, которые меняются при каждой генерации дампа.
 */
final class DumpSchemaHasher
{
    public function hash(string $dump, string $driver, string $version): string
    {
        return hash('sha256', $this->normalize($dump) . "\n" . $driver . "\n" . $version);
    }

    /**
     * Есть ли в дампе данные (INSERT или COPY ... FROM stdin).
     */
    public function containsData(string $dump): bool
    {
        return preg_match('/^\s*(INSERT\s+INTO|COPY\s+\S+.*\s+FROM\s+stdin)/im', $dump) === 1;
    }

    private function normalize(string $dump): string
    {
        $lines = [];
        foreach (explode("\n", $dump) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $lines[] = $trimmed;
        }

        return implode("\n", $lines);
    }
}
