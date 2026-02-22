<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils;

use PhpSoftBox\Application\Application;
use PhpSoftBox\Auth\Authorization\UserRoleManager;
use PhpSoftBox\Auth\Contracts\UserInterface;
use PhpSoftBox\Auth\Manager\AuthManager;
use PhpSoftBox\Orm\Contracts\EntityInterface;
use PhpSoftBox\Orm\Contracts\EntityManagerInterface;
use PhpSoftBox\Session\SessionInterface;
use PhpSoftBox\TestUtils\Http\HttpClientConfiguratorInterface;
use PhpSoftBox\TestUtils\Http\TestHttpClient;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Throwable;

use function ctype_digit;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;
use function trim;

abstract class WebTestCase extends ApplicationTestCase
{
    private mixed $actingUser             = null;
    private int|string|null $actingUserId = null;

    protected function authenticate(int|string $userId, string $userHash): void
    {
        $session = $this->session();
        $session->start();
        $session->set($this->authUserIdSessionKey(), $userId);
        $session->set($this->authUserHashSessionKey(), $userHash);
    }

    protected function unauthenticate(): void
    {
        $session = $this->session();
        $session->start();
        $session->forget($this->authUserIdSessionKey());
        $session->forget($this->authUserHashSessionKey());

        $this->actingUser   = null;
        $this->actingUserId = null;
    }

    protected function actingAs(mixed $user, ?string $guard = null, ?string $userHash = null): void
    {
        $loggedIn = $this->loginViaGuard($user, $guard);
        $userId   = $this->resolveAuthUserId($user);
        if ($userId === null) {
            throw new RuntimeException('Authenticated test user id is not resolved.');
        }

        if (!$loggedIn) {
            $session = $this->session();
            $session->start();
            $session->set($this->authUserIdSessionKey(), $userId);
            if ($userHash !== null) {
                $session->set($this->authUserHashSessionKey(), $userHash);
            }
        }

        $this->actingUser = $user instanceof UserInterface
            ? $user
            : new readonly class ($userId, $user) implements UserInterface {
                public function __construct(
                    private int|string $userId,
                    public mixed $identity,
                ) {
                }

                public function id(): int|string|null
                {
                    return $this->userId;
                }
            };
        $this->actingUserId = $userId;
    }

    protected function withAuthToken(string $token, string $cookieName = 'auth_token'): TestHttpClient
    {
        return $this->httpClient()->withAuthToken($token, $cookieName);
    }

    protected function withBearerToken(string $token, string $headerName = 'Authorization'): TestHttpClient
    {
        return $this->httpClient()->withBearerToken($token, $headerName);
    }

    protected function withHost(string $host): TestHttpClient
    {
        return $this->httpClient()->withHost($host);
    }

    protected function withRole(mixed $user, string $roleName, bool $replace = false): mixed
    {
        return $this->withRoles($user, [$roleName], $replace);
    }

    /**
     * @param list<string> $roleNames
     */
    protected function withRoles(mixed $user, array $roleNames, bool $replace = false): mixed
    {
        $manager = $this->userRoleManager();
        $manager->assignRoles($user, $roleNames, $replace);

        return $user;
    }

    protected function authUserIdSessionKey(): string
    {
        return 'auth.user_id';
    }

    protected function authUserHashSessionKey(): string
    {
        return 'auth.user_hash';
    }

    protected function entityManager(): EntityManagerInterface
    {
        $em = $this->container()->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManagerInterface is not configured in container.');
        }

