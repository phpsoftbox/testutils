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
    payload: $payload,
    snapshotName: 'users-index',
    config: $config,
);
```

Поведение задается флагами `SnapshotConfig` (оба по умолчанию `true`):

- `withAutoCreate(bool)` — snapshot не найден: при `true` файл создается, тест помечается пропущенным
  (`A new snapshot was created.`); при `false` тест падает с `Snapshot '<имя>' not found.`, файл не создается;
- `withAutoUpdateOnMismatch(bool)` — snapshot не совпал: тест падает всегда; при `true` файл перезаписывается
  актуальным payload и сообщение дополняется `Snapshot was updated.`, при `false` файл не меняется.

В CI отключайте оба флага, чтобы отсутствующий или устаревший snapshot не подменялся молча.

## Практика

- храните snapshot рядом с тестовым артефактом проекта (`local/tests/response`);
- исключайте нестабильные ключи (`id`, `timestamp`, random токены);
- не снимайте слишком большие payload без причины.
