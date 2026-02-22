<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\Command;
use PhpSoftBox\TestUtils\Database\CommandResult;
use PhpSoftBox\TestUtils\Database\CommandRunnerInterface;

use function file_put_contents;

final class FakeCommandRunner implements CommandRunnerInterface
{
    /**
     * @var list<Command>
     */
    public array $commands = [];

    public function __construct(
        private bool $writeStdout = true,
    ) {
    }

    public function run(Command $command): CommandResult
    {
        $this->commands[] = $command;

        if ($this->writeStdout && $command->stdoutFile !== null) {
            file_put_contents($command->stdoutFile, "-- dump --\n");
        }

        return new CommandResult(0, '', '');
    }
}
