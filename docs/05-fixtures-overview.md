# Fixture API (подробно)

Фикстуры нужны для единообразной подготовки тестовых данных без дублирования SQL/boilerplate.

## Состав API

- `FixtureInterface` — контракт одной фикстуры.
- `DependentFixtureInterface` — контракт фикстуры с декларацией зависимостей.
- `FixtureContext` — state-контекст выполнения:
  - `refs()` — ссылки на созданные объекты/ID;
  - `service()` / `setService()` — доступ к переданным сервисам;
  - `wasLoadedOnce()` / `markLoadedOnce()` — механизм `loadOnce`.
- `ReferenceStore` — key/value хранилище ссылок.
- `FixtureRunner` — оркестратор запуска фикстур.

## Базовый контракт фикстуры

```php
use PhpSoftBox\TestUtils\Fixture\FixtureContext;
use PhpSoftBox\TestUtils\Fixture\FixtureInterface;

final class UserFixture implements FixtureInterface
{
    public function load(FixtureContext $context): void
    {
        // создание данных
        $context->refs()->set('user_id', 123);
    }
}
```

## `FixtureRunner` — основной API

```php
$runner = new FixtureRunner();

// Контекст создается явно
$context = $runner->createContext(
    services: [
        UserProvider::class => $userProvider,
    ],
);

// Выполнить фикстуры
$refs = $runner->load($context, new UserFixture());

// Считать ссылку
$userId = $refs->get('user_id');
```

### Порядок загрузки

- Если фикстура реализует только `FixtureInterface`, она выполняется в порядке, в котором передана в `load(...)`.
- Если фикстура реализует `DependentFixtureInterface`, `FixtureRunner` сначала рекурсивно загрузит зависимости, потом саму фикстуру.
- Одинаковые зависимости в одном запуске `load(...)` выполняются один раз.
- При циклической зависимости `FixtureRunner` выбросит `InvalidArgumentException`.

Пример:

```php
use PhpSoftBox\TestUtils\Fixture\DependentFixtureInterface;
use PhpSoftBox\TestUtils\Fixture\FixtureContext;

final class AuthSeedFixture implements FixtureInterface
{
    public function load(FixtureContext $context): void
    {
        // ...
    }
}

final readonly class AdminFixture implements DependentFixtureInterface
{
    public function dependencies(): array
    {
        return [new AuthSeedFixture()];
    }

    public function load(FixtureContext $context): void
    {
        // ...
    }
}
```

## `loadOnce` — семантика

`loadOnce(...)`:

- c явным ключом: `loadOnce('auth-seed', $context, ...$fixtures)`;
- с авто-ключом: `loadOnce(new AuthSeedFixture(), $context)`;
- выполняет фикстуры только один раз на данный `FixtureContext` и рассчитанный ключ;
- повторный вызов с тем же ключом возвращает текущие `refs`, но фикстуры не исполняются.

Пример:

```php
$runner->loadOnce('auth-seed', $context, new AuthSeedFixture());
$runner->loadOnce('auth-seed', $context, new AuthSeedFixture()); // пропуск

$runner->loadOnce(new AuthSeedFixture(), $context);
$runner->loadOnce(new AuthSeedFixture(), $context); // пропуск (ключ = AuthSeedFixture::class)
```

Важно: scope `loadOnce` ограничен конкретным объектом `FixtureContext`.

## `ReferenceStore` — семантика

- `set($key, $value)` — сохранить ссылку;
- `get($key)` — получить или выбросить исключение;
- `getOrNull($key)` — получить `null`, если ключа нет;
- `clear()` — сбросить ссылки.

## Частые ошибки

1. Ожидать глобальный state между тестами.
`FixtureContext` обычно не шарится между разными тест-кейсами.

2. Использовать общие ключи без namespace.
Лучше `admin.user`, `tenant.owner`, `site.operator`.

3. Пытаться хранить бизнес-логику в `FixtureRunner`.
Runner должен оставаться тонким; доменная логика только в фикстурах/провайдерах приложения.
