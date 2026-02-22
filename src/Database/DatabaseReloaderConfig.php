<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use PhpSoftBox\Database\Dsn\DsnParser;
use PhpSoftBox\Database\Exception\ConfigurationException;

use function array_fill_keys;
use function array_key_exists;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function ltrim;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function strlen;
use function substr;
use function trim;

final readonly class DatabaseReloaderConfig
{
    /**
     * @param list<DatabaseReloaderConnection> $connections
     */
    public function __construct(
        public array $connections,
        public string $dumpDirectory,
        public bool $keepDumpFiles = false,
        public string $mode = 'dump',
    ) {
    }

    /**
     * @param list<string> $connectionNames
     */
    public function withConnections(array $connectionNames): self
    {
        if ($connectionNames === []) {
            return new self([], $this->dumpDirectory, $this->keepDumpFiles, $this->mode);
        }

        $allowed  = array_fill_keys($connectionNames, true);
        $filtered = [];

        foreach ($this->connections as $connection) {
            if (isset($allowed[$connection->name])) {
                $filtered[] = $connection;
            }
        }

        return new self($filtered, $this->dumpDirectory, $this->keepDumpFiles, $this->mode);
    }

    public function withMode(string $mode): self
    {
        return new self($this->connections, $this->dumpDirectory, $this->keepDumpFiles, $mode);
    }

    /**
     * @param array<string, mixed> $databaseConfig
     * @param list<string> $connectionNames
     * @param array<string, string> $testDatabaseNames
     */
    public static function fromDatabaseConfig(
        array $databaseConfig,
        array $connectionNames = ['default'],
        string $testSuffix = '_autotests',
        array $testDatabaseNames = [],
        string $dumpDirectory = '',
        bool $keepDumpFiles = false,
        string $mode = 'dump',
    ): self {
        $connectionsConfig = $databaseConfig['connections'] ?? null;
        if (!is_array($connectionsConfig)) {
            throw new ConfigurationException('Database config must contain a connections array.');
        }

        $resolved = [];
        foreach ($connectionNames as $name) {
            $dsn                 = self::resolveConnectionDsn($connectionsConfig, $name);
            [$mainDsn, $testDsn] = self::resolveMainAndTestDsn(
                $dsn,
                $testDatabaseNames[$name] ?? null,
                $testSuffix,
            );

            $resolved[] = new DatabaseReloaderConnection($name, $mainDsn, $testDsn);
        }

        return new self(
            connections: $resolved,
            dumpDirectory: $dumpDirectory,
            keepDumpFiles: $keepDumpFiles,
            mode: $mode,
        );
    }

    /**
     * @param array<string, mixed> $connections
     */
    private static function resolveConnectionDsn(array $connections, string $name): string
    {
        if ($name === 'default' && isset($connections['default'])) {
            $name = (string) $connections['default'];
        }

        $config = $connections[$name] ?? null;
        if (is_array($config)) {
            if (array_key_exists('dsn', $config)) {
                $dsn = $config['dsn'] ?? null;
                if (!is_string($dsn) || $dsn === '') {
                    throw new ConfigurationException(sprintf('Connection "%s" has invalid dsn.', $name));
                }

                return $dsn;
            }

            if (array_key_exists('write', $config) || array_key_exists('read', $config)) {
                $role       = array_key_exists('write', $config) ? 'write' : 'read';
                $roleConfig = $config[$role] ?? null;
                if (!is_array($roleConfig)) {
                    throw new ConfigurationException(sprintf('Unknown connection "%s".', $name));
                }

                $dsn = $roleConfig['dsn'] ?? null;
                if (!is_string($dsn) || $dsn === '') {
                    throw new ConfigurationException(sprintf('Connection "%s" has invalid dsn.', $name));
                }

                return $dsn;
            }
        }

        if (str_contains($name, '.')) {
            [$group, $role] = explode('.', $name, 2);
            if ($group === 'default' && isset($connections['default']) && is_string($connections['default'])) {
                $group = $connections['default'];
            }

            $groupConfig = $connections[$group] ?? null;
            if (!is_array($groupConfig)) {
                throw new ConfigurationException(sprintf('Unknown connection group "%s".', $group));
            }

            $roleConfig = $groupConfig[$role] ?? null;
            if (!is_array($roleConfig)) {
                throw new ConfigurationException(sprintf('Unknown connection "%s".', $name));
            }

            $dsn = $roleConfig['dsn'] ?? null;
            if (!is_string($dsn) || $dsn === '') {
                throw new ConfigurationException(sprintf('Connection "%s" has invalid dsn.', $name));
            }

            return $dsn;
        }

        throw new ConfigurationException(sprintf('Unknown connection "%s".', $name));
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function resolveMainAndTestDsn(string $dsn, ?string $forcedDatabase, string $suffix): array
    {
        $parser = new DsnParser();

        $parsed = $parser->parse($dsn);
        $suffix = trim($suffix);

        if ($parsed->driver === 'sqlite') {
            $path = $parsed->path ?? '';
            if ($path === '') {
                throw new ConfigurationException('SQLite DSN must contain a path.');
            }

            if (is_string($forcedDatabase) && $forcedDatabase !== '') {
                return [$dsn, self::formatSqliteDsn($forcedDatabase)];
            }

            if ($suffix === '') {
                throw new ConfigurationException(
                    'Test database suffix must not be empty when test database name is not explicitly provided.',
                );
            }

            if ($suffix !== '' && str_ends_with($path, $suffix)) {
                $basePath = substr($path, 0, strlen($path) - strlen($suffix));
                if ($basePath !== '') {
                    return [self::formatSqliteDsn($basePath), self::formatSqliteDsn($path)];
                }
            }

            $testPath = $path . $suffix;

            return [$dsn, self::formatSqliteDsn($testPath)];
        }

        $database = $parsed->database ?? '';
        if ($database === '') {
            throw new ConfigurationException('Database name is empty.');
        }

        if (is_string($forcedDatabase) && $forcedDatabase !== '') {
            return [
                $dsn,
                self::formatNetworkDsn(
                    driver: $parsed->driver,
                    host: $parsed->host ?? '',
                    port: $parsed->port,
                    database: $forcedDatabase,
                    user: $parsed->user,
                    password: $parsed->password,
                    params: $parsed->params,
                ),
            ];
        }

        if ($suffix === '') {
            throw new ConfigurationException(
                'Test database suffix must not be empty when test database name is not explicitly provided.',
            );
        }

        if ($suffix !== '' && str_ends_with($database, $suffix)) {
            $baseDatabase = substr($database, 0, strlen($database) - strlen($suffix));
            if ($baseDatabase !== '') {
                return [
                    self::formatNetworkDsn(
                        driver: $parsed->driver,
                        host: $parsed->host ?? '',
                        port: $parsed->port,
                        database: $baseDatabase,
                        user: $parsed->user,
                        password: $parsed->password,
                        params: $parsed->params,
                    ),
                    self::formatNetworkDsn(
                        driver: $parsed->driver,
                        host: $parsed->host ?? '',
                        port: $parsed->port,
                        database: $database,
                        user: $parsed->user,
                        password: $parsed->password,
                        params: $parsed->params,
                    ),
                ];
            }
        }

        $testDatabase = $database . $suffix;

        return [
            $dsn,
            self::formatNetworkDsn(
                driver: $parsed->driver,
                host: $parsed->host ?? '',
                port: $parsed->port,
                database: $testDatabase,
                user: $parsed->user,
                password: $parsed->password,
                params: $parsed->params,
            ),
        ];
    }

    /**
     * @param array<string, string> $params
     */
    private static function formatNetworkDsn(
        string $driver,
        string $host,
        ?int $port,
        string $database,
        ?string $user,
        ?string $password,
        array $params,
    ): string {
        $auth = '';
        if ($user !== null && $user !== '') {
            $auth = rawurlencode($user);
            if ($password !== null && $password !== '') {
                $auth .= ':' . rawurlencode($password);
            }
            $auth .= '@';
        }

        $portPart = $port !== null ? ':' . $port : '';
        $query    = self::buildQuery($params);

        return sprintf('%s://%s%s%s/%s%s', $driver, $auth, $host, $portPart, $database, $query);
    }

    private static function formatSqliteDsn(string $path): string
    {
        if ($path === ':memory:') {
            return 'sqlite:///:memory:';
        }

        if ($path !== '' && $path[0] === '/') {
            return 'sqlite:////' . ltrim($path, '/');
        }

        return 'sqlite:///' . ltrim($path, '/');
    }

    /**
     * @param array<string, string> $params
     */
    private static function buildQuery(array $params): string
    {
        if ($params === []) {
            return '';
        }

        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return '?' . implode('&', $pairs);
    }
}
