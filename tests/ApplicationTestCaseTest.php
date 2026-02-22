<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests;

use PhpSoftBox\TestUtils\ApplicationTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class ApplicationTestCaseTest extends ApplicationTestCase
{
    protected bool $reloadDatabase = false;

    private int $beforeBootCalls = 0;
    private int $bootCalls       = 0;
    private int $afterBootCalls  = 0;
    private int $seedersCalls    = 0;



    /**
     * Проверяет, что во время setUp вызывается инициализация сидов после выполнения afterAppBoot.
     */
    #[Test]
    public function seedersAreCalledDuringSetUpAfterAfterAppBoot(): void
    {
        self::assertSame(1, $this->beforeBootCalls);
        self::assertSame(1, $this->bootCalls);
        self::assertSame(1, $this->afterBootCalls);
        self::assertSame(1, $this->seedersCalls);
    }

    protected function beforeAppBoot(): void
    {
        $this->beforeBootCalls++;
    }

    protected function afterAppBoot(): void
    {
        $this->afterBootCalls++;
    }

    protected function seeders(): void
    {
        $this->seedersCalls++;
    }

    protected function container(): ContainerInterface
    {
        return new class () implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException('Container is not used in ApplicationTestCaseTest.');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }

    protected function bootApp(): void
    {
        $this->bootCalls++;
    }
}
