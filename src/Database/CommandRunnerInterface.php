<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

interface CommandRunnerInterface
{
    public function run(Command $command): CommandResult;
}
