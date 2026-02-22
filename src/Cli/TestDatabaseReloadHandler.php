<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\TestUtils\Database\DatabaseReloader;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderException;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function implode;
use function is_string;
use function trim;

final readonly class TestDatabaseReloadHandler implements HandlerInterface
{
    public function __construct(
        private DatabaseReloader $reloader,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $mode = $runner->request()->option('mode', 'dump');
        $mode = is_string($mode) ? trim($mode) : 'dump';
        if ($mode === '') {
            $mode = 'dump';
        }

        $rawConnections = $runner->request()->option('connections', '');
        $rawConnections = is_string($rawConnections) ? $rawConnections : '';

        $connections = array_values(array_filter(array_map(
            static fn (string $name): string => trim($name),
            explode(',', $rawConnections),
        ), static fn (string $name): bool => $name !== ''));

        $reloader = $this->reloader;
        if ($connections !== []) {
            $reloader = $reloader->withConnections($connections);
        }

        try {
            $reloader = $reloader->withMode($mode);
            $reloader->reloadAll();
        } catch (DatabaseReloaderException $exception) {
            $runner->io()->writeln('Ошибка перезагрузки БД: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        $target = $connections === [] ? 'all' : implode(',', $connections);
        $runner->io()->writeln(
            'Тестовая БД перезагружена. mode=' . $mode . ', connections=' . $target . '.',
            'success',
        );

        return Response::SUCCESS;
    }
}
