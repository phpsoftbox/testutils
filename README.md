# PhpSoftBox TestUtils

Утилиты для тестирования пакетов и приложений на PhpSoftBox.

## Документация

Подробная документация разбита по темам в [`docs/`](docs/README.md):

1. [Установка и Bootstrap](docs/01-installation-and-bootstrap.md)
2. [Базовые TestCase и HTTP-клиент](docs/02-test-cases-and-http.md)
3. [Перезагрузка БД](docs/03-database-reloader.md)
4. [Inertia и snapshot-тестирование](docs/04-snapshots-and-inertia.md)
5. [Fixture API (подробно)](docs/05-fixtures-overview.md)
6. [Интеграция fixture в приложение](docs/06-fixtures-integration.md)

## Примеры

- [`examples/tests-bootstrap.php`](examples/tests-bootstrap.php)
- [`examples/http-client-configurator.php`](examples/http-client-configurator.php)

## Быстрый старт

```bash
composer require --dev phpsoftbox/test-utils
```

## Опциональные зависимости

Базовые утилиты пакета не требуют БД, ORM, Auth и Session. Эти компоненты подключаются
только для соответствующих helper-ов:

- `phpsoftbox/session` — `WebTestCase`, `TestHttpClient`, session/CSRF helpers;
- `phpsoftbox/auth` — `actingAs()`, `withRole()`, интеграция с guard;
- `phpsoftbox/database` — database reloader и transaction helpers;
- `phpsoftbox/orm` — entity helpers и ORM fixture relations.

Используйте:

- `ApplicationTestCase` — для интеграционных тестов без HTTP;
- `WebTestCase` — для контроллеров и HTTP-интеграции.

Для ручной перезагрузки тестовой БД:

```bash
php psb test:db:reload --mode=dump --connections=default
```

Если `--connections` не указан, будут перезагружены все подключения из `DatabaseReloaderConfig`.

Для параллельного запуска fast-набора:

```bash
php psb test:parallel --mode=transaction --processes=4 --exclude-group=db-dump
```

## Важно

- `FixtureRunner`/`FixtureContext` не завязаны на контейнер.
- `FixtureRunner` поддерживает зависимости через `DependentFixtureInterface`.
- Area/domain-специфичные фикстуры остаются в приложении (`tests/Utils/...`).
