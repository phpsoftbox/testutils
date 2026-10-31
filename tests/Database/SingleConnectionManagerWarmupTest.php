<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use LogicException;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Connection\WarmupAwareConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\TestUtils\Database\SingleConnectionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SingleConnectionManager::class)]
#[CoversMethod(SingleConnectionManager::class, 'clearWarmup')]
final class SingleConnectionManagerWarmupTest extends TestCase
{
    /**
     * Проверим, что сброс кеша прогрева доходит до внутреннего менеджера: сброс сервисов
     * приложения вызывает его у менеджера подключений и в тестах.
     *
     * @see SingleConnectionManager::clearWarmup()
     */
    #[Test]
    public function clearWarmupIsForwardedToInnerManager(): void
    {
        $inner = new class () implements ConnectionManagerInterface, WarmupAwareConnectionManagerInterface {
            public int $cleared = 0;

            public function connection(string $name = 'default'): ConnectionInterface
            {
                throw new LogicException('Подключение в этом тесте не нужно.');
            }

            public function read(string $name = 'default'): ConnectionInterface
            {
                throw new LogicException('Подключение в этом тесте не нужно.');
            }

            public function write(string $name = 'default'): ConnectionInterface
            {
                throw new LogicException('Подключение в этом тесте не нужно.');
            }

            public function clearWarmup(): void
            {
                $this->cleared++;
            }
        };

        new SingleConnectionManager($inner)->clearWarmup();

        $this->assertSame(1, $inner->cleared);
    }
}
