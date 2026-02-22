<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

final readonly class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }

    public function isOk(): bool
    {
        return $this->exitCode === 0;
    }
}
