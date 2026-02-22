<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils;

use PhpSoftBox\Application\Application;
use PhpSoftBox\Session\SessionInterface;
use PhpSoftBox\TestUtils\Http\HttpClientConfiguratorInterface;
use PhpSoftBox\TestUtils\Http\TestHttpClient;
use Psr\Http\Message\ServerRequestInterface;

use function is_object;
use function method_exists;

abstract class WebTestCase extends ApplicationTestCase
{
    protected function httpClient(): TestHttpClient
    {
        $configurator = $this->resolveHttpClientConfigurator();

        return new TestHttpClient(
            app: $this->app(),
            session: $this->session(),
            baseUri: $this->baseUri(),
            requestConfigurator: $configurator !== null
                ? [$configurator, 'configure']
                : function (ServerRequestInterface $request): void {
                    $this->configureRequest($request);
                },
        );
    }

    protected function configureRequest(ServerRequestInterface $request): void
    {
    }

    protected function httpClientConfigurator(): ?HttpClientConfiguratorInterface
    {
        return null;
    }

    private function resolveHttpClientConfigurator(): ?HttpClientConfiguratorInterface
    {
        $configurator = $this->httpClientConfigurator();
        if ($configurator !== null) {
            return $configurator;
        }

        if (!method_exists($this, 'container')) {
            return null;
        }

        /** @var callable(): mixed $containerAccessor */
        $containerAccessor = [$this, 'container'];
        $container         = $containerAccessor();

        if (is_object($container) && method_exists($container, 'has') && method_exists($container, 'get')) {
            if ($container->has(HttpClientConfiguratorInterface::class)) {
                $instance = $container->get(HttpClientConfiguratorInterface::class);

                return $instance instanceof HttpClientConfiguratorInterface ? $instance : null;
            }
        }

        return null;
    }

    abstract protected function app(): Application;

    abstract protected function session(): SessionInterface;

    abstract protected function baseUri(): string;
}
