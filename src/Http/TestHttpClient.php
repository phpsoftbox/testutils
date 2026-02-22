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
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function array_merge;
use function http_build_query;
use function is_array;
use function is_int;
use function is_string;
use function parse_str;
use function parse_url;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;

use const PHP_QUERY_RFC3986;
use const PHP_URL_QUERY;

final class TestHttpClient implements ClientInterface
{
    private ?Closure $requestConfigurator;
    /** @var array<string, string> */
    private array $defaultHeaders;
    /** @var array<string, mixed> */
    private array $cookies;
    /** @var array<string, mixed> */
    private array $serverParams;
    /** @var array<string, mixed> */
    private array $attributes;

    /**
     * @param array<string, string> $defaultHeaders
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $serverParams
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly Application $app,
        private readonly SessionInterface $session,
        private readonly string $baseUri,
        ?callable $requestConfigurator = null,
        array $defaultHeaders = [],
        array $cookies = [],
        array $serverParams = [],
        array $attributes = [],
        private readonly ?string $host = null,
    ) {
        $this->requestConfigurator = $requestConfigurator !== null
            ? Closure::fromCallable($requestConfigurator)
            : null;
        $this->defaultHeaders = $defaultHeaders;
        $this->cookies        = $cookies;
        $this->serverParams   = $serverParams;
        $this->attributes     = $attributes;
    }

    public function withBearerToken(string $token, string $headerName = 'Authorization'): self
    {
        return $this->withHeader($headerName, 'Bearer ' . $token);
    }

    public function withAuthToken(string $token, string $cookieName = 'auth_token'): self
    {
        return $this->withCookie($cookieName, $token);
    }

    public function withHost(string $host): self
    {
        $host = trim($host);

        $clone = clone $this;
        if ($host === '') {
            $clone->serverParams = $this->withoutArrayKey($clone->serverParams, 'HTTP_HOST');

            return new self(
                app: $clone->app,
                session: $clone->session,
                baseUri: $clone->baseUri,
                requestConfigurator: $clone->requestConfigurator,
                defaultHeaders: $clone->defaultHeaders,
                cookies: $clone->cookies,
                serverParams: $clone->serverParams,
                attributes: $clone->attributes,
                host: null,
            );
        }

        $clone->serverParams['HTTP_HOST'] = $this->hostHeader($host);

        return new self(
            app: $clone->app,
            session: $clone->session,
            baseUri: $clone->baseUri,
            requestConfigurator: $clone->requestConfigurator,
            defaultHeaders: $clone->defaultHeaders,
            cookies: $clone->cookies,
            serverParams: $clone->serverParams,
            attributes: $clone->attributes,
            host: $host,
        );
    }

    public function withHeader(string $name, string $value): self
    {
        $clone                        = clone $this;
        $clone->defaultHeaders[$name] = $value;

        return $clone;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone                 = clone $this;
        $clone->defaultHeaders = array_merge($clone->defaultHeaders, $headers);

        return $clone;
    }

    public function withCookie(string $name, mixed $value): self
    {
        $clone                 = clone $this;
        $clone->cookies[$name] = $value;

        return $clone;
    }

    /**
     * @param array<string, mixed> $cookies
     */
    public function withCookies(array $cookies): self
    {
        $clone          = clone $this;
        $clone->cookies = array_merge($clone->cookies, $cookies);

        return $clone;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone                    = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function withAttributes(array $attributes): self
    {
        $clone             = clone $this;
        $clone->attributes = array_merge($clone->attributes, $attributes);

        return $clone;
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

        $uri         = $this->makeUri($path);
        $headers     = $this->mergeHeaders($headers, $data !== null);
        $queryParams = $this->parseQueryParams($uri);

        $request = new ServerRequest(
            $method,
            $uri,
            $headers,
            null,
            '1.1',
            $this->serverParams,
            $this->cookies,
            $queryParams,
            attributes: $this->attributes,
        );

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
            $uri = $this->makeUri($uri);
        } else {
            $uri = $this->applyHostToUri($uri);
        }

        $queryParams = $this->parseQueryParams($uri);

        if ($request instanceof ServerRequest) {
            $serverRequest = $request;
            $serverRequest = $serverRequest->withUri(new Uri($uri));
            $serverRequest = $this->applyDefaultHeaders($serverRequest);
            $serverRequest = $serverRequest->withCookieParams($serverRequest->getCookieParams() + $this->cookies);
            if ($queryParams !== [] && $serverRequest->getQueryParams() === []) {
                $serverRequest = $serverRequest->withQueryParams($queryParams);
            }
        } else {
            $serverRequest = new ServerRequest(
                $request->getMethod(),
                $uri,
                $this->defaultHeaders + $request->getHeaders(),
                null,
                '1.1',
                $this->serverParams,
                $this->cookies,
                $queryParams,
                attributes: $this->attributes,
            );
        }

        if (!$request instanceof ServerRequest) {
            $serverRequest = $serverRequest->withBody($request->getBody());
        } else {
            foreach ($this->attributes as $name => $value) {
                if ($serverRequest->getAttribute($name) === null) {
                    $serverRequest = $serverRequest->withAttribute($name, $value);
                }
            }
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
            'Referer'      => $this->baseOrigin() . '/login',
            'X-XSRF-TOKEN' => (string) $this->session->get('csrf_token', ''),
        ];

        if ($hasBody) {
            $defaults['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        if ($this->cookies !== [] && !$this->hasHeader($headers + $this->defaultHeaders + $defaults, 'Cookie')) {
            $defaults['Cookie'] = http_build_query($this->cookies, '', '; ', PHP_QUERY_RFC3986);
        }

        return $headers + $this->defaultHeaders + $defaults;
    }

    private function configureRequest(ServerRequest $request): ServerRequest
    {
        if ($this->requestConfigurator === null) {
            return $request;
        }

        $result = ($this->requestConfigurator)($request);

        return $result instanceof ServerRequest ? $result : $request;
    }

    private function makeUri(string $path): string
    {
        return $this->applyHostToUri(rtrim($this->baseUri, '/') . $path);
    }

    private function applyHostToUri(string $uri): string
    {
        if ($this->host === null || trim($this->host) === '') {
            return $uri;
        }

        $parsed = $this->parseHost($this->host);
        if ($parsed['host'] === '') {
            return $uri;
        }

        $requestUri = new Uri($uri);

        $requestUri = $requestUri->withHost($parsed['host']);

        return (string) $requestUri->withPort($parsed['port']);
    }

    private function baseOrigin(): string
    {
        $uri = $this->applyHostToUri(rtrim($this->baseUri, '/'));

        return rtrim($uri, '/');
    }

    /**
     * @return array{host: string, port: int|null}
     */
    private function parseHost(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['host' => '', 'port' => null];
        }

        $parsed = parse_url(str_contains($value, '://') ? $value : '//' . $value);
        if (!is_array($parsed)) {
            return ['host' => '', 'port' => null];
        }

        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? null;

        return [
            'host' => is_string($host) ? $host : '',
            'port' => is_int($port) ? $port : null,
        ];
    }

    private function hostHeader(string $value): string
    {
        $parsed = $this->parseHost($value);
        if ($parsed['host'] === '') {
            return trim($value);
        }

        if ($parsed['port'] !== null) {
            return $parsed['host'] . ':' . $parsed['port'];
        }

        return $parsed['host'];
    }

    private function applyDefaultHeaders(ServerRequestInterface $request): ServerRequestInterface
    {
        foreach ($this->defaultHeaders as $name => $value) {
            if (!$request->hasHeader($name)) {
                $request = $request->withHeader($name, $value);
            }
        }

        if ($this->cookies !== [] && !$request->hasHeader('Cookie')) {
            $request = $request->withHeader('Cookie', http_build_query($this->cookies, '', '; ', PHP_QUERY_RFC3986));
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function withoutArrayKey(array $values, string $key): array
    {
        if (!array_key_exists($key, $values)) {
            return $values;
        }

        unset($values[$key]);

        return $values;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $headerName => $_value) {
            if (strtolower((string) $headerName) === strtolower($name)) {
                return true;
            }
        }

        return false;
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
