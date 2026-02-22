<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Cli;

use PhpSoftBox\CliApp\Command\InMemoryCommandRegistry;
use PhpSoftBox\TestUtils\Cli\TestDatabaseReloadHandler;
use PhpSoftBox\TestUtils\Cli\TestUtilsCommandProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestUtilsCommandProvider::class)]
final class TestUtilsCommandProviderTest extends TestCase
{
    /**
     * Проверяет, что провайдер CLI-команд регистрирует команду перезагрузки тестовой базы данных.
     */
    #[Test]
    public function registersReloadCommand(): void
    {
        $registry = new InMemoryCommandRegistry(false);

        new TestUtilsCommandProvider()->register($registry);

        $command = $registry->get('test:db:reload');

        $this->assertNotNull($command);
        $this->assertSame('test:db:reload', $command->name);
        $this->assertSame(TestDatabaseReloadHandler::class, $command->handler);
    }
}
