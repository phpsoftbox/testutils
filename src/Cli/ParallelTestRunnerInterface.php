<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Cli;

interface ParallelTestRunnerInterface
{
    /**
     * @param list<string> $command
     * @param array<string, string> $env
     */
    public function run(array $command, array $env = []): int;
}
