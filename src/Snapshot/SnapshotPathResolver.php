<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Snapshot;

use function ltrim;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class SnapshotPathResolver
{
    public function resolve(SnapshotConfig $config, string $snapshotName): string
    {
        $classPath = str_replace('\\', '/', $config->testClass());
        foreach ($config->classPrefixesToStrip() as $prefix) {
            $prefixPath = str_replace('\\', '/', $prefix);
            if (str_starts_with($classPath, $prefixPath)) {
                $classPath = ltrim(substr($classPath, strlen($prefixPath)), '/');
                break;
            }
        }

        $snapshotName = trim($snapshotName);
        $basePath     = rtrim($config->basePath(), '/');

        return $basePath . '/' . $classPath . '/' . $snapshotName . '.json';
    }
}
