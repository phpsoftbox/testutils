# Базовые TestCase и HTTP-клиент

## ApplicationTestCase

`ApplicationTestCase` — базовый класс для тестов без HTTP.

Подходит для:

- сервисов;
- доменной логики;
- команд/хендлеров.

## WebTestCase

`WebTestCase` расширяет `ApplicationTestCase` и добавляет `TestHttpClient`.

Подходит для:

- интеграционных тестов контроллеров;
- проверки редиректов, статусов, flash, inertia-ответов.

## Минимальный пример `WebTestCase`

```php
use PhpSoftBox\Application\Application;
use PhpSoftBox\Session\SessionInterface;
use PhpSoftBox\TestUtils\TestApplication;
use PhpSoftBox\TestUtils\WebTestCase;
use Psr\Container\ContainerInterface;

abstract class IntegrationTestCase extends WebTestCase
{
    protected function container(): ContainerInterface
    {
        return TestApplication::container();
    }

    protected function bootApp(): void
    {
        TestApplication::boot();
    }

    protected function resetApp(): void
    {
        TestApplication::reset();
    }

    protected function app(): Application
    {
        return TestApplication::app();
    }

    protected function session(): SessionInterface
    {
        return $this->container()->get(SessionInterface::class);
    }

    protected function baseUri(): string
    {
        return 'https://example.test';
    }
}
```

## Конфигурирование request перед отправкой

Если приложению нужно обновлять `ServerRequestInterface`, `Redirector`, `Inertia`, используйте:

- `configureRequest(...)` в вашем `WebTestCase`;
- или `HttpClientConfiguratorInterface` (единый конфигуратор в контейнере).

## Auth helpers

`WebTestCase` содержит helpers для типовых auth-сценариев:

```php
$this->actingAs($user);

$this->withRole($user, 'admin');
$this->withRoles($user, ['manager', 'support']);

$response = $this->withAuthToken($token)->get('/admin');
$response = $this->withBearerToken($token)->get('/api/me');
```

`actingAs()` сначала пробует залогинить пользователя через `AuthManager` и текущий guard. Если guard не поддерживает login, helper выставляет session id и request attributes `user` / `user_id`.

## Host-aware HTTP

Для host-based areas или host-based routes используйте `withHost()`:

```php
$response = $this
    ->withHost('admin.example.test')
    ->get('/dashboard');
```

Helper меняет host в URI запроса, поэтому его видят и роутер, и Inertia area detector.
