<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Администрирование: управление пользователями и ролями.
 *
 * Доступ к разделу имеет только пользователь с ролью ADMIN.
 * Пока в системе нет ни одного администратора (первичная настройка) —
 * раздел открыт любому авторизованному пользователю, чтобы можно было
 * назначить первого администратора через интерфейс.
 */
class AdminController
{
    private DbInterface $db;
    private array $config;

    public function __construct(DbInterface $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    /** GET /admin */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $roles = $this->db->fetchAll(
            'SELECT id, code, name, description FROM xx_rjd_roles ORDER BY id'
        );

        $users = $this->db->fetchAll(
            'SELECT u.id, u.username, u.display_name, u.email, u.is_active, u.role_id,
                    r.code AS role_code, r.name AS role_name
               FROM xx_rjd_users u
               LEFT JOIN xx_rjd_roles r ON r.id = u.role_id
              ORDER BY NLSSORT(u.display_name, \'NLS_SORT=RUSSIAN\'), u.username'
        );

        $appName  = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user     = $_SESSION['user'] ?? [];

        $query = $request->getQueryParams();
        $flashOk  = $query['ok']  ?? null;
        $flashErr = $query['err'] ?? null;

        ob_start();
        include __DIR__ . '/../../templates/admin.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** POST /admin/users/role — изменить роль пользователя */
    public function saveRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $userId = (int) ($body['user_id'] ?? 0);
        $roleId = $body['role_id'] ?? '';
        $roleId = ($roleId === '' || $roleId === null) ? null : (int) $roleId;

        if ($userId <= 0) {
            return $this->redirect($response, '/admin?err=' . urlencode('Пользователь не найден'));
        }

        $this->db->execute(
            'UPDATE xx_rjd_users SET role_id = :role_id WHERE id = :id',
            ['role_id' => $roleId, 'id' => $userId]
        );

        return $this->redirect($response, '/admin?ok=' . urlencode('Роль обновлена'));
    }

    /** POST /admin/users/active — заблокировать / разблокировать пользователя */
    public function toggleActive(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $userId = (int) ($body['user_id'] ?? 0);
        $active = (int) ($body['is_active'] ?? 0) === 1 ? 1 : 0;

        if ($userId <= 0) {
            return $this->redirect($response, '/admin?err=' . urlencode('Пользователь не найден'));
        }

        $this->db->execute(
            'UPDATE xx_rjd_users SET is_active = :active WHERE id = :id',
            ['active' => $active, 'id' => $userId]
        );

        return $this->redirect(
            $response,
            '/admin?ok=' . urlencode($active === 1 ? 'Пользователь разблокирован' : 'Пользователь заблокирован')
        );
    }

    /** POST /admin/users — создать пользователя */
    public function createUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $username    = trim((string) ($body['username'] ?? ''));
        $displayName = trim((string) ($body['display_name'] ?? ''));
        $email       = trim((string) ($body['email'] ?? ''));
        $password    = (string) ($body['password'] ?? '');
        $roleId      = $body['role_id'] ?? '';
        $roleId      = ($roleId === '' || $roleId === null) ? null : (int) $roleId;

        if ($username === '' || $displayName === '') {
            return $this->redirect($response, '/admin?err=' . urlencode('Укажите логин и имя пользователя'));
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_users WHERE username = :username',
            ['username' => $username]
        );
        if ($exists) {
            return $this->redirect($response, '/admin?err=' . urlencode('Пользователь с таким логином уже существует'));
        }

        $hash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : '';

        $this->db->execute(
            'INSERT INTO xx_rjd_users (username, display_name, email, password_hash, is_active, role_id)
             VALUES (:username, :display_name, :email, :hash, 1, :role_id)',
            [
                'username'     => $username,
                'display_name' => $displayName,
                'email'        => $email !== '' ? $email : null,
                'hash'         => $hash,
                'role_id'      => $roleId,
            ]
        );

        return $this->redirect($response, '/admin?ok=' . urlencode('Пользователь создан'));
    }

    /** Текущий пользователь — администратор? */
    private function isAdmin(): bool
    {
        $u = $_SESSION['user'] ?? [];
        if (($u['role_code'] ?? '') === 'ADMIN') {
            return true;
        }

        // Первичная настройка: пока ни один пользователь не назначен администратором,
        // либо инфраструктура ролей ещё не развёрнута — пускаем авторизованного пользователя.
        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt FROM xx_rjd_users u
                  JOIN xx_rjd_roles r ON r.id = u.role_id
                 WHERE r.code = 'ADMIN'"
            );
            return (int) ($row['cnt'] ?? 0) === 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function checkCsrf(array $body): bool
    {
        $csrf = $body['csrf_token'] ?? '';
        return $csrf !== '' && hash_equals($_SESSION['csrf_token'] ?? '', (string) $csrf);
    }

    private function forbidden(ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(
            '<!doctype html><meta charset="utf-8"><div style="font-family:system-ui;padding:48px;text-align:center">'
            . '<h1 style="color:#46297f">403</h1><p>Недостаточно прав для доступа к разделу администрирования.</p>'
            . '<p><a href="' . htmlspecialchars($this->config['base_path'] ?? '') . '/">← На главную</a></p></div>'
        );
        return $response->withStatus(403)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        $base = $this->config['base_path'] ?? '';
        return $response->withHeader('Location', $base . $url)->withStatus(302);
    }
}
