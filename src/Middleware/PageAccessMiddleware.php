<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Logging\ErrorLogger;
use App\Services\Navigation\PageAvailability;
use App\Services\Navigation\PageVisibility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class PageAccessMiddleware implements MiddlewareInterface
{
    private const ACCESS_CACHE_SECONDS = 60;

    /** @var callable():\App\Database\DbInterface */
    private $dbResolver;
    private string $basePath;
    private array $config;

    public function __construct(callable $dbResolver, string $basePath = '', array $config = [])
    {
        $this->dbResolver = $dbResolver;
        $this->basePath   = $basePath;
        $this->config     = $config;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            // Неавторизованных обрабатывает AuthMiddleware
            return $handler->handle($request);
        }

        $disabledPages = (new PageVisibility(($this->dbResolver)()))->disabledPages();
        $currentConfig = \App\Services\Config\ConfigSchema::current();
        $currentConfig['disabled_pages'] = $disabledPages;
        \App\Services\Config\ConfigSchema::setCurrent($currentConfig);
        $pageAvailability = new PageAvailability(
            $disabledPages,
            $this->basePath
        );

        if (\App\Services\Auth\Authorization::isSuper($user)) {
            return $handler->handle($request);
        }
        $disabledPage = $pageAvailability->disabledPageForRequest($request);
        if ($disabledPage !== null) {
            return $this->unavailable($request);
        }

        $allowed = [];
        try {
            $allowedCachedAt = (int) ($_SESSION['allowed_pages_cached_at'] ?? 0);
            if (isset($_SESSION['user']['allowed_pages'])
                && $allowedCachedAt > time() - self::ACCESS_CACHE_SECONDS) {
                $allowed = (array) $_SESSION['user']['allowed_pages'];
            } else {
                $allowed = $this->allowedPages((int) ($user['id'] ?? 0));
                $_SESSION['user']['allowed_pages'] = $allowed;
                $_SESSION['allowed_pages_cached_at'] = time();
            }
        } catch (\Throwable $e) {
            ErrorLogger::logThrowable($e, [
                'module' => self::class,
                'function' => 'process',
                'params' => ['user_id' => (int) ($user['id'] ?? 0)],
            ], $request);
            // Таблицы ролей ещё нет — сохраняем прежний режим первичной настройки.
            return $handler->handle($request);
        }

        $page = $this->resolvePage($request->getUri()->getPath(), $request->getQueryParams());
        if ($page === null) {
            // Путь не относится к разграничиваемым страницам — пропускаем
            return $handler->handle($request);
        }

        $definition = \App\Services\Navigation\PageRegistry::all()[$page] ?? [];
        if (!empty($definition['super_only']) && !\App\Services\Auth\Authorization::isSuper($user)) {
            return $this->deny($request, $page);
        }
        if (!empty($definition['admin_only']) && !\App\Services\Auth\Authorization::isAdmin($user)) {
            return $this->deny($request, $page);
        }

        $accessPages = \App\Services\Navigation\PageRegistry::accessPages(
            $this->path($request->getUri()->getPath()),
            $request->getQueryParams()
        );
        if (array_intersect($accessPages, $allowed)) {
            return $handler->handle($request);
        }

        // bootstrap — нет ни одного admin-а, пропускаем
        if ($this->noAdminYet()) {
            return $handler->handle($request);
        }

        return $this->deny($request, $page);
    }

    private function resolvePage(string $path, array $query = []): ?string
    {
        return \App\Services\Navigation\PageRegistry::pageForPath($this->path($path), $query);
    }

    private function path(string $path): string
    {
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        return '/' . ltrim($path, '/');
    }

    private function allowedPages(int $userId): array
    {
        $rows = ($this->dbResolver)()->fetchAll(
            'SELECT p.page
               FROM xx_rjd_role_pages p
               JOIN xx_rjd_user_roles ur ON ur.role_id = p.role_id
              WHERE ur.user_id = :id',
            ['id' => $userId]
        );
        return \App\Services\Navigation\PageRegistry::forUser(
            array_map(static fn($r) => $r['page'], $rows),
            (array) ($_SESSION['user'] ?? [])
        );
    }

    private function noAdminYet(): bool
    {
        try {
            return !\App\Services\Auth\Authorization::hasAdministrators(($this->dbResolver)());
        } catch (\Throwable $e) {
            ErrorLogger::logThrowable($e, [
                'module' => self::class,
                'function' => 'noAdminYet',
            ]);
            return true;
        }
    }

    /** Формирует ответ «доступ запрещён» (JSON для /api, иначе HTML). */
    private function deny(ServerRequestInterface $request, string $page): ResponseInterface
    {
        $response = new Response(403);
        $path = $request->getUri()->getPath();

        if (str_contains($path, '/api/')) {
            $response->getBody()->write(json_encode(
                ['status' => 'error', 'message' => 'Недостаточно прав для доступа к разделу'],
                JSON_UNESCAPED_UNICODE
            ));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $appName     = $this->config['app_name'] ?? '';
        $basePath    = $this->basePath;
        $user        = $_SESSION['user'] ?? [];
        $headerSub   = '<div class="brand-sub">Ошибка доступа</div>';
        $headerRight = '';

        ob_start();
        include __DIR__ . '/../../templates/403.php';
        $html = ob_get_clean();

        $response->getBody()->write((string) $html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** Формирует ответ для временно отключённой страницы. */
    private function unavailable(ServerRequestInterface $request): ResponseInterface
    {
        $response = new Response(503);
        $path = $request->getUri()->getPath();

        if (str_contains($path, '/api/')) {
            $response->getBody()->write(json_encode(
                ['status' => 'error', 'message' => 'Раздел временно недоступен'],
                JSON_UNESCAPED_UNICODE
            ));
            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        $appName = $this->config['app_name'] ?? '';
        $basePath = $this->basePath;
        $user = $_SESSION['user'] ?? [];
        $headerSub = '<div class="brand-sub">Раздел отключён</div>';
        $headerRight = '';

        ob_start();
        include __DIR__ . '/../../templates/503.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
