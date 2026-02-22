<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Http;

use Psr\Http\Message\ServerRequestInterface;

interface HttpClientConfiguratorInterface
{
    public function configure(ServerRequestInterface $request): void;
}
