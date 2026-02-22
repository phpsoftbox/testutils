<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PDO;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Contracts\DriverInterface;
use PhpSoftBox\Database\IsolationLevelEnum;
use PhpSoftBox\Database\QueryBuilder\QueryFactory;
use PhpSoftBox\Database\SchemaBuilder\SchemaBuilderInterface;
use PhpSoftBox\TestUtils\Database\SingleConnectionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(SingleConnectionManager::class)]
#[CoversMethod(SingleConnectionManager::class, 'connection')]
#[CoversMethod(SingleConnectionManager::class, 'read')]
#[CoversMethod(SingleConnectionManager::class, 'write')]
final class SingleConnectionManagerTest extends TestCase
{
    /**
     * Проверяет, что менеджер всегда использует write‑подключение и нормализует имя.
     */
    #[Test]
    public function usesWriteConnectionForAllCalls(): void
    {
        $connection = new class () implements ConnectionInterface {
            public function pdo(): PDO
            {
                throw new RuntimeException('Not implemented.');
            }

            public function fetchAll(string $sql, array $params = []): array
            {
                return [];
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function execute(string $sql, array $params = []): int
            {
                return 0;
            }

            public function transaction(callable $fn, ?IsolationLevelEnum $isolationLevel = null): mixed
            {
                return $fn();
            }

            public function lastInsertId(?string $name = null): string
            {
                return '0';
            }

            public function prefix(): string
            {
                return '';
            }

            public function table(string $name): string
            {
                return $name;
            }

            public function isReadOnly(): bool
            {
                return false;
            }

            public function schema(): SchemaBuilderInterface
            {
                throw new RuntimeException('Not implemented.');
            }

            public function logger(): ?LoggerInterface
            {
                return null;
            }

            public function query(): QueryFactory
            {
                throw new RuntimeException('Not implemented.');
            }

            public function driver(): DriverInterface
            {
                throw new RuntimeException('Not implemented.');
            }
        };

        $inner = new class ($connection) implements ConnectionManagerInterface {
            public array $calls = [];

            public function __construct(
                private readonly ConnectionInterface $connection,
            ) {
            }

            public function connection(string $name = 'default'): ConnectionInterface
            {
                $this->calls[] = ['connection', $name];

                return $this->connection;
            }

            public function read(string $name = 'default'): ConnectionInterface
            {
                $this->calls[] = ['read', $name];

                return $this->connection;
            }

            public function write(string $name = 'default'): ConnectionInterface
            {
                $this->calls[] = ['write', $name];

                return $this->connection;
            }
        };

        $manager = new SingleConnectionManager($inner);

        $this->assertSame($connection, $manager->connection('main.read'));
        $this->assertSame($connection, $manager->read('main.read'));
        $this->assertSame($connection, $manager->write('search.write'));

        $this->assertSame([
            ['write', 'main'],
            ['write', 'main'],
            ['write', 'search'],
        ], $inner->calls);
    }
}
