<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\Database\Dsn\Dsn;
use RuntimeException;

use function sprintf;

final class DatabaseTransactionManager implements TransactionAdapterInterface
{
    public function __construct(
        private readonly CommandRunnerInterface $runner = new ProcessCommandRunner(),
    ) {
    }

    public function begin(DatabaseReloaderConnection $connection): void
    {
        $this->runForConnection($connection, 'BEGIN');
    }

    public function rollback(DatabaseReloaderConnection $connection): void
    {
        $this->runForConnection($connection, 'ROLLBACK');
    }

    public function supports(DatabaseReloaderConnection $connection): bool
    {
        return $this->supportsDsn($connection->test);
    }

    private function runForConnection(DatabaseReloaderConnection $connection, string $sql): void
    {
        $dsn = $connection->test;

        if (!$this->supportsDsn($dsn)) {
            throw new RuntimeException(sprintf('Transactions are not supported for driver "%s".', $dsn->driver));
        }

        $command = $this->buildCommand($dsn, $sql);
        $result  = $this->runner->run($command);

        if (!$result->isOk()) {
            throw new RuntimeException(sprintf(
                'Transaction command failed with exit code %d. %s',
                $result->exitCode,
                $result->stderr !== '' ? $result->stderr : $result->stdout,
            ));
        }
    }

    private function supportsDsn(Dsn $dsn): bool
    {
        if ($dsn->driver === 'sqlite') {
            $path = $dsn->path ?? '';

            return $path !== '' && $path !== ':memory:';
        }

        return $dsn->driver === 'postgres' || $dsn->driver === 'mariadb';
    }

    private function buildCommand(Dsn $dsn, string $sql): Command
    {
        return match ($dsn->driver) {
            'postgres' => $this->buildPostgresCommand($dsn, $sql),
            'mariadb'  => $this->buildMariaDbCommand($dsn, $sql),
            'sqlite'   => $this->buildSqliteCommand($dsn, $sql),
            default    => throw new RuntimeException(sprintf('Unsupported driver "%s".', $dsn->driver)),
        };
    }

    private function buildPostgresCommand(Dsn $dsn, string $sql): Command
    {
        $args     = ['-v', 'ON_ERROR_STOP=1', '-c', $sql];
        $database = $dsn->database ?? '';
        if ($database !== '') {
            $args[] = '-d';
            $args[] = $database;
        }

        $cmd = ['psql'];

        $host = $dsn->host ?? '';
        if ($host !== '') {
            $cmd[] = '-h';
            $cmd[] = $host;
        }

        if ($dsn->port !== null) {
            $cmd[] = '-p';
            $cmd[] = (string) $dsn->port;
        }

        if ($dsn->user !== null && $dsn->user !== '') {
            $cmd[] = '-U';
            $cmd[] = $dsn->user;
        }

        foreach ($args as $arg) {
            $cmd[] = $arg;
        }

        return new Command(
            command: $cmd,
            env: $this->buildPostgresEnv($dsn),
        );
    }

    private function buildMariaDbCommand(Dsn $dsn, string $sql): Command
    {
        $cmd  = ['mysql'];
        $host = $dsn->host ?? '';
        if ($host !== '') {
            $cmd[] = '-h';
            $cmd[] = $host;
        }

        if ($dsn->port !== null) {
            $cmd[] = '-P';
            $cmd[] = (string) $dsn->port;
        }

        if ($dsn->user !== null && $dsn->user !== '') {
            $cmd[] = '-u';
            $cmd[] = $dsn->user;
        }

        $database = $dsn->database ?? '';
        if ($database !== '') {
            $cmd[] = $database;
        }

        $cmd[] = '-e';
        $cmd[] = $sql;

        return new Command(
            command: $cmd,
            env: $this->buildMariaDbEnv($dsn),
        );
    }

    private function buildSqliteCommand(Dsn $dsn, string $sql): Command
    {
        $path = $dsn->path ?? '';
        if ($path === '') {
            throw new RuntimeException('SQLite test database path is empty.');
        }

        return new Command(command: ['sqlite3', $path, $sql]);
    }

    /**
     * @return array<string, string>
     */
    private function buildMariaDbEnv(Dsn $dsn): array
    {
        if ($dsn->password === null || $dsn->password === '') {
            return [];
        }

        return ['MYSQL_PWD' => $dsn->password];
    }

    /**
     * @return array<string, string>
     */
    private function buildPostgresEnv(Dsn $dsn): array
    {
        if ($dsn->password === null || $dsn->password === '') {
            return [];
        }

        return ['PGPASSWORD' => $dsn->password];
    }
}
