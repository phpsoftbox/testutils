<?php

declare(strict_types=1);

use PhpSoftBox\Http\Message\Redirector;
use PhpSoftBox\Router\Router;
use PhpSoftBox\Session\SessionInterface;
use PhpSoftBox\TestUtils\Http\HttpClientConfiguratorInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AppHttpClientConfigurator implements HttpClientConfiguratorInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function configure(ServerRequestInterface $request): void
    {
        if (!method_exists($this->container, 'set')) {
            return;
        }

        $this->container->set(ServerRequestInterface::class, $request);

        if (!$this->container->has(ResponseFactoryInterface::class) || !$this->container->has(SessionInterface::class)) {
            return;
        }

        $redirector = new Redirector(
            $this->container->get(ResponseFactoryInterface::class),
            $this->container->get(SessionInterface::class),
            $request,
            $this->container->has(Router::class) ? $this->container->get(Router::class) : null,
        );

        $this->container->set(Redirector::class, $redirector);
    }
}

// Регистрация в контейнере:
// $container->set(HttpClientConfiguratorInterface::class, new AppHttpClientConfigurator($container));
