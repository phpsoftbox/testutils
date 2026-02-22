<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils;

use Closure;
use PhpSoftBox\Config\Config;
use PhpSoftBox\Config\ConfigFactory;
use PhpSoftBox\Config\Provider\PhpFileDataProvider;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function is_callable;
use function is_file;
use function is_object;
use function sprintf;

final readonly class TestApplicationFactory
{
    /**
     * @param callable():ContainerInterface|null $containerFactory
     * @param callable(ContainerInterface):object|null $appFactory
     */
    public function __construct(
        private string $basePath,
        private string $env = 'testing',
        private ?string $containerPath = null,
        private ?string $appPath = null,
        private ?Closure $containerFactory = null,
        private ?Closure $appFactory = null,
        private ?string $configPath = null,
        private ?string $localConfigPath = null,
    ) {
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function env(): string
    {
        return $this->env;
    }

    public function createContainer(): ContainerInterface
    {
        if ($this->containerFactory !== null) {
            $container = ($this->containerFactory)();
            if ($container instanceof ContainerInterface) {
                return $container;
            }
        }

        $path = $this->containerPath ?? $this->basePath . '/config/container.php';
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Test container config not found: %s', $path));
        }

        $container = require $path;
        if (!$container instanceof ContainerInterface) {
            throw new RuntimeException('Container factory did not return a ContainerInterface.');
        }

        return $container;
    }

    public function createApplication(ContainerInterface $container): object
    {
        if ($this->appFactory !== null) {
            $app = ($this->appFactory)($container);
            if (is_object($app)) {
                return $app;
            }
        }

        $path = $this->appPath ?? $this->basePath . '/config/app.php';
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Test app config not found: %s', $path));
        }

        $factory = require $path;
        if (!is_callable($factory)) {
            throw new RuntimeException('App factory is not callable.');
        }

        $app = $factory($container);
        if (!is_object($app)) {
            throw new RuntimeException('App factory did not return an object.');
        }

        return $app;
    }

    public function createConfig(bool $readOnly = false): Config
    {
        $configDir      = $this->configPath ?? ($this->basePath . '/config/app');
        $localConfigDir = $this->localConfigPath ?? ($this->basePath . '/local/config/' . $this->env . '/app');

        $factory = new ConfigFactory(
            baseDir: $this->basePath,
            env: $this->env,
            providers: [
                new PhpFileDataProvider($configDir . '/*.php'),
                new PhpFileDataProvider($configDir . '/' . $this->env . '/*.php'),
                new PhpFileDataProvider($localConfigDir . '/*.php'),
            ],
        );

        return $factory->create()->withReadOnly($readOnly);
    }

    /**
     * @return array<string, mixed>
     */
    public function createDatabaseConfig(): array
    {
        $configDir = $this->configPath ?? ($this->basePath . '/config/app');

        $factory = new ConfigFactory(
            baseDir: $this->basePath,
            env: $this->env,
            providers: [
                new PhpFileDataProvider($configDir . '/*.php'),
                new PhpFileDataProvider($configDir . '/' . $this->env . '/*.php'),
            ],
        );

        $config = $factory->create();

        return (array) $config->get('database', []);
    }
}
