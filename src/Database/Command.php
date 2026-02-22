<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

final readonly class Command
{
    /**
     * @param list<string> $command
     * @param array<string, string> $env
     */
    public function __construct(
        public array $command,
        public array $env = [],
        public ?string $workingDirectory = null,
        public ?string $stdinFile = null,
        public ?string $stdoutFile = null,
        public ?string $stderrFile = null,
    ) {
    }
}
