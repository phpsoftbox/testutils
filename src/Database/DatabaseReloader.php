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
        $bootstrapped = $this->ensureDumpBootstrapIfMissing($connection);
        if ($bootstrapped && $this->mode() === DatabaseReloaderModesEnum::DUMP->value) {
            return;
        }

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
        $resolvedMode = DatabaseReloaderModesEnum::fromString($mode);

        return new self(
            $this->config->withMode($resolvedMode->value),
            $this->runner,
            $this->transactionAdapter,
        );
    }

    public function mode(): string
    {
        return DatabaseReloaderModesEnum::fromString($this->config->mode)->value;
    }

    private function resolveStrategy(): ReloadStrategyInterface
    {
        $mode = DatabaseReloaderModesEnum::fromString($this->config->mode);

        if ($mode === DatabaseReloaderModesEnum::TRANSACTION) {
            if ($this->transactionAdapter === null) {
                throw new DatabaseReloaderException('Transaction mode requires a transaction adapter.');
            }

            return new TransactionReloadStrategy($this->transactionAdapter);
        }

        return new DumpReloadStrategy($this->config, $this->runner);
    }

    private function ensureDumpBootstrapIfMissing(DatabaseReloaderConnection $connection): bool
    {
        $strategy = new DumpReloadStrategy($this->config, $this->runner);

        if (!$strategy->needsBootstrap($connection)) {
            return false;
        }

        $strategy->reload($connection);

        return true;
    }
}
