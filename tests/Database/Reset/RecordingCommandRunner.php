<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PhpSoftBox\TestUtils\Database\Command;
use PhpSoftBox\TestUtils\Database\CommandResult;
use PhpSoftBox\TestUtils\Database\CommandRunnerInterface;
use PhpSoftBox\TestUtils\Database\ProcessCommandRunner;

/**
 * Выполняет команды настоящим runner'ом и запоминает их.
 */
final class RecordingCommandRunner implements CommandRunnerInterface
{
    /**
     * @var list<Command>
     */
    public array $commands = [];

    private readonly CommandRunnerInterface $runner;

    public function __construct()
    {
        $this->runner = new ProcessCommandRunner();
    }

    public function run(Command $command): CommandResult
    {
        $this->commands[] = $command;

        return $this->runner->run($command);
    }

    /**
     * Сколько раз дамп загружался в тестовую БД.
     */
    public function schemaLoads(string $dumpFile): int
    {
        $loads = 0;
        foreach ($this->commands as $command) {
            if ($command->stdinFile === $dumpFile) {
                $loads++;
            }
        }

        return $loads;
    }
}
