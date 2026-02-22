<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Cli;

use RuntimeException;

use function array_merge;
use function fclose;
use function getenv;
use function is_array;
use function is_resource;
use function proc_close;
use function proc_open;
use function sprintf;

final class ProcOpenParallelTestRunner implements ParallelTestRunnerInterface
{
    public function run(array $command, array $env = []): int
    {
        $baseEnv = getenv();
        if (!is_array($baseEnv)) {
            $baseEnv = [];
        }

        $process = proc_open(
            $command,
            [
                0 => ['file', 'php://stdin', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stderr', 'w'],
            ],
            $pipes,
            null,
            array_merge($baseEnv, $env),
        );

        if (!is_resource($process)) {
            throw new RuntimeException(sprintf('Failed to start process "%s".', $command[0] ?? 'unknown'));
        }

        if (is_array($pipes)) {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }

        return proc_close($process);
    }
}
