<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use RuntimeException;

use function fclose;
use function is_array;
use function is_resource;
use function proc_close;
use function proc_open;
use function stream_get_contents;

final class ProcessCommandRunner implements CommandRunnerInterface
{
    public function run(Command $command): CommandResult
    {
        $descriptorSpec = [
            0 => $command->stdinFile !== null
                ? ['file', $command->stdinFile, 'r']
                : ['pipe', 'r'],
            1 => $command->stdoutFile !== null
                ? ['file', $command->stdoutFile, 'w']
                : ['pipe', 'w'],
            2 => $command->stderrFile !== null
                ? ['file', $command->stderrFile, 'w']
                : ['pipe', 'w'],
        ];

        $process = proc_open(
            $command->command,
            $descriptorSpec,
            $pipes,
            $command->workingDirectory,
            $command->env,
        );

        if (!is_resource($process) || !is_array($pipes)) {
            throw new RuntimeException('Failed to start process.');
        }

        $stdin = $pipes[0] ?? null;
        if (is_resource($stdin)) {
            fclose($stdin);
        }

        $stdout = $pipes[1] ?? null;
        $stderr = $pipes[2] ?? null;

        $stdoutContent = is_resource($stdout) ? stream_get_contents($stdout) : '';
        $stderrContent = is_resource($stderr) ? stream_get_contents($stderr) : '';

        if (is_resource($stdout)) {
            fclose($stdout);
        }

        if (is_resource($stderr)) {
            fclose($stderr);
        }

        $exitCode = proc_close($process);

        return new CommandResult(
            exitCode: $exitCode,
            stdout: $stdoutContent ?? '',
            stderr: $stderrContent ?? '',
        );
    }
}
