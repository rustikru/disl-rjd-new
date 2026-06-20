<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Постраничное разграничение доступа по ролям.
 *
 * Сопоставляет запрошенный путь с «страницей» приложения (dashboard / maps /
 * import / admin) и проверяет, что роль пользователя имеет к ней доступ
 * (таблица xx_rjd_role_pages).
 *
 * Особые случаи:
 *  - роль ADMIN — полный доступ ко всему;
 *  - первичная настройка (ни один пользователь ещё не назначен администратором)
 *    либо инфраструктура ролей не развёрнута — доступ открыт, чтобы не
 *    заблокировать систему.
 */
class PageAccessMiddleware implements MiddlewareInterface
{
    /** @var callable():\App\Database\DbInterface */
    private $dbResolver;
    private string $basePath;

    public function __construct(callable $dbResolver, string $basePath = '')
    {
        $this->dbResolver = $dbResolver;
        $this->basePath = $basePath;
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
            // Инфраструктура ролей ещё не развёрнута — не блокируем
            return $handler->handle($request);
        }

        if (in_array($page, $allowed, true)) {
            return $handler->handle($request);
        }

        // Доступа нет. Но если в системе ещё нет ни одного администратора —
        // режим первичной настройки, пропускаем.
        if ($this->noAdminYet()) {
            return $handler->handle($request);
        }

        return $this->deny($request, $page);
    }

    /** Сопоставляет путь запроса со страницей приложения. */
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

    /** Возвращает список страниц, доступных всем ролям пользователя. */
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

    /** В системе ещё нет назначенных администраторов? */
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

        $home = htmlspecialchars($this->basePath) . '/';
        $response->getBody()->write(
            '<!doctype html><meta charset="utf-8">'
            . '<title>Нет доступа</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:480px;margin:80px auto;text-align:center;color:#1b1726">'
            . '<div style="font-size:48px;font-weight:800;color:#46297f">403</div>'
            . '<h2 style="margin:8px 0 4px">Раздел недоступен</h2>'
            . '<p style="color:#6b667a">У вашей роли нет доступа к этой странице.</p>'
            . '<p><a style="color:#46297f" href="' . $home . '">← На главную</a></p>'
            . '</div>'
        );
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
