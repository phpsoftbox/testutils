<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Snapshot;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;

final class JsonSnapshotStore
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function read(string $path): string
    {
        $content = file_get_contents($path);

        return $content === false ? '' : $content;
    }

    public function write(string $path, string $payload): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $payload);
    }
}
