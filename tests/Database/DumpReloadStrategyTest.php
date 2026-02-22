<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Database;

use PhpSoftBox\TestUtils\Database\Command;
use PhpSoftBox\TestUtils\Database\CommandResult;
use PhpSoftBox\TestUtils\Database\CommandRunnerInterface;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConfig;
use PhpSoftBox\TestUtils\Database\DatabaseReloaderConnection;
use PhpSoftBox\TestUtils\Database\DumpReloadStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_shift;
use function count;
use function in_array;
use function sys_get_temp_dir;
use function uniqid;

#[CoversClass(DumpReloadStrategy::class)]
#[CoversMethod(DumpReloadStrategy::class, 'reload')]
final class DumpReloadStrategyTest extends TestCase
{
    /**
     * Проверяет, что dump-стратегия MariaDB завершает активные сессии отдельными SQL-командами.
     */
    #[Test]
    public function mariadbReloadKillsSessionsWithSeparateSqlStatements(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );

        $config = new DatabaseReloaderConfig(
            [$connection],
            $this->dumpDirectory(),
            keepDumpFiles: true,
        );

        $runner = new QueueCommandRunner([
            new CommandResult(0, "101\n102\n", ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
        ]);

        $strategy = new DumpReloadStrategy($config, $runner);

        $strategy->reload($connection);

        self::assertCount(6, $runner->commands);
        self::assertTrue(in_array('-N', $runner->commands[0]->command, true));
        self::assertSame(
            "SELECT ID FROM information_schema.processlist WHERE DB = 'app_autotests' AND ID <> CONNECTION_ID();",
            $this->commandSql($runner->commands[0]),
        );
        self::assertSame('KILL 101;', $this->commandSql($runner->commands[1]));
        self::assertSame('KILL 102;', $this->commandSql($runner->commands[2]));

        $dropCreateSql = $this->commandSql($runner->commands[3]);
        self::assertNotNull($dropCreateSql);
        self::assertStringContainsString('DROP DATABASE IF EXISTS `app_autotests`;', $dropCreateSql);
        self::assertStringContainsString('CREATE DATABASE `app_autotests`;', $dropCreateSql);
        self::assertStringNotContainsString('PREPARE psb_kill_stmt', $dropCreateSql);
        $this->assertMariaDbCommandsUseSkipSsl($runner->commands);
    }



    /**
     * Проверяет, что при отсутствии активных сессий dump-стратегия MariaDB не выполняет команды KILL.
     */
    #[Test]
    public function mariadbReloadSkipsKillStatementWhenNoSessionsFound(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );

        $config = new DatabaseReloaderConfig(
            [$connection],
            $this->dumpDirectory(),
            keepDumpFiles: true,
        );

        $runner = new QueueCommandRunner([
            new CommandResult(0, "\n", ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
        ]);

        $strategy = new DumpReloadStrategy($config, $runner);

        $strategy->reload($connection);

        self::assertCount(4, $runner->commands);
        self::assertSame(
            'SET SESSION lock_wait_timeout = 5; DROP DATABASE IF EXISTS `app_autotests`; CREATE DATABASE `app_autotests`;',
            $this->commandSql($runner->commands[1]),
        );
        $this->assertMariaDbCommandsUseSkipSsl($runner->commands);
    }



    /**
     * Проверяет, что ошибки Unknown thread id при KILL игнорируются и не прерывают восстановление.
     */
    #[Test]
    public function mariadbReloadIgnoresUnknownThreadIdErrorsDuringKill(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );

        $config = new DatabaseReloaderConfig(
            [$connection],
            $this->dumpDirectory(),
            keepDumpFiles: true,
        );

        $runner = new QueueCommandRunner([
            new CommandResult(0, "101\n102\n", ''),
            new CommandResult(1, '', 'ERROR 1094 (HY000): Unknown thread id: 101'),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
            new CommandResult(0, '', ''),
        ]);

        $strategy = new DumpReloadStrategy($config, $runner);

        $strategy->reload($connection);

        self::assertCount(6, $runner->commands);
        self::assertSame('KILL 101;', $this->commandSql($runner->commands[1]));
        self::assertSame('KILL 102;', $this->commandSql($runner->commands[2]));
    }



    /**
     * Проверяет, что неигнорируемая ошибка KILL прерывает процесс восстановления с исключением.
     */
    #[Test]
    public function mariadbReloadFailsOnNonIgnorableKillError(): void
    {
        $connection = new DatabaseReloaderConnection(
            'default',
            'mariadb://user:pass@localhost:3306/app',
            'mariadb://user:pass@localhost:3306/app_autotests',
        );

        $config = new DatabaseReloaderConfig(
            [$connection],
            $this->dumpDirectory(),
            keepDumpFiles: true,
        );

        $runner = new QueueCommandRunner([
            new CommandResult(0, "101\n", ''),
            new CommandResult(1, '', 'ERROR 2013 (HY000): Lost connection to server during query'),
        ]);

        $strategy = new DumpReloadStrategy($config, $runner);

        $this->expectExceptionMessage('Failed to terminate MariaDB session 101');
        $strategy->reload($connection);
    }

    private function commandSql(Command $command): ?string
    {
        $parts = $command->command;
        $count = count($parts);

        for ($index = 0; $index < $count; $index++) {
            if (($parts[$index] ?? '') !== '-e') {
                continue;
            }

            return $parts[$index + 1] ?? null;
        }

        return null;
    }

    /**
     * @param list<Command> $commands
     */
    private function assertMariaDbCommandsUseSkipSsl(array $commands): void
    {
        foreach ($commands as $command) {
            $binary = $command->command[0] ?? '';
            if ($binary !== 'mysql' && $binary !== 'mysqldump') {
                continue;
            }

            self::assertContains('--skip-ssl', $command->command);
        }
    }

    private function dumpDirectory(): string
    {
        return sys_get_temp_dir() . '/psb-test-utils-dump-strategy-tests-' . uniqid('', true);
    }
}

final class QueueCommandRunner implements CommandRunnerInterface
{
    /**
     * @var list<Command>
     */
    public array $commands = [];

    /**
     * @param list<CommandResult> $results
     */
    public function __construct(
        private array $results,
    ) {
    }

    public function run(Command $command): CommandResult
    {
        $this->commands[] = $command;

        return array_shift($this->results) ?? new CommandResult(0, '', '');
    }
}
