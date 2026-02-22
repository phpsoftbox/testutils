<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Cli;

use PhpSoftBox\CliApp\Io\IoInterface;
use PhpSoftBox\CliApp\Io\ProgressInterface;
use PhpSoftBox\CliApp\Request\Request;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\TestUtils\Cli\TestDatabaseReloadHandler;
use PhpSoftBox\TestUtils\Database\DatabaseReloader;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Tests\Database\FakeCommandRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_dir;
use function mkdir;
use function sys_get_temp_dir;

#[CoversClass(TestDatabaseReloadHandler::class)]
final class TestDatabaseReloadHandlerTest extends TestCase
{
    /**
     * Проверяет, что обработчик команды перезагрузки БД запускает reloader и завершает выполнение с успешным кодом.
     */
    #[Test]
    public function runReloadsDatabaseAndReturnsSuccess(): void
    {
        $tmpDir = sys_get_temp_dir() . '/psb-test-utils-cli-tests';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );

        $reloader = new DatabaseReloader(
            new DatabaseReloaderConfig([$connection], $tmpDir, keepDumpFiles: true, mode: 'dump'),
            new FakeCommandRunner(),
        );

        $handler = new TestDatabaseReloadHandler($reloader);
        $io      = new class () implements IoInterface {
            /** @var list<string> */
            public array $messages = [];

            public function ask(string $question, ?string $default = null): string
            {
                return $default ?? '';
            }

            public function confirm(string $question, bool $default = false): bool
            {
                return $default;
            }

            public function secret(string $question): string
            {
                return '';
            }

            public function writeln(string $message, string $style = 'info'): void
            {
                $this->messages[] = '[' . $style . '] ' . $message;
            }

            public function table(array $headers, array $rows): void
            {
            }

            public function progress(int $max): ProgressInterface
            {
                return new class () implements ProgressInterface {
                    public function advance(int $step = 1): void
                    {
                    }

                    public function finish(): void
                    {
                    }
                };
            }
        };

        $runner = new class ($io) implements RunnerInterface {
            public function __construct(
                private readonly IoInterface $io,
            ) {
            }

            public function run(string $command, array $argv): Response
            {
                return new Response(Response::SUCCESS);
            }

            public function runSubCommand(string $command, array $argv): Response
            {
                return new Response(Response::SUCCESS);
            }

            public function request(): Request
            {
                return new Request(params: [], options: [
                    'mode'        => 'dump',
                    'connections' => '',
                ]);
            }

            public function io(): IoInterface
            {
                return $this->io;
            }
        };

        $result = $handler->run($runner);

        $this->assertSame(Response::SUCCESS, $result);
        $this->assertNotEmpty($io->messages);
    }
}
