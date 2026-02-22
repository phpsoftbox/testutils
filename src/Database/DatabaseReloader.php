<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\TestUtils\Database\Reset\PdoResetConnectionFactory;
use PhpSoftBox\TestUtils\Database\Reset\ResetConnectionFactoryInterface;

final class DatabaseReloader
{
    public function __construct(
        private readonly DatabaseReloaderConfig $config,
        private readonly CommandRunnerInterface $runner = new ProcessCommandRunner(),
        private readonly ?TransactionAdapterInterface $transactionAdapter = null,
        private readonly ResetConnectionFactoryInterface $resetConnectionFactory = new PdoResetConnectionFactory(),
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
        // reset сам получает дамп один раз на процесс; для transaction схема уже подготовлена через reset.
        $mode = DatabaseReloaderModesEnum::fromString($this->config->mode);
        if ($mode === DatabaseReloaderModesEnum::RESET
            || ($mode === DatabaseReloaderModesEnum::TRANSACTION && ResetReloadStrategy::isPrepared($connection))
        ) {
            $this->resolveStrategy()->reload($connection);

            return;
        }

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
            $this->resetConnectionFactory,
        );
    }

    public function withMode(string $mode): self
    {
        $resolvedMode = DatabaseReloaderModesEnum::fromString($mode);

        return new self(
            $this->config->withMode($resolvedMode->value),
            $this->runner,
            $this->transactionAdapter,
            $this->resetConnectionFactory,
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

        if ($mode === DatabaseReloaderModesEnum::RESET) {
            return new ResetReloadStrategy($this->config, $this->runner, $this->resetConnectionFactory);
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
