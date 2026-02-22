<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\Database\Dsn\Dsn;
use RuntimeException;

use function copy;
use function dirname;
use function is_dir;
use function is_file;
use function mkdir;
use function rtrim;
use function sprintf;
use function str_replace;
use function sys_get_temp_dir;
use function touch;
use function unlink;

final class DumpReloadStrategy implements ReloadStrategyInterface
{
    public function __construct(
        private readonly DatabaseReloaderConfig $config,
        private readonly CommandRunnerInterface $runner = new ProcessCommandRunner(),
    ) {
    }

    public function reload(DatabaseReloaderConnection $connection): void
    {
        $connection->assertDifferentDatabases();

        if ($connection->driver() === 'sqlite') {
            $mainPath = $connection->main->path ?? '';
            $testPath = $connection->test->path ?? '';
            if ($mainPath === '' || $testPath === '') {
                throw new DatabaseReloaderException('SQLite database path is empty.');
            }

            if (is_file($mainPath)) {
                $this->resetSqliteFile($connection->test);
                $this->copySqliteDatabase($connection->main, $connection->test);

                return;
            }

            if (is_file($testPath)) {
                return;
            }

            $this->ensureSqliteFile($connection->test);

            return;
        }

        $dumpFile   = $this->dumpFilePath($connection);
        $dumpExists = is_file($dumpFile);

        $this->dropAndCreateTestDatabase($connection);
        if (!$dumpExists) {
            $this->dumpSchema($connection, $dumpFile);
        }
        $this->loadSchema($connection, $dumpFile);

        if (!$this->config->keepDumpFiles && $this->config->dumpDirectory === '' && is_file($dumpFile)) {
            unlink($dumpFile);
        }
    }

