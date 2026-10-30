<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\Authorization;
use App\Services\Maintenance;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/** Закрывает сайт на время технических работ, но сохраняет доступ SUPER. */
final class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(private array $config) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!Maintenance::isActive($this->config) || Authorization::isSuper() || $this->isLoginPath($request)) {
            return $handler->handle($request);
        }

        $response = (new Response(503))
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Retry-After', '300');

        if ($this->isApi($request)) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => (string) ($this->config['maintenance_message'] ?? ''),
            ], JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? '';
        $maintenanceEnd = $this->config['maintenance_end'] ?? '';
        $maintenanceTitle = $this->config['maintenance_title'] ?? '';
        $maintenanceMessage = $this->config['maintenance_message'] ?? '';
        $user = $_SESSION['user'] ?? [];
        ob_start();
        require dirname(__DIR__, 2) . '/templates/maintenance.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function isLoginPath(ServerRequestInterface $request): bool
    {
        return in_array($this->path($request), ['/login', '/auth/kerberos', '/logout'], true);
    }

    private function isApi(ServerRequestInterface $request): bool
    {
        return str_starts_with($this->path($request), '/api/');
    }

    private function path(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        $basePath = rtrim((string) ($this->config['base_path'] ?? ''), '/');
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        return '/' . ltrim($path, '/');
    }
}
