<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_diff;
use function array_merge;
use function array_unique;
use function array_values;

trait DatabaseReloaderTrait
{
    /**
     * @var list<string>
     */
    protected array $databaseConnections = ['default'];

    protected bool $reloadDatabase       = true;
    protected string $databaseReloadMode = 'transaction';

    /**
     * @var list<string>
     */
    private static array $preparedConnections = [];

    protected function reloadDatabaseSchema(): void
    {
        $reloader = $this->databaseReloader();

        if ($this->databaseReloadMode === 'transaction') {
            $this->prepareDatabaseSchema($reloader);
        }

        if ($reloader->mode() !== $this->databaseReloadMode) {
            $reloader = $reloader->withMode($this->databaseReloadMode);
        }

        if ($this->databaseConnections !== []) {
            $reloader = $reloader->withConnections($this->databaseConnections);
        }

        $reloader->reloadAll();
    }

    protected function databaseReloader(): DatabaseReloader
    {
        $container = $this->container();
        if (!$container->has(DatabaseReloader::class)) {
            throw new RuntimeException('DatabaseReloader is not configured in the container.');
        }

        return $container->get(DatabaseReloader::class);
    }

    protected function prepareDatabaseSchema(DatabaseReloader $reloader): void
    {
        $missing = array_values(array_diff($this->databaseConnections, self::$preparedConnections));
        if ($missing === []) {
            return;
        }

        $reloader = $reloader
            ->withMode('dump')
            ->withConnections($missing);

        $reloader->reloadAll();

        self::$preparedConnections = array_values(array_unique(array_merge(self::$preparedConnections, $missing)));
    }

    abstract protected function container(): ContainerInterface;
}
