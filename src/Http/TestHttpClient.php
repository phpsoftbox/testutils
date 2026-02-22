<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Http;

use Closure;
use PhpSoftBox\Application\Application;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Http\Message\Uri;
use PhpSoftBox\Session\SessionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function parse_str;
use function parse_url;
use function rtrim;
use function str_starts_with;

use const PHP_URL_QUERY;

final class TestHttpClient implements ClientInterface
{
    private ?Closure $requestConfigurator;

    public function __construct(
        private readonly Application $app,
        private readonly SessionInterface $session,
        private readonly string $baseUri,
        ?callable $requestConfigurator = null,
    ) {
        $this->requestConfigurator = $requestConfigurator !== null
            ? Closure::fromCallable($requestConfigurator)
            : null;
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $path, array $headers = []): ResponseInterface
    {
        return $this->request('GET', $path, null, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function post(string $path, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->request('POST', $path, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function put(string $path, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->request('PUT', $path, $data, $headers);
    }

    /**
     * @param array<string, mixed>|null $data
     * @param array<string, string> $headers
     */
    public function request(string $method, string $path, ?array $data, array $headers = []): ResponseInterface
    {
        $this->ensureCsrfToken();

        $uri         = rtrim($this->baseUri, '/') . $path;
        $headers     = $this->mergeHeaders($headers, $data !== null);
        $queryParams = $this->parseQueryParams($uri);

        $request = new ServerRequest($method, $uri, $headers, null, '1.1', [], [], $queryParams);

        if ($data !== null) {
            $request = $request->withParsedBody($data);
        }

        return $this->app->handle($this->configureRequest($request));
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->ensureCsrfToken();

        $uri = (string) $request->getUri();
        if ($uri === '' || str_starts_with($uri, '/')) {
            $uri = rtrim($this->baseUri, '/') . $uri;
        }

        $queryParams = $this->parseQueryParams($uri);

        if ($request instanceof ServerRequest) {
            $serverRequest = $request;
            $serverRequest = $serverRequest->withUri(new Uri($uri));
            if ($queryParams !== [] && $serverRequest->getQueryParams() === []) {
                $serverRequest = $serverRequest->withQueryParams($queryParams);
            }
        } else {
            $serverRequest = new ServerRequest($request->getMethod(), $uri, $request->getHeaders(), null, '1.1', [], [], $queryParams);
        }

        if (!$request instanceof ServerRequest) {
            $serverRequest = $serverRequest->withBody($request->getBody());
        }

        return $this->app->handle($this->configureRequest($serverRequest));
    }

    private function ensureCsrfToken(): void
    {
        $this->session->start();

        if (!$this->session->has('csrf_token')) {
            $this->session->set('csrf_token', 'test-csrf-token');
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function mergeHeaders(array $headers, bool $hasBody): array
    {
        $defaults = [
            'X-Inertia'    => 'true',
            'Accept'       => 'application/json',
            'Referer'      => rtrim($this->baseUri, '/') . '/login',
            'X-XSRF-TOKEN' => (string) $this->session->get('csrf_token', ''),
        ];

        if ($hasBody) {
            $defaults['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        return $headers + $defaults;
    }

    private function configureRequest(ServerRequest $request): ServerRequest
    {
        if ($this->requestConfigurator === null) {
            return $request;
        }

        $result = ($this->requestConfigurator)($request);

        return $result instanceof ServerRequest ? $result : $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseQueryParams(string $uri): array
    {
        $query = parse_url($uri, PHP_URL_QUERY);
        if ($query === null || $query === false || $query === '') {
            return [];
        }

        $params = [];
        parse_str($query, $params);

        return $params;
    }
}
