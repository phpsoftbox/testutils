# Интеграция Fixture в приложение

Этот документ описывает интеграцию fixture-механизма в конкретное приложение.

## Принципиальная модель

`TestUtils` не знает о вашем домене, area или контейнерных соглашениях.

Поэтому:

- в `TestUtils` лежит только generic API (`FixtureRunner`, `FixtureContext`, `ReferenceStore`);
- domain/area-фикстуры размещаются в приложении, например:
  - `tests/Utils/Fixtures/*`

## Нужно ли регистрировать `FixtureContext` в DI?

Обычно нет.

`FixtureContext` — stateful-объект:

- хранит ссылки (`refs`);
- хранит флаги `loadOnce`.

Если зарегистрировать его как singleton в контейнере — получите утечки между тестами.

Рекомендация:

- в DI регистрировать `FixtureRunner`;
- `FixtureContext` создавать явно на тест/тест-класс через `runner->createContext(...)`.

## Пример definitions (testing env)

```php
<?php

declare(strict_types=1);

use PhpSoftBox\TestUtils\Fixture\FixtureRunner;
use function DI\autowire;

return [
    FixtureRunner::class => autowire(),
];
```

## Пример IntegrationTestCase

```php
use PhpSoftBox\TestUtils\Fixture\FixtureContext;
use PhpSoftBox\TestUtils\Fixture\FixtureRunner;

abstract class IntegrationTestCase extends WebTestCase
{
    private ?FixtureContext $fixtureContext = null;

    protected function fixtureContext(): FixtureContext
    {
        if ($this->fixtureContext !== null) {
            return $this->fixtureContext;
        }

        $runner = $this->container()->get(FixtureRunner::class);

        return $this->fixtureContext = $runner->createContext(
            services: [
                UserProvider::class => $this->container()->get(UserProvider::class),
                RoleProvider::class => $this->container()->get(RoleProvider::class),
            ],
        );
    }
}
```

## Пример domain-фикстур в приложении

```php
use PhpSoftBox\TestUtils\Fixture\FixtureContext;
use PhpSoftBox\TestUtils\Fixture\FixtureInterface;

final class AuthSeedFixture implements FixtureInterface
{
    public function load(FixtureContext $context): void
    {
        $roles = $context->service(RoleProvider::class);
        $roles->sync();
    }
}

final readonly class AdminUserFixture implements FixtureInterface
{
    public function __construct(private string $ref = 'admin') {}

    public function load(FixtureContext $context): void
    {
        $users = $context->service(UserProvider::class);
        $admin = $users->create(['role' => 'admin']);
        $context->refs()->set($this->ref, $admin);
    }
}
```

## Пример использования в тесте

```php
#[Test]
public function usersIndexIsAvailableForAdmin(): void
{
    $runner = $this->container()->get(FixtureRunner::class);
    $context = $this->fixtureContext();

    $runner->loadOnce(new AuthSeedFixture(), $context);
    $runner->load($context, new AdminUserFixture('initiator'));

    $admin = $context->refs()->get('initiator');
    // authenticate + assertions
}
```

## Роли фикстур и провайдеров

- `Provider` — низкоуровневый строитель данных (`create user`, `ensure role`).
- `Fixture` — сценарий подготовки (`auth-seed`, `admin+permissions`, `tenant-with-owner`).

Обычно фикстура вызывает 1..N провайдеров.

## Рекомендации

1. Делайте фикстуры маленькими и композиционными.
   Для связки атомарных фикстур используйте `DependentFixtureInterface`, а не ручной вызов одних фикстур из других.
2. Разделяйте seed-фикстуры (`AuthSeedFixture`) и test-data фикстуры (`UserFixture`).
3. Используйте `loadOnce` только для повторяемых сидов.
4. Не кладите в `FixtureContext` весь контейнер.
5. Не переносите domain-логику в `FixtureRunner`.
