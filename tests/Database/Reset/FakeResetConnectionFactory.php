<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database\Reset;

use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\Reset\ResetConnectionFactoryInterface;
use PhpSoftBox\TestUtils\Database\Reset\ResetConnectionInterface;
use RuntimeException;
use Throwable;

use function array_shift;

final class FakeResetConnectionFactory implements ResetConnectionFactoryInterface
{
    public int $connects = 0;

    /**
     * @param list<ResetConnectionInterface|Throwable> $connections очередь результатов connect()
     */
    public function __construct(
        private array $connections = [],
    ) {
    }

    public function push(ResetConnectionInterface|Throwable $connection): self
    {
        $this->connections[] = $connection;

        return $this;
    }

    public function connect(DatabaseReloaderConnection $connection): ResetConnectionInterface
    {
        $this->connects++;

        $next = array_shift($this->connections) ?? new RuntimeException('No fake reset connection queued.');
        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}
