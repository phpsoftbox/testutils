# Inertia и Snapshot-тестирование

## Inertia helpers

Подключите `InertiaTestTrait` в интеграционном тесте:

```php
use PhpSoftBox\TestUtils\Traits\InertiaTestTrait;

final class UsersControllerTest extends IntegrationTestCase
{
    use InertiaTestTrait;
}
```

Доступные проверки:

- `assertInertiaComponent($response, 'Dispatcher/Users/Index')`
- `assertInertiaArea($response, 'admin')`
- `assertInertiaProp($response, 'app.area', 'admin')`
- `inertiaPayload($response)` для точечных assert
- `assertInertiaSnapshot(...)` для JSON-снимка ответа

Для host-based areas используйте `WebTestCase::withHost()` вместе с area assertion:

```php
$response = $this
    ->withHost('admin.example.test')
    ->get('/dashboard');

$this->assertInertiaComponent($response, 'Admin/Dashboard');
$this->assertInertiaArea($response, 'admin');
```

## JSON snapshots

Можно использовать напрямую `JsonSnapshotAssert`:

```php
use PhpSoftBox\TestUtils\Snapshot\JsonSnapshotAssert;
use PhpSoftBox\TestUtils\Snapshot\SnapshotConfig;

$config = SnapshotConfig::forTestClass(
    basePath: __DIR__ . '/../../local/tests/response',
    testClass: static::class,
)->withExcludedKeys(['meta.timestamp']);

(new JsonSnapshotAssert())->assertMatchesSnapshot(
    actual: $payload,
    snapshotName: 'users-index',
    config: $config,
);
```

## Практика

- храните snapshot рядом с тестовым артефактом проекта (`local/tests/response`);
- исключайте нестабильные ключи (`id`, `timestamp`, random токены);
- не снимайте слишком большие payload без причины.
