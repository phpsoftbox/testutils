<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderTrait;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

abstract class ApplicationTestCase extends TestCase
{
    use DatabaseReloaderTrait;

    protected bool $bootApp = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beforeAppBoot();

        if ($this->bootApp) {
            if ($this->databaseReloadMode !== 'transaction') {
                $this->resetApp();
            }
            $this->bootApp();
        }

        if ($this->reloadDatabase) {
            $this->reloadDatabaseSchema();
        }

        if ($this->bootApp) {
            $this->afterAppBoot();
        }
    }

    protected function beforeAppBoot(): void
    {
    }

    protected function afterAppBoot(): void
    {
    }

    abstract protected function container(): ContainerInterface;

    abstract protected function bootApp(): void;

    protected function resetApp(): void
    {
    }
}
