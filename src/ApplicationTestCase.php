<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

abstract class ApplicationTestCase extends TestCase
{
    use DatabaseReloaderTrait;

    private static ?string $lastDatabaseReloadMode = null;

    protected bool $bootApp = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beforeAppBoot();

        if ($this->bootApp) {
            $modeChanged = self::$lastDatabaseReloadMode !== null
                && self::$lastDatabaseReloadMode !== $this->databaseReloadMode;

            if ($modeChanged && $this->databaseReloadMode === 'transaction') {
                $this->resetPreparedDatabaseConnections();
            }

            if ($this->databaseReloadMode !== 'transaction' || $modeChanged) {
                $this->resetApp();
            }
            $this->bootApp();

            self::$lastDatabaseReloadMode = $this->databaseReloadMode;
        }

        if ($this->reloadDatabase) {
            $this->reloadDatabaseSchema();
        }

        if ($this->bootApp) {
            $this->afterAppBoot();
            $this->seeders();
        }
    }

    protected function beforeAppBoot(): void
    {
    }

    protected function afterAppBoot(): void
    {
    }

    protected function seeders(): void
    {
    }

    abstract protected function container(): ContainerInterface;

    abstract protected function bootApp(): void;

    protected function resetApp(): void
    {
    }
}
