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

Режимы БД: `transaction` (по умолчанию), `reset` — очистка данных без пересоздания схемы для тестов, где код
коммитит в отдельных соединениях, `dump` — пересоздание БД для тестов с DDL. Подробнее —
[docs/03-database-reloader.md](docs/03-database-reloader.md).

Для параллельного запуска:

```bash
php psb test:parallel --processes=4
```

## Важно

- `FixtureRunner`/`FixtureContext` не завязаны на контейнер.
- `FixtureRunner` поддерживает зависимости через `DependentFixtureInterface`.
- Area/domain-специфичные фикстуры остаются в приложении (`tests/Utils/...`).

## Тесты пакета

Интеграционные тесты режима `reset` работают с настоящими MariaDB, MySQL и PostgreSQL из docker-compose фреймворка:
`make select-testutils` поднимает `php-cli`, `mariadb` и `postgres`; MySQL — профиль `mysql`
(`docker compose --profile mysql up -d mysql`), затем `make php-test`. Если сервер недоступен, тесты падают
(а не пропускаются). DSN можно переопределить через `TEST_UTILS_MARIADB_DSN`, `TEST_UTILS_MYSQL_DSN` и
`TEST_UTILS_POSTGRES_DSN`.
