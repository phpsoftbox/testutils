# Перезагрузка БД

`DatabaseReloader` управляет изоляцией тестовой БД.

## Режимы

### `dump`

- пересоздает тестовую БД;
- загружает схему из дампа;
- подходит для сложных миграционных кейсов;
- медленнее, но максимально изолированно.

### `transaction`

- не пересоздает БД;
- оборачивает тесты в транзакции (rollback/begin);
- если dump отсутствует, автоматически делает bootstrap схемы из main БД;
- быстрее для CI и локальной разработки.

Поддерживаемые значения режима формализованы в `DatabaseReloaderModesEnum`:

- `dump`
- `transaction`

Любое другое значение считается ошибкой конфигурации.

## Рекомендация

- по умолчанию использовать `transaction`;
- переключать на `dump` только для отдельных тестов/классов, где это необходимо.

## Конфиг

```php
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;

$config = DatabaseReloaderConfig::fromDatabaseConfig(
    databaseConfig: $databaseConfig,
    connectionNames: ['default'],
    testSuffix: '_autotests',
    dumpDirectory: __DIR__ . '/../../local/tests/dumps',
    mode: 'transaction',
);
```

Рекомендуемая схема для всех проектов:

- в `.env.testing` не переопределяйте `APP_DB_MAIN_DSN` на тестовую БД;
- держите `APP_DB_MAIN_DSN` как main (`app`, `project`, и т.д.);
- тестовая БД формируется через `testSuffix` (`_autotests` по умолчанию).

`testSuffix` должен быть непустым, если вы не задаете явное имя тестовой БД через `testDatabaseNames`.

## Подмена DSN на тестовые

Для тестового окружения используйте `DatabaseConfigSwitcher`.

```php
use PhpSoftBox\TestUtils\Database\DatabaseConfigSwitcher;

$switcher = new DatabaseConfigSwitcher($config);
$testConfig = $switcher->applyTestConfig($databaseConfig);
```

## CLI-команда

Для ручной перезагрузки тестовой БД доступна команда:

```bash
php psb test:db:reload --mode=dump --connections=default
```

Параметры:

- `--mode` — режим перезагрузки (`dump` или `transaction`), по умолчанию `dump`.
- `--connections` — список подключений через запятую (`default,search`).

Если `--connections` не указан, команда перезагружает **все подключения**, которые описаны в `DatabaseReloaderConfig`.

Примеры:

```bash
# Перезагрузить все подключения в dump-режиме
php psb test:db:reload

# Перезагрузить только default и search в transaction-режиме
php psb test:db:reload --mode=transaction --connections=default,search
```

## Параллельный запуск тестов

Для запуска `paratest` доступна команда:

```bash
php psb test:parallel --mode=transaction --processes=4 --exclude-group=db-dump
```

Ключевые параметры:

- `--mode` — значение для `APP_TEST_DB_RELOAD_MODE` (`transaction` или `dump`).
- `--processes` — количество параллельных процессов.
- `--group` — запуск только указанной группы.
- `--exclude-group` — исключение группы (по умолчанию `db-dump`).
- `--filter` — фильтр тестов.
- `--binary` — путь к бинарнику paratest (по умолчанию `./vendor/bin/paratest`).
- `--arg` — дополнительный аргумент для paratest (можно указывать несколько раз).

Примеры:

```bash
# Fast-набор (исключая db-dump)
php psb test:parallel --mode=transaction --processes=4 --exclude-group=db-dump

# Запуск только db-dump группы
php psb test:parallel --mode=dump --group=db-dump --exclude-group=

# Передача дополнительных аргументов в paratest
php psb test:parallel --arg=--testsuite=Integration --arg=--stop-on-failure
```
