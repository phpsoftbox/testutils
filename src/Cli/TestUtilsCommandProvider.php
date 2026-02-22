<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Cli;

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;

final class TestUtilsCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'test:db:reload',
            description: 'Перезагрузить тестовую БД и bootstrap dump (если отсутствует)',
            signature: [
                new OptionDefinition(
                    name: 'mode',
                    short: 'm',
                    description: 'Режим перезагрузки: dump|transaction|reset (по умолчанию dump)',
                    required: false,
                    default: 'dump',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'connections',
                    short: 'c',
                    description: 'Список подключений через запятую (например: default,search)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
            ],
            handler: TestDatabaseReloadHandler::class,
        ));

        $registry->register(Command::define(
            name: 'test:parallel',
            description: 'Запустить параллельные тесты через paratest',
            signature: [
                new OptionDefinition(
                    name: 'mode',
                    short: 'm',
                    description: 'Переопределить режим БД для всех классов: transaction|reset|dump (по умолчанию — режим класса)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'processes',
                    short: 'p',
                    description: 'Количество параллельных процессов',
                    required: false,
                    default: 4,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'group',
                    short: 'g',
                    description: 'Группа тестов для включения',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'exclude-group',
                    short: 'x',
                    description: 'Группа тестов для исключения (по умолчанию не исключается ничего)',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'filter',
                    short: 'f',
                    description: 'PHPUnit/Paratest фильтр по имени теста',
                    required: false,
                    default: '',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'binary',
                    short: 'b',
                    description: 'Путь к бинарнику paratest',
                    required: false,
                    default: './vendor/bin/paratest',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'arg',
                    short: 'a',
                    description: 'Дополнительный аргумент для paratest (можно указывать несколько раз)',
                    required: false,
                    default: [],
                    type: 'string',
                    repeatable: true,
                ),
            ],
            handler: TestParallelHandler::class,
        ));
    }
}
