<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Middleware\AuthMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // Сбрасываем сессию перед каждым тестом
        $_SESSION = [];
    }

    private function makeHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response(200));
        return $handler;
    }

    private function makeRequest(): ServerRequestInterface
    {
        return $this->createMock(ServerRequestInterface::class);
    }

    public function testRedirectsToLoginWhenNoSession(): void
    {
        $middleware = new AuthMiddleware('');
        $response = $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testRedirectsToLoginWhenSessionUserIsNull(): void
    {
        $_SESSION['user'] = null;

        $middleware = new AuthMiddleware('');
        $response = $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testPassesThroughWhenSessionExists(): void
    {
        $_SESSION['user'] = ['id' => 1, 'username' => 'alice'];

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn(new Response(200));

        $middleware = new AuthMiddleware('');
        $response = $middleware->process($this->makeRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testBasePathPrependedToLoginUrl(): void
    {
        $middleware = new AuthMiddleware('/rzd');
        $response = $middleware->process($this->makeRequest(), $this->makeHandler());

        $this->assertSame('/rzd/login', $response->getHeaderLine('Location'));
    }
}
