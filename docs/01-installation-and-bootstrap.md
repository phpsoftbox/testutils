# Установка и Bootstrap

## 1. Подключение пакета

```bash
composer require --dev phpsoftbox/test-utils
```

## 2. Базовый `tests/bootstrap.php`

```php
<?php

declare(strict_types=1);

use PhpSoftBox\Env\Environment;
use PhpSoftBox\Application\Contracts\EnvironmentEnumInterface;
use PhpSoftBox\TestUtils\TestApplication;
use PhpSoftBox\TestUtils\TestApplicationFactory;

enum TestEnvironment: string implements EnvironmentEnumInterface
{
    case TESTING = 'testing';
}

$root = dirname(__DIR__);

$variables = Environment::create($root . '/config/env')
    ->setEnvironment('testing')
    ->setPrefix('APP_')
    ->includeGlobals(true)
    ->overload();

$variables->toGlobals(true);
$variables->toPutEnv(true);

TestApplication::configure(
    new TestApplicationFactory(
        basePath: $root,
        environment: TestEnvironment::TESTING,
    ),
);

TestApplication::setFrozenTime('2024-01-01 00:00:00');
```

## 3. Что делает `TestApplication`

- поднимает контейнер и приложение один раз;
- позволяет сбрасывать app-state между тестами (`reset`);
- хранит test-config/test-db-config;
- умеет фиксировать время (`setFrozenTime` + `applyFrozenTime`).

## 4. Где хранить test-артефакты

Рекомендуемый layout:

- `local/tests/coverage` — coverage;
- `local/tests/response` или `local/tests/snapshots` — снапшоты ответов;
- `local/tests/tmp` — временные файлы тестов.