        return $em;
    }

    /**
     * @template TEntity of EntityInterface
     * @param class-string<TEntity> $entityClass
     * @return TEntity|null
     */
    protected function findEntity(string $entityClass, int|string|UuidInterface $id): ?EntityInterface
    {
        $entity = $this->entityManager()->find($entityClass, $id);
        if (!$entity instanceof $entityClass) {
            return null;
        }

        return $entity;
    }

    protected function removeEntity(EntityInterface $entity, bool $force = true, bool $flush = true): void
    {
        $em = $this->entityManager();
        if ($force) {
            $em->forceRemove($entity);
        } else {
            $em->remove($entity);
        }

        if ($flush) {
            $em->flush();
        }
    }

    protected function httpClient(): TestHttpClient
    {
        $configurator = $this->resolveHttpClientConfigurator();

        $client = new TestHttpClient(
            app: $this->app(),
            session: $this->session(),
            baseUri: $this->baseUri(),
            requestConfigurator: $configurator !== null
                ? [$configurator, 'configure']
                : function (ServerRequestInterface $request): void {
                    $this->configureRequest($request);
                },
        );

        if ($this->actingUser !== null) {
            $client = $client->withAttribute('user', $this->actingUser);
        }

        if ($this->actingUserId !== null) {
            $client = $client->withAttribute('user_id', $this->actingUserId);
        }

        return $client;
    }

    protected function configureRequest(ServerRequestInterface $request): void
    {
    }

    protected function httpClientConfigurator(): ?HttpClientConfiguratorInterface
    {
        return null;
    }

    private function resolveHttpClientConfigurator(): ?HttpClientConfiguratorInterface
    {
        $configurator = $this->httpClientConfigurator();
        if ($configurator !== null) {
            return $configurator;
        }

        if (!method_exists($this, 'container')) {
            return null;
        }

        /** @var callable(): mixed $containerAccessor */
        $containerAccessor = [$this, 'container'];
        $container         = $containerAccessor();

        if (is_object($container) && method_exists($container, 'has') && method_exists($container, 'get')) {
            if ($container->has(HttpClientConfiguratorInterface::class)) {
                $instance = $container->get(HttpClientConfiguratorInterface::class);

                return $instance instanceof HttpClientConfiguratorInterface ? $instance : null;
            }
        }

        return null;
    }

    private function authManager(): AuthManager
    {
        $auth = $this->container()->get(AuthManager::class);
        if (!$auth instanceof AuthManager) {
            throw new RuntimeException('AuthManager is not configured in container.');
        }

        return $auth;
    }

    private function userRoleManager(): UserRoleManager
    {
        $manager = $this->container()->get(UserRoleManager::class);
        if (!$manager instanceof UserRoleManager) {
            throw new RuntimeException('UserRoleManager is not configured in container.');
        }

        return $manager;
    }

    private function loginViaGuard(mixed $user, ?string $guard): bool
    {
        try {
            $resolvedGuard = $this->authManager()->guard($guard);
        } catch (Throwable) {
            return false;
        }

        if (!method_exists($resolvedGuard, 'login')) {
            return false;
        }

        try {
            $resolvedGuard->login($user);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function resolveAuthUserId(mixed $user): int|string|null
    {
        if (is_int($user)) {
            return $user > 0 ? $user : null;
        }

        if (is_string($user)) {
            return $this->normalizeAuthUserId($user);
        }

        if (is_array($user)) {
            $id = $user['id'] ?? null;

            return is_int($id) ? ($id > 0 ? $id : null) : (is_string($id) ? $this->normalizeAuthUserId($id) : null);
        }

        if ($user instanceof UserInterface) {
            $id = $user->id();

            return is_int($id) ? ($id > 0 ? $id : null) : (is_string($id) ? $this->normalizeAuthUserId($id) : null);
        }

        if (!is_object($user)) {
            return null;
        }

        if (method_exists($user, 'id')) {
            $id = $user->id();

            return is_int($id) ? ($id > 0 ? $id : null) : (is_string($id) ? $this->normalizeAuthUserId($id) : null);
        }

        if (property_exists($user, 'id')) {
            $id = $user->id;

            return is_int($id) ? ($id > 0 ? $id : null) : (is_string($id) ? $this->normalizeAuthUserId($id) : null);
        }

        return null;
    }

    private function normalizeAuthUserId(string $userId): int|string|null
    {
        $userId = trim($userId);
        if ($userId === '') {
            return null;
        }

        if (ctype_digit($userId)) {
            $resolved = (int) $userId;

            return $resolved > 0 ? $resolved : null;
        }

        return $userId;
    }

    abstract protected function app(): Application;

    abstract protected function session(): SessionInterface;

    abstract protected function baseUri(): string;
}
