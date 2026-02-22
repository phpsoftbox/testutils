<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Cli;

use PhpSoftBox\CliApp\Io\IoInterface;
use PhpSoftBox\CliApp\Io\ProgressInterface;
use PhpSoftBox\CliApp\Request\Request;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\TestUtils\Cli\ParallelTestRunnerInterface;
use PhpSoftBox\TestUtils\Cli\TestParallelHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_last;

#[CoversClass(TestParallelHandler::class)]
final class TestParallelHandlerTest extends TestCase
{
    /**
     * Проверяет, что обработчик параллельного запуска собирает корректную команду и возвращает успешный код.
     */
    #[Test]
    public function runBuildsCommandAndReturnsSuccess(): void
    {
        $processRunner = new FakeParallelTestRunner(exitCode: 0);

        $handler = new TestParallelHandler($processRunner);
        $runner  = new FakeRunner([
            'mode'          => 'transaction',
            'processes'     => 6,
            'group'         => '',
            'exclude-group' => 'db-dump',
            'filter'        => 'UserTest',
            'binary'        => './vendor/bin/paratest',
            'arg'           => ['--testsuite=Integration', '--stop-on-failure'],
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::SUCCESS, $result);
        self::assertSame([
            './vendor/bin/paratest',
            '--runner',
            'WrapperRunner',
            '--processes',
            '6',
            '--exclude-group',
            'db-dump',
            '--filter',
            'UserTest',
            '--testsuite=Integration',
            '--stop-on-failure',
        ], $processRunner->command);
        self::assertSame(['APP_TEST_DB_RELOAD_MODE' => 'transaction'], $processRunner->env);
    }



    /**
     * Проверяет, что при неподдерживаемом режиме обработчик возвращает ошибку валидации аргументов.
     */
    #[Test]
    public function runReturnsInvalidInputForUnsupportedMode(): void
    {
        $handler = new TestParallelHandler(new FakeParallelTestRunner(exitCode: 0));
        $runner  = new FakeRunner([
            'mode'      => 'unknown',
            'processes' => 4,
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::INVALID_INPUT, $result);
        self::assertStringContainsString('Опция --mode принимает только значения', $runner->messages()[0] ?? '');
    }



    /**
     * Проверяет, что при ненулевом коде дочернего процесса обработчик возвращает ошибку выполнения.
     */
    #[Test]
    public function runReturnsFailureWhenProcessFails(): void
    {
        $handler = new TestParallelHandler(new FakeParallelTestRunner(exitCode: 2));
        $runner  = new FakeRunner([
            'mode'      => 'transaction',
            'processes' => 4,
        ]);

        $result = $handler->run($runner);

        self::assertSame(Response::FAILURE, $result);
        self::assertStringContainsString('завершился с кодом 2', $runner->lastMessage());
    }
}

final class FakeParallelTestRunner implements ParallelTestRunnerInterface
{
    /**
     * @var list<string>
     */
    public array $command = [];

    /**
     * @var array<string, string>
     */
    public array $env = [];

    public function __construct(
        private readonly int $exitCode,
    ) {
    }

    public function run(array $command, array $env = []): int
    {
        $this->command = $command;
        $this->env     = $env;

        return $this->exitCode;
    }
}

final class FakeRunner implements RunnerInterface
{
    /**
     * @var list<string>
     */
    private array $messages = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly array $options,
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
        return new Request(params: [], options: $this->options);
    }

    public function io(): IoInterface
    {
        return new class ($this) implements IoInterface {
            public function __construct(
                private readonly FakeRunner $runner,
            ) {
            }

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
                $this->runner->appendMessage('[' . $style . '] ' . $message);
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
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function lastMessage(): string
    {
        $message = $this->messages[array_key_last($this->messages)] ?? '';

        return (string) $message;
    }

    public function appendMessage(string $message): void
    {
        $this->messages[] = $message;
    }
}
