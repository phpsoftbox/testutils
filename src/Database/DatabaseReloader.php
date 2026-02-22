<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

final class DatabaseReloader
{
    public function __construct(
        private readonly DatabaseReloaderConfig $config,
        private readonly CommandRunnerInterface $runner = new ProcessCommandRunner(),
        private readonly ?TransactionAdapterInterface $transactionAdapter = null,
    ) {
    }

    public function reloadAll(): void
    {
        foreach ($this->config->connections as $connection) {
            $this->reload($connection);
        }
    }

    public function reload(DatabaseReloaderConnection $connection): void
    {
        $this->resolveStrategy()->reload($connection);
    }

    /**
     * @param list<string> $connectionNames
     */
    public function withConnections(array $connectionNames): self
    {
        return new self(
            $this->config->withConnections($connectionNames),
            $this->runner,
            $this->transactionAdapter,
        );
    }

    public function withMode(string $mode): self
    {
        return new self(
            $this->config->withMode($mode),
            $this->runner,
            $this->transactionAdapter,
        );
    }

    public function mode(): string
    {
        return $this->config->mode;
    }

    private function resolveStrategy(): ReloadStrategyInterface
    {
        if ($this->config->mode === 'transaction') {
            if ($this->transactionAdapter === null) {
                throw new DatabaseReloaderException('Transaction mode requires a transaction adapter.');
            }

            return new TransactionReloadStrategy($this->transactionAdapter);
        }

        return new DumpReloadStrategy($this->config, $this->runner);
    }
}
