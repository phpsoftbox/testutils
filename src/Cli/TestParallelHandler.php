<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Cli;

use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use RuntimeException;

use function array_filter;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function trim;

final readonly class TestParallelHandler implements HandlerInterface
{
    public function __construct(
        private ?ParallelTestRunnerInterface $parallelTestRunner = null,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $parallelTestRunner = $this->parallelTestRunner ?? new ProcOpenParallelTestRunner();

        $mode = $runner->request()->option('mode', 'transaction');
        $mode = is_string($mode) ? trim($mode) : 'transaction';
        if ($mode === '') {
            $mode = 'transaction';
        }

        if ($mode !== 'transaction' && $mode !== 'dump') {
            $runner->io()->writeln('Опция --mode принимает только значения: transaction или dump.', 'error');

            return Response::INVALID_INPUT;
        }

        $processes = $runner->request()->option('processes', 4);
        if (!is_int($processes)) {
            $processes = (int) $processes;
        }
        if ($processes <= 0) {
            $runner->io()->writeln('Опция --processes должна быть больше 0.', 'error');

            return Response::INVALID_INPUT;
        }

        $group = $runner->request()->option('group', '');
        $group = is_string($group) ? trim($group) : '';

        $excludeGroup = $runner->request()->option('exclude-group', 'db-dump');
        $excludeGroup = is_string($excludeGroup) ? trim($excludeGroup) : 'db-dump';

        $filter = $runner->request()->option('filter', '');
        $filter = is_string($filter) ? trim($filter) : '';

        $binary = $runner->request()->option('binary', './vendor/bin/paratest');
        $binary = is_string($binary) ? trim($binary) : './vendor/bin/paratest';
        if ($binary === '') {
            $binary = './vendor/bin/paratest';
        }

        $extraArgs = $runner->request()->option('arg', []);
        if (!is_array($extraArgs)) {
            $extraArgs = [];
        }
        $extraArgs = array_values(array_filter(
            $extraArgs,
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        ));

        $command = [
            $binary,
            '--runner',
            'WrapperRunner',
            '--processes',
            (string) $processes,
        ];

        if ($group !== '') {
            $command[] = '--group';
            $command[] = $group;
        }

        if ($excludeGroup !== '') {
            $command[] = '--exclude-group';
            $command[] = $excludeGroup;
        }

        if ($filter !== '') {
            $command[] = '--filter';
            $command[] = $filter;
        }

        foreach ($extraArgs as $arg) {
            $command[] = $arg;
        }

        $runner->io()->writeln(
            'Запуск параллельных тестов: mode=' . $mode . ', processes=' . $processes . '.',
            'info',
        );

        try {
            $exitCode = $parallelTestRunner->run($command, [
                'APP_TEST_DB_RELOAD_MODE' => $mode,
            ]);
        } catch (RuntimeException $exception) {
            $runner->io()->writeln('Не удалось запустить paratest: ' . $exception->getMessage(), 'error');

            return Response::FAILURE;
        }

        if ($exitCode !== Response::SUCCESS) {
            $runner->io()->writeln('Параллельный запуск завершился с кодом ' . $exitCode . '.', 'error');

            return Response::FAILURE;
        }

        $runner->io()->writeln('Параллельный запуск завершен успешно.', 'success');

        return Response::SUCCESS;
    }
}