    private function dumpFilePath(DatabaseReloaderConnection $connection): string
    {
        $baseDir = $this->config->dumpDirectory !== ''
            ? $this->config->dumpDirectory
            : sys_get_temp_dir() . '/psb-test-utils';

        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $baseDir));
        }

        $fileName = sprintf('%s-%s.sql', $connection->name, $connection->driver());

        return rtrim($baseDir, '/') . '/' . $fileName;
    }

    private function dropAndCreateTestDatabase(DatabaseReloaderConnection $connection): void
    {
        $driver = $connection->driver();

        if ($driver === 'sqlite') {
            $this->resetSqliteFile($connection->test);

            return;
        }

        if ($driver === 'mariadb') {
            $command = $this->buildMariaDbDropCreate($connection->test);
            $this->runChecked($command);

            return;
        }

        if ($driver === 'postgres') {
            $command = $this->buildPostgresDropCreate($connection->test);
            $this->runChecked($command);

            return;
        }

        throw new DatabaseReloaderException(sprintf('Unsupported driver "%s".', $driver));
    }

    private function dumpSchema(DatabaseReloaderConnection $connection, string $dumpFile): void
    {
        $driver = $connection->driver();

        if ($driver === 'sqlite') {
            $command = $this->buildSqliteDump($connection->main, $dumpFile);
            $this->runChecked($command);

            return;
        }

        if ($driver === 'mariadb') {
            $command = $this->buildMariaDbDump($connection->main, $dumpFile);
            $this->runChecked($command);

            return;
        }

        if ($driver === 'postgres') {
            $command = $this->buildPostgresDump($connection->main, $dumpFile);
            $this->runChecked($command);

            return;
        }

        throw new DatabaseReloaderException(sprintf('Unsupported driver "%s".', $driver));
    }

    private function loadSchema(DatabaseReloaderConnection $connection, string $dumpFile): void
    {
        $driver = $connection->driver();

        if ($driver === 'sqlite') {
            $command = $this->buildSqliteLoad($connection->test, $dumpFile);
            $this->runChecked($command);

            return;
        }

        if ($driver === 'mariadb') {
            $command = $this->buildMariaDbLoad($connection->test, $dumpFile);
            $this->runChecked($command);

            return;
        }

        if ($driver === 'postgres') {
            $command = $this->buildPostgresLoad($connection->test, $dumpFile);
            $this->runChecked($command);

            return;
        }

        throw new DatabaseReloaderException(sprintf('Unsupported driver "%s".', $driver));
    }

    private function buildMariaDbDropCreate(Dsn $dsn): Command
    {
        $database = $dsn->database ?? '';
        if ($database === '') {
            throw new DatabaseReloaderException('MariaDB test database name is empty.');
        }

        $sql = sprintf(
            'DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s`;',
            str_replace('`', '``', $database),
            str_replace('`', '``', $database),
        );

        return $this->buildMariaDbCommand(['-e', $sql], $dsn);
    }

    private function buildMariaDbDump(Dsn $dsn, string $dumpFile): Command
    {
        $database = $dsn->database ?? '';
        if ($database === '') {
            throw new DatabaseReloaderException('MariaDB main database name is empty.');
        }

        $cmd = $this->buildMariaDbDumpCommand($dsn, $database);

        return new Command(
            command: $cmd,
            env: $this->buildMariaDbEnv($dsn),
            stdoutFile: $dumpFile,
        );
    }

    private function buildMariaDbLoad(Dsn $dsn, string $dumpFile): Command
    {
        $database = $dsn->database ?? '';
        if ($database === '') {
            throw new DatabaseReloaderException('MariaDB test database name is empty.');
        }

        return new Command(
            command: $this->buildMariaDbCommandParts($dsn, $database),
            env: $this->buildMariaDbEnv($dsn),
            stdinFile: $dumpFile,
        );
    }

    private function buildPostgresDropCreate(Dsn $dsn): Command
    {
        $database = $dsn->database ?? '';
        if ($database === '') {
            throw new DatabaseReloaderException('Postgres test database name is empty.');
        }

        $sql = sprintf(
            'DROP DATABASE IF EXISTS "%s"; CREATE DATABASE "%s";',
            str_replace('"', '""', $database),
            str_replace('"', '""', $database),
        );

        $args = ['-d', 'postgres', '-v', 'ON_ERROR_STOP=1', '-c', $sql];

        return $this->buildPostgresCommand($args, $dsn);
    }

    private function buildPostgresDump(Dsn $dsn, string $dumpFile): Command
    {
        $database = $dsn->database ?? '';
        if ($database === '') {
            throw new DatabaseReloaderException('Postgres main database name is empty.');
        }

        $args = [
            '--schema-only',
            '--no-owner',
            '--no-privileges',
            '-d',
            $database,
        ];

        $command = $this->buildPostgresDumpCommand($args, $dsn);

        return new Command(
            command: $command,
            env: $this->buildPostgresEnv($dsn),
            stdoutFile: $dumpFile,
        );
    }

    private function buildPostgresLoad(Dsn $dsn, string $dumpFile): Command
    {
        $database = $dsn->database ?? '';
        if ($database === '') {
            throw new DatabaseReloaderException('Postgres test database name is empty.');
        }

        $args = ['-d', $database, '-v', 'ON_ERROR_STOP=1'];

        return new Command(
            command: $this->buildPostgresCommandParts($args, $dsn),
            env: $this->buildPostgresEnv($dsn),
            stdinFile: $dumpFile,
        );
    }

    private function buildSqliteDump(Dsn $dsn, string $dumpFile): Command
    {
        $path = $dsn->path ?? '';
        if ($path === '') {
            throw new DatabaseReloaderException('SQLite main database path is empty.');
        }

        return new Command(
            command: ['sqlite3', $path, '.schema'],
            stdoutFile: $dumpFile,
        );
    }

    private function buildSqliteLoad(Dsn $dsn, string $dumpFile): Command
    {
        $path = $dsn->path ?? '';
        if ($path === '') {
            throw new DatabaseReloaderException('SQLite test database path is empty.');
        }

        return new Command(
            command: ['sqlite3', $path],
            stdinFile: $dumpFile,
        );
    }

    private function resetSqliteFile(Dsn $dsn): void
    {
        $path = $dsn->path ?? '';
        if ($path === '') {
            throw new DatabaseReloaderException('SQLite test database path is empty.');
        }

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function ensureSqliteFile(Dsn $dsn): void
    {
        $path = $dsn->path ?? '';
        if ($path === '') {
            throw new DatabaseReloaderException('SQLite test database path is empty.');
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (!is_file($path) && false === @touch($path)) {
            throw new DatabaseReloaderException('Failed to create SQLite database file.');
        }
    }

    private function copySqliteDatabase(Dsn $source, Dsn $target): void
    {
        $sourcePath = $source->path ?? '';
        if ($sourcePath === '') {
            throw new DatabaseReloaderException('SQLite main database path is empty.');
        }

        $targetPath = $target->path ?? '';
        if ($targetPath === '') {
            throw new DatabaseReloaderException('SQLite test database path is empty.');
        }

        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        if (false === @copy($sourcePath, $targetPath)) {
            throw new DatabaseReloaderException('Failed to copy SQLite database file.');
        }
    }

    /**
     * @param list<string> $args
     */
    private function buildMariaDbCommand(array $args, Dsn $dsn): Command
    {
        return new Command(
            command: $this->buildMariaDbCommandParts($dsn, null, $args),
            env: $this->buildMariaDbEnv($dsn),
        );
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private function buildMariaDbCommandParts(Dsn $dsn, ?string $database = null, array $args = []): array
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

        foreach ($args as $arg) {
            $cmd[] = $arg;
        }

        if ($database !== null && $database !== '') {
            $cmd[] = $database;
        }

        return $cmd;
    }

    /**
     * @return list<string>
     */
    private function buildMariaDbDumpCommand(Dsn $dsn, string $database): array
    {
        $cmd = ['mysqldump', '--no-data', '--routines', '--events', '--triggers', '--single-transaction'];

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

        $cmd[] = $database;

        return $cmd;
    }

    /**
     * @param list<string> $args
     */
    private function buildPostgresCommand(array $args, Dsn $dsn): Command
    {
        return new Command(
            command: $this->buildPostgresCommandParts($args, $dsn),
            env: $this->buildPostgresEnv($dsn),
        );
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private function buildPostgresCommandParts(array $args, Dsn $dsn): array
    {
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

        return $cmd;
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private function buildPostgresDumpCommand(array $args, Dsn $dsn): array
    {
        $cmd = ['pg_dump'];

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

        return $cmd;
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

    private function runChecked(Command $command): void
    {
        $result = $this->runner->run($command);
        if (!$result->isOk()) {
            throw new DatabaseReloaderException(sprintf(
                'Command failed with exit code %d. %s',
                $result->exitCode,
                $result->stderr !== '' ? $result->stderr : $result->stdout,
            ));
        }
    }
}
