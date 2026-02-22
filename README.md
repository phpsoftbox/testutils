# PhpSoftBox TestUtils

Утилиты для тестирования пакетов и приложений на PhpSoftBox.

## Быстрый старт для приложений

1) В `tests/bootstrap.php` настройте окружение и тестовое приложение:

```php
use PhpSoftBox\Env\Environment;
use PhpSoftBox\TestUtils\TestApplication;
use PhpSoftBox\TestUtils\TestApplicationFactory;

$root = dirname(__DIR__);

$variables = Environment::create($root . '/config/env')
    ->setEnvironment('testing')
    ->setPrefix('APP_')
    ->includeGlobals(true)
    ->overload();

$variables->toGlobals(true);
$variables->toPutEnv(true);

$factory = new TestApplicationFactory(
    basePath: $root,
    env: 'testing',
);

TestApplication::configure($factory);
TestApplication::setFrozenTime('2024-01-01 00:00:00');
```

2) Используйте базовые тест-кейсы:

- `ApplicationTestCase` — без HTTP клиента (подходит для консольных/сервисных тестов)
- `WebTestCase` — с `TestHttpClient` (интеграционные тесты контроллеров)

Пример WebTestCase:

```php
use PhpSoftBox\TestUtils\WebTestCase;
use PhpSoftBox\TestUtils\TestApplication;

final class UsersTest extends WebTestCase
{
    protected function container(): \Psr\Container\ContainerInterface
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

    protected function app(): \PhpSoftBox\Application\Application
    {
        return TestApplication::app();
    }

    protected function session(): \PhpSoftBox\Session\SessionInterface
    {
        return TestApplication::container()->get(\PhpSoftBox\Session\SessionInterface::class);
    }

    protected function baseUri(): string
    {
        return 'https://example.test';
    }
}
```

Переключение режима БД на `dump` для дебага:

```php
$this->databaseReloadMode = 'dump';
```

Альтернатива для локального переключения релоадера (если вы хотите подменить сервис в контейнере):

```php
$reloader = $this->container()->get(\PhpSoftBox\TestUtils\Database\DatabaseReloader::class);
$this->overrideContainer(
    \PhpSoftBox\TestUtils\Database\DatabaseReloader::class,
    $reloader->withMode('dump'),
);
```

## Конфигурация TestHttpClient

Если нужно централизованно настраивать запросы (например, прокидывать Request в контейнер, обновлять Redirector),
зарегистрируйте конфигуратор:

```php
use PhpSoftBox\TestUtils\Http\HttpClientConfiguratorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AppHttpClientConfigurator implements HttpClientConfiguratorInterface
{
    public function configure(ServerRequestInterface $request): void
    {
        // кастомная логика
    }
}
```

Далее можно положить его в контейнер и `WebTestCase` автоматически подхватит:

```php
$container->set(
    HttpClientConfiguratorInterface::class,
    new AppHttpClientConfigurator(),
);
```

См. пример в `examples/http-client-configurator.php`.

3) Inertia helpers (опционально):

```php
use PhpSoftBox\TestUtils\Traits\InertiaTestTrait;

final class UsersTest extends WebTestCase
{
    use InertiaTestTrait;

    public function testIndexRenders(): void
    {
        $response = $this->httpClient()->get('/users');

        $this->assertInertiaComponent($response, 'Admin/Users/Index');
        $this->assertInertiaSnapshot($response, 'users-index', __DIR__ . '/../../local/tests/snapshots');
    }
}
```

## Шаблоны

- `examples/tests-bootstrap.php` — базовый `tests/bootstrap.php` для нового проекта.
- `examples/http-client-configurator.php` — пример конфигуратора `TestHttpClient`.

## Best practices

- По умолчанию используйте режим `transaction` — он быстрее и стабильнее в CI.
- Переключайте конкретный тест/класс на `dump`, если нужно:
  - воспроизвести проблему с миграциями,
  - проверить актуальность схемы,
  - изолировать тест от «грязного» состояния.
- Если в тесте нужен другой режим или список подключений — переключайте только для этого класса, а не глобально.
- В больших наборах тестов старайтесь не смешивать `dump` и `transaction` в одном процессе (меньше сюрпризов).

## JSON снимки

Пример использования с собственным базовым путём:

```php
use PhpSoftBox\TestUtils\Snapshot\JsonSnapshotAssert;
use PhpSoftBox\TestUtils\Snapshot\SnapshotConfig;

$config = SnapshotConfig::forTestClass(
    basePath: __DIR__ . '/../../local/tests/responses',
    testClass: static::class,
)->withExcludedKeys(['meta.timestamp']);

$assert = new JsonSnapshotAssert();
$assert->assertMatchesSnapshot($payload, 'login-success', $config);
```


## Перезагрузка базы

DatabaseReloader пересоздаёт тестовые базы, копируя схему из основной базы.
Использует нативные CLI инструменты (`mysqldump`/`mysql`, `pg_dump`/`psql`, `sqlite3`),
поэтому ожидает их наличие в окружении тестов.

Доступны два режима: `dump` (по умолчанию) и `transaction`.

- `dump`: пересоздаёт базу, затем грузит схему из дампа.
  Если указан `dumpDirectory`, дампы кэшируются по имени подключения/драйвера.
  Повторный прогон использует готовый файл. Для обновления схемы удалите файл дампа.
- `transaction`: не пересоздаёт базу. Вместо этого выполняет `ROLLBACK` и `BEGIN`
  на тестовой базе через переданный адаптер транзакций.

```php
use PhpSoftBox\TestUtils\Database\DatabaseReloader;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseTransactionManager;

$config = DatabaseReloaderConfig::fromDatabaseConfig(
    databaseConfig: $databaseConfig,
    connectionNames: ['default', 'analytics'],
    testSuffix: '_autotests',
    dumpDirectory: __DIR__ . '/../../local/tests/dumps',
    mode: 'dump',
);

$reloader = new DatabaseReloader($config);
$reloader->reloadAll();

$txConfig = DatabaseReloaderConfig::fromDatabaseConfig(
    databaseConfig: $databaseConfig,
    connectionNames: ['default'],
    testSuffix: '_autotests',
    mode: 'transaction',
);

$txReloader = new DatabaseReloader($txConfig, transactionAdapter: new DatabaseTransactionManager());
$txReloader->reloadAll();
```


## Подмена конфигурации БД

Используйте DatabaseConfigSwitcher, чтобы заменить DSN в конфиге приложения на тестовые DSN.

```php
use PhpSoftBox\TestUtils\Database\DatabaseConfigSwitcher;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;

$reloaderConfig = DatabaseReloaderConfig::fromDatabaseConfig($databaseConfig, ['default']);
$switcher = new DatabaseConfigSwitcher($reloaderConfig);

$testDatabaseConfig = $switcher->applyTestConfig($databaseConfig);
```


## Заморозка времени

Используйте `Clock::freeze`, чтобы фиксировать время в тестах.

```php
use PhpSoftBox\Clock\Clock;

Clock::freeze(new \DateTimeImmutable('2024-01-02 03:04:05'));

$now = Clock::now();
Clock::reset();
```


## Тестовый HTTP-клиент

Используйте TestHttpClient, чтобы отправлять запросы в приложение без реального HTTP-транспорта:

```php
use PhpSoftBox\TestUtils\Http\TestHttpClient;

$client = new TestHttpClient($app, $session, 'https://example.test');
$response = $client->post('/login', ['email' => 'demo@example.com']);
```
