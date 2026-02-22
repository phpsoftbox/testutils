<?php

declare(strict_types=1);

namespace PhpSoftBox\TestUtils\Tests\Http;

use PhpSoftBox\Application\Application;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\ArraySessionStore;
use PhpSoftBox\TestUtils\Http\TestHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(TestHttpClient::class)]
#[CoversMethod(TestHttpClient::class, 'get')]
#[CoversMethod(TestHttpClient::class, 'post')]
#[CoversMethod(TestHttpClient::class, 'put')]
#[CoversMethod(TestHttpClient::class, 'request')]
#[CoversMethod(TestHttpClient::class, 'sendRequest')]
final class TestHttpClientTest extends TestCase
{
    /**
     * Проверяет, что GET-запрос клиента добавляет базовые заголовки и корректно передает query-параметры.
     */
    #[Test]
    public function getAddsDefaultHeadersAndQueryParams(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $lastRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(200);
            }
        };

        $client = $this->makeClient($handler);
        $client->get('/settings?query=value');

        $request = $handler->lastRequest;
        $this->assertNotNull($request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://example.test/settings?query=value', (string) $request->getUri());
        $this->assertSame(['query' => 'value'], $request->getQueryParams());
        $this->assertSame('true', $request->getHeaderLine('X-Inertia'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame('https://example.test/login', $request->getHeaderLine('Referer'));
        $this->assertSame('test-csrf-token', $request->getHeaderLine('X-XSRF-TOKEN'));
    }

    /**
     * Проверяет, что POST-запрос отправляет parsed body и выставляет корректный Content-Type.
     */
    #[Test]
    public function postAddsParsedBodyAndContentType(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $lastRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(200);
            }
        };

        $client = $this->makeClient($handler);
        $client->post('/submit', ['name' => 'Anton']);

        $request = $handler->lastRequest;
        $this->assertNotNull($request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame(['name' => 'Anton'], $request->getParsedBody());
        $this->assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
    }

    /**
     * Проверяет, что PUT-запрос передает тело запроса в обработчик.
     */
    #[Test]
    public function putSendsBody(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $lastRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(200);
            }
        };

        $client = $this->makeClient($handler);
        $client->put('/submit', ['enabled' => true]);

        $request = $handler->lastRequest;
        $this->assertNotNull($request);
        $this->assertSame('PUT', $request->getMethod());
        $this->assertSame(['enabled' => true], $request->getParsedBody());
    }

    /**
     * Проверяет, что sendRequest формирует абсолютный URI на основе baseUri клиента.
     */
    #[Test]
    public function sendRequestUsesBaseUri(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $lastRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(200);
            }
        };

        $client = $this->makeClient($handler);

        $request = new ServerRequest('GET', '/health');

        $client->sendRequest($request);

        $captured = $handler->lastRequest;
        $this->assertNotNull($captured);
        $this->assertSame('https://example.test/health', (string) $captured->getUri());
    }

    /**
     * Проверяет, что клиент умеет выставлять auth-token cookie, bearer header, host и request attributes.
     */
    #[Test]
    public function authAndHostHelpersConfigureRequest(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public ?ServerRequestInterface $lastRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->lastRequest = $request;

                return new Response(200);
            }
        };

        $client = $this->makeClient($handler)
            ->withHost('admin.example.test:8443')
            ->withAuthToken('cookie-token')
            ->withBearerToken('bearer-token')
            ->withCookie('locale', 'ru')
            ->withAttribute('user_id', 42);

        $client->get('/admin');

        $request = $handler->lastRequest;
        $this->assertNotNull($request);
        $this->assertSame('https://admin.example.test:8443/admin', (string) $request->getUri());
        $this->assertSame('admin.example.test:8443', $request->getHeaderLine('Host'));
        $this->assertSame('admin.example.test:8443', $request->getServerParams()['HTTP_HOST'] ?? null);
        $this->assertSame('Bearer bearer-token', $request->getHeaderLine('Authorization'));
        $this->assertSame('cookie-token', $request->getCookieParams()['auth_token'] ?? null);
        $this->assertSame('ru', $request->getCookieParams()['locale'] ?? null);
        $this->assertSame(42, $request->getAttribute('user_id'));
        $this->assertSame('https://admin.example.test:8443/login', $request->getHeaderLine('Referer'));
    }

    private function makeClient(RequestHandlerInterface $handler): TestHttpClient
    {
        $app     = new Application($handler);
        $session = new Session(new ArraySessionStore());

        return new TestHttpClient($app, $session, 'https://example.test');
    }
}
