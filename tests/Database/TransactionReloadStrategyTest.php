<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DatabaseTransactionManager;
use PhpSoftBox\TestUtils\Database\TransactionReloadStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionReloadStrategy::class)]
#[CoversMethod(TransactionReloadStrategy::class, 'reload')]
final class TransactionReloadStrategyTest extends TestCase
{
    /**
     * Проверяет, что стратегия выполняет rollback + begin.
     */
    #[Test]
    public function reloadCallsRollbackAndBegin(): void
    {
        $runner = new FakeCommandRunner(false);

        $manager = new DatabaseTransactionManager($runner);

        $strategy = new TransactionReloadStrategy($manager);

        $connection = new DatabaseReloaderConnection(
            'default',
            'sqlite:///tmp/main.sqlite',
            'sqlite:///tmp/test.sqlite',
        );

        $strategy->reload($connection);

        $this->assertCount(2, $runner->commands);
        $this->assertSame(['sqlite3', 'tmp/test.sqlite', 'ROLLBACK'], $runner->commands[0]->command);
        $this->assertSame(['sqlite3', 'tmp/test.sqlite', 'BEGIN'], $runner->commands[1]->command);
    }
}
