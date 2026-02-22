<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests;

use PhpSoftBox\Application\Application;
use PhpSoftBox\Auth\Authorization\Store\RoleStoreInterface;
use PhpSoftBox\Auth\Authorization\Store\UserRoleStoreInterface;
use PhpSoftBox\Auth\Authorization\UserRoleManager;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\SessionInterface;
use PhpSoftBox\Session\Store\ArraySessionStore;
use PhpSoftBox\TestUtils\WebTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

use function array_filter;
use function array_values;
use function count;
use function in_array;

final class WebTestCaseTest extends WebTestCase
{
    private SessionInterface $session;
    private ContainerInterface $container;
    private EntityManagerInterface $em;
    private UserRoleManager $roles;
    private ?RequestHandlerInterface $handler = null;

    protected bool $bootApp        = false;
    protected bool $reloadDatabase = false;

    protected function setUp(): void
    {
        $this->session = new Session(new ArraySessionStore());

        $this->em    = $this->createMock(EntityManagerInterface::class);
        $this->roles = new UserRoleManager(
            new class () implements UserRoleStoreInterface {
                /** @var array<int|string, list<int>> */
                private array $rolesByUser = [];
                /** @var array<int, string> */
                private array $namesById = [
                    1 => 'admin',
                    2 => 'manager',
                ];

                public function listRoleIdsByUserId(int|string $userId): array
                {
                    return $this->rolesByUser[$userId] ?? [];
                }

                public function listRoleNamesByUserId(int|string $userId): array
                {
                    $names = [];
                    foreach ($this->listRoleIdsByUserId($userId) as $roleId) {
                        $names[] = $this->namesById[$roleId] ?? '';
                    }

                    return $names;
                }

                public function attach(int|string $userId, int $roleId): void
                {
                    $this->rolesByUser[$userId] ??= [];
                    if (!in_array($roleId, $this->rolesByUser[$userId], true)) {
                        $this->rolesByUser[$userId][] = $roleId;
                    }
                }

                public function detach(int|string $userId, int $roleId): void
                {
                    $this->rolesByUser[$userId] = array_values(array_filter(
                        $this->rolesByUser[$userId] ?? [],
                        static fn (int $current): bool => $current !== $roleId,
                    ));
                }

                public function detachAll(int|string $userId): void
                {
                    unset($this->rolesByUser[$userId]);
                }
            },
            new class () implements RoleStoreInterface {
                /** @var array<string, int> */
                private array $ids = [
                    'admin'   => 1,
                    'manager' => 2,
                ];

                public function findIdByName(string $name): ?int
                {
                    return $this->ids[$name] ?? null;
                }

                public function create(string $name, ?string $label = null, bool $adminAccess = false): int
                {
                    $id               = count($this->ids) + 1;
                    $this->ids[$name] = $id;

                    return $id;
                }

                public function update(string $name, ?string $label = null, bool $adminAccess = false): void
                {
                }

                public function listIdsByName(): array
                {
                    return $this->ids;
                }

                public function deleteByIds(array $ids): void
                {
                }
            },
        );

        $this->container = new class ($this->em, $this->roles) implements ContainerInterface {
            public function __construct(
                private readonly EntityManagerInterface $em,
                private readonly UserRoleManager $roles,
            ) {
            }

            public function get(string $id): mixed
            {
                if ($id === EntityManagerInterface::class) {
                    return $this->em;
                }

                if ($id === UserRoleManager::class) {
                    return $this->roles;
                }

                throw new RuntimeException('Entry not found: ' . $id);
            }

            public function has(string $id): bool
            {
                return $id === EntityManagerInterface::class || $id === UserRoleManager::class;
            }
        };

        parent::setUp();
    }



    /**
     * Проверяет, что методы authenticate/unauthenticate корректно устанавливают и очищают ключи авторизации в сессии.
     */
    #[Test]
    public function authenticateAndUnauthenticateManageSessionKeys(): void
    {
        $this->authenticate(100, 'token-hash');

        self::assertSame(100, $this->session()->get('auth.user_id'));
        self::assertSame('token-hash', $this->session()->get('auth.user_hash'));

        $this->unauthenticate();

        self::assertFalse($this->session()->has('auth.user_id'));
        self::assertFalse($this->session()->has('auth.user_hash'));
    }



    /**
     * Проверяет, что actingAs выставляет session id и request attributes для последующих HTTP-запросов.
     */
    #[Test]
    public function actingAsAuthenticatesUserForHttpClient(): void
    {
        $this->handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $lastRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(200);
            }
        };

        $user = new readonly class () implements UserInterface {
            public function id(): int|string|null
            {
                return 42;
            }
        };

        $this->actingAs($user);
        $this->httpClient()->get('/profile');

        self::assertSame(42, $this->session()->get('auth.user_id'));
        self::assertSame($user, $this->handler->lastRequest?->getAttribute('user'));
        self::assertSame(42, $this->handler->lastRequest?->getAttribute('user_id'));
    }



    /**
     * Проверяет, что withRole назначает роль через UserRoleManager из контейнера.
     */
    #[Test]
    public function withRoleAssignsRoleThroughManager(): void
    {
        $this->withRole(42, 'admin');

        self::assertSame(['admin'], $this->roles->roles(42));
    }



    /**
     * Проверяет, что findEntity делегирует поиск сущности в EntityManager и возвращает найденный объект.
     */
    #[Test]
    public function findEntityDelegatesToEntityManager(): void
    {
        $entity = new class () implements EntityInterface {
            public function id(): int|UuidInterface|null
            {
                return 77;
            }
        };

        $this->em
            ->expects(self::once())
            ->method('find')
            ->with($entity::class, 77)
            ->willReturn($entity);

        self::assertSame($entity, $this->findEntity($entity::class, 77));
    }



    /**
     * Проверяет, что removeEntity в режиме force вызывает forceRemove и выполняет flush.
     */
    #[Test]
    public function removeEntityForceCallsForceRemoveAndFlush(): void
    {
        $entity = new class () implements EntityInterface {
            public function id(): int|UuidInterface|null
            {
                return 1;
            }
        };

        $this->em->expects(self::once())->method('forceRemove')->with($entity);
        $this->em->expects(self::never())->method('remove');
        $this->em->expects(self::once())->method('flush');

        $this->removeEntity($entity, force: true, flush: true);
    }



    /**
     * Проверяет, что removeEntity в мягком режиме без flush вызывает только remove.
     */
    #[Test]
    public function removeEntitySoftWithoutFlushCallsRemoveOnly(): void
    {
        $entity = new class () implements EntityInterface {
            public function id(): int|UuidInterface|null
            {
                return 2;
            }
        };

        $this->em->expects(self::never())->method('forceRemove');
        $this->em->expects(self::once())->method('remove')->with($entity);
        $this->em->expects(self::never())->method('flush');

        $this->removeEntity($entity, force: false, flush: false);
    }

    protected function container(): ContainerInterface
    {
        return $this->container;
    }

    protected function bootApp(): void
    {
    }

    protected function app(): Application
    {
        return new Application(
            handler: $this->handler ?? new class () implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new RuntimeException('Not used in WebTestCaseTest.');
                }
            },
        );
    }

    protected function session(): SessionInterface
    {
        return $this->session;
    }

    protected function baseUri(): string
    {
        return 'https://example.test';
    }
}
