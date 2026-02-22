<?php

declare(strict_types=1);

use PhpSoftBox\Env\Environment;
use PhpSoftBox\TestUtils\TestApplication;
use PhpSoftBox\TestUtils\TestApplicationFactory;

$root = dirname(__DIR__, 2);

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
