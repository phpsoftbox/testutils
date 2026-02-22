<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Database;

use function array_key_exists;
use function explode;
use function is_array;
use function is_string;
use function sprintf;
use function str_contains;

final readonly class DatabaseConfigSwitcher
{
    public function __construct(
        private DatabaseReloaderConfig $reloaderConfig,
    ) {
    }

    /**
     * @param array<string, mixed> $databaseConfig
     * @return array<string, mixed>
     */
    public function applyTestConfig(array $databaseConfig): array
    {
        $connections = $databaseConfig['connections'] ?? null;
        if (!is_array($connections)) {
            throw new DatabaseReloaderException('Database config must contain a connections array.');
        }

        foreach ($this->reloaderConfig->connections as $connection) {
            $connections = $this->applyConnection($connections, $connection);
        }

        $databaseConfig['connections'] = $connections;

        return $databaseConfig;
    }

    /**
     * @param array<string, mixed> $connections
     * @return array<string, mixed>
     */
    private function applyConnection(array $connections, DatabaseReloaderConnection $connection): array
    {
        $name = $connection->name;

        if ($name === 'default' && isset($connections['default']) && is_string($connections['default'])) {
            $name = $connections['default'];
        }

        if (str_contains($name, '.')) {
            [$group, $role] = explode('.', $name, 2);
            $connections    = $this->replaceRoleDsn($connections, $group, $role, $connection->testDsn);

            return $connections;
        }

        $config = $connections[$name] ?? null;
        if (!is_array($config)) {
            throw new DatabaseReloaderException(sprintf('Unknown connection "%s".', $name));
        }

        if (array_key_exists('dsn', $config)) {
            $config['dsn']      = $connection->testDsn;
            $connections[$name] = $config;

            return $connections;
        }

        if (array_key_exists('read', $config) || array_key_exists('write', $config)) {
            $connections = $this->replaceRoleDsn($connections, $name, 'read', $connection->testDsn, false);
            $connections = $this->replaceRoleDsn($connections, $name, 'write', $connection->testDsn, false);

            return $connections;
        }

        throw new DatabaseReloaderException(sprintf('Connection "%s" has no dsn configuration.', $name));
    }

    /**
     * @param array<string, mixed> $connections
     * @return array<string, mixed>
     */
    private function replaceRoleDsn(
        array $connections,
        string $group,
        string $role,
        string $dsn,
        bool $throwOnMissing = true,
    ): array {
        $groupConfig = $connections[$group] ?? null;
        if (!is_array($groupConfig)) {
            if ($throwOnMissing) {
                throw new DatabaseReloaderException(sprintf('Unknown connection group "%s".', $group));
            }

            return $connections;
        }

        $roleConfig = $groupConfig[$role] ?? null;
        if (!is_array($roleConfig)) {
            if ($throwOnMissing) {
                throw new DatabaseReloaderException(sprintf('Unknown connection "%s.%s".', $group, $role));
            }

            return $connections;
        }

        $roleConfig['dsn']   = $dsn;
        $groupConfig[$role]  = $roleConfig;
        $connections[$group] = $groupConfig;

        return $connections;
    }
}
