<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class PageAccessMiddleware implements MiddlewareInterface
{
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

        $page = $this->resolvePage($request->getUri()->getPath());
        if ($page === null) {
            // Путь не относится к разграничиваемым страницам — пропускаем
            return $handler->handle($request);
        }

        // Администратор — полный доступ
        if ($user['is_admin'] ?? false) {
            return $handler->handle($request);
        }

        try {
            $allowed = $this->allowedPages((int) ($user['id'] ?? 0));
        } catch (\Throwable $e) {
            // таблицы ролей ещё нет — не блокируем
            return $handler->handle($request);
        }

        if (in_array($page, $allowed, true)) {
            return $handler->handle($request);
        }

        // bootstrap — нет ни одного admin-а, пропускаем
        if ($this->noAdminYet()) {
            return $handler->handle($request);
        }

        return $this->deny($request, $page);
    }

    private function resolvePage(string $path): ?string
    {
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        $path = '/' . ltrim($path, '/');

        if ($path === '/' || $path === '' || $path === '/detail') {
            return 'dashboard';
        }
        if (str_starts_with($path, '/admin')) {
            return 'admin';
        }
        if (str_starts_with($path, '/maps')) {
            return 'maps';
        }
        if (str_starts_with($path, '/import') || str_starts_with($path, '/api/import')) {
            return 'import';
        }
        if (str_starts_with($path, '/api')) {
            return 'dashboard';
        }
        return null;
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
        return array_map(static fn($r) => $r['page'], $rows);
    }

    private function noAdminYet(): bool
    {
        try {
            $row = ($this->dbResolver)()->fetchOne(
                "SELECT COUNT(*) AS cnt FROM xx_rjd_user_roles ur
                  JOIN xx_rjd_roles r ON r.id = ur.role_id
                 WHERE r.code = 'ADMIN'"
            );
            return (int) ($row['cnt'] ?? 0) === 0;
        } catch (\Throwable $e) {
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
}
