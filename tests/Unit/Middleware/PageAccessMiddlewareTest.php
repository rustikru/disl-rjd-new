<?php
declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Database\DbInterface;
use App\Middleware\PageAccessMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Slim\Psr7\Response;

class PageAccessMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    // =========================================================================
    // resolvePage()
    // =========================================================================

    private function resolvePage(string $path, string $basePath = ''): ?string
    {
        $method = new ReflectionMethod(PageAccessMiddleware::class, 'resolvePage');
        $method->setAccessible(true);

        $db = $this->createMock(DbInterface::class);
        $middleware = new PageAccessMiddleware(fn() => $db, $basePath);
        return $method->invoke($middleware, $path);
    }

    public function testRootPathReturnsNull(): void
    {
        $this->assertNull($this->resolvePage('/'));
    }

    public function testDetailPathReturnsNull(): void
    {
        $this->assertNull($this->resolvePage('/detail'));
    }

    public function testAdminPathReturnsAdmin(): void
    {
        $this->assertSame('admin', $this->resolvePage('/admin'));
    }

    public function testAdminSubPathReturnsAdmin(): void
    {
        $this->assertSame('admin', $this->resolvePage('/admin/users'));
        $this->assertSame('admin', $this->resolvePage('/admin/roles'));
    }

    public function testMapsPathReturnsMaps(): void
    {
        $this->assertSame('maps', $this->resolvePage('/maps'));
    }

    public function testMapsSubPathReturnsMaps(): void
    {
        $this->assertSame('maps', $this->resolvePage('/maps/detail'));
    }

    public function testImportPathReturnsImport(): void
    {
        $this->assertSame('import', $this->resolvePage('/import'));
    }

    public function testApiImportPathReturnsImport(): void
    {
        $this->assertSame('import', $this->resolvePage('/api/import/file'));
    }

    public function testApiPathReturnsDashboard(): void
    {
        $this->assertSame('dashboard', $this->resolvePage('/api/dashboard'));
        $this->assertSame('dashboard', $this->resolvePage('/api/dislocation/summary'));
        $this->assertSame('dashboard', $this->resolvePage('/api/kpi/summary'));
    }

    public function testUnknownPathReturnsNull(): void
    {
        $this->assertNull($this->resolvePage('/some-unknown-page'));
    }

    public function testBasePathStrippedBeforeResolving(): void
    {
        $this->assertSame('admin', $this->resolvePage('/rzd/admin/users', '/rzd'));
        $this->assertSame('maps',  $this->resolvePage('/rzd/maps', '/rzd'));
        $this->assertNull($this->resolvePage('/rzd/', '/rzd'));
    }

    // =========================================================================
    // process()
    // =========================================================================

    private function makeRequest(string $path): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        return $request;
    }

    private function makeHandler(int $status = 200): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Response($status));
        return $handler;
    }

    public function testUnauthenticatedUserPassesThrough(): void
    {
        // Неавторизованных обрабатывает AuthMiddleware, PageAccess пропускает
        $db = $this->createMock(DbInterface::class);
        $middleware = new PageAccessMiddleware(fn() => $db);

        $response = $middleware->process($this->makeRequest('/admin'), $this->makeHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testAdminUserAlwaysPassesThrough(): void
    {
        $_SESSION['user'] = ['id' => 1, 'is_admin' => true];

        $db = $this->createMock(DbInterface::class);
        $middleware = new PageAccessMiddleware(fn() => $db);

        $response = $middleware->process($this->makeRequest('/admin'), $this->makeHandler());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUserWithAllowedPagePassesThrough(): void
    {
        $_SESSION['user'] = ['id' => 2, 'is_admin' => false];

        $db = $this->createMock(DbInterface::class);
        $db->method('fetchAll')->willReturn([['page' => 'maps']]);
        $db->method('fetchOne')->willReturn(['cnt' => 1]); // admin exists

        $middleware = new PageAccessMiddleware(fn() => $db);
        $response = $middleware->process($this->makeRequest('/maps'), $this->makeHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testUserWithoutPermissionGets403(): void
    {
        $_SESSION['user'] = ['id' => 2, 'is_admin' => false];

        $db = $this->createMock(DbInterface::class);
        $db->method('fetchAll')->willReturn([]); // нет страниц
        $db->method('fetchOne')->willReturn(['cnt' => 1]); // admin exists

        $middleware = new PageAccessMiddleware(fn() => $db, '', ['app_name' => 'Test']);

        // Создаём реальный request с /api/ путём чтобы получить JSON 403
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/api/dashboard');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);

        $response = $middleware->process($request, $this->makeHandler());
        $this->assertSame(403, $response->getStatusCode());
    }

    public function testBootstrapModeAllowsAllWhenNoAdminExists(): void
    {
        // Если нет ни одного ADMIN — система в bootstrap-режиме, всё разрешено
        $_SESSION['user'] = ['id' => 2, 'is_admin' => false];

        $db = $this->createMock(DbInterface::class);
        $db->method('fetchAll')->willReturn([]); // нет страниц у пользователя
        $db->method('fetchOne')->willReturn(['cnt' => 0]); // нет ни одного ADMIN

        $middleware = new PageAccessMiddleware(fn() => $db);
        $response = $middleware->process($this->makeRequest('/admin'), $this->makeHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHomePageAlwaysAccessible(): void
    {
        // / не входит в список охраняемых страниц — middleware пропускает без проверки ролей
        $_SESSION['user'] = ['id' => 2, 'is_admin' => false];

        $db = $this->createMock(DbInterface::class);
        $db->expects($this->never())->method('fetchAll'); // не должен спрашивать роли

        $middleware = new PageAccessMiddleware(fn() => $db);
        $response = $middleware->process($this->makeRequest('/'), $this->makeHandler());

        $this->assertSame(200, $response->getStatusCode());
    }
}
