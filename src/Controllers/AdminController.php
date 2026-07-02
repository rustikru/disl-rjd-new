<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use App\Repositories\StationDirectoryRepository;
use App\Services\FreiconStationClient;
use App\Services\StationDirectoryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminController
{
    private DbInterface $db;
    private array $config;

    public const PAGES = [
        'dashboard' => 'Дашборд',
        'maps'      => 'Карта',
        'import'    => 'Загрузка справок',
        'admin'     => 'Администрирование',
    ];

    public function __construct(DbInterface $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->redirect($response, '/admin/users');
    }

    public function usersPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $roles = $this->db->fetchAll('SELECT id, code, name FROM xx_rjd_roles ORDER BY id');

        $rawUsers = $this->db->fetchAll(
            'SELECT u.id, u.username, u.display_name, u.email, u.is_active
               FROM xx_rjd_users u
              ORDER BY NLSSORT(u.display_name, \'NLS_SORT=RUSSIAN\'), u.username'
        );

        $userRolesRaw = $this->db->fetchAll(
            'SELECT ur.user_id, r.id, r.code, r.name
               FROM xx_rjd_user_roles ur
               JOIN xx_rjd_roles r ON r.id = ur.role_id'
        );
        $rolesByUser = [];
        foreach ($userRolesRaw as $ur) {
            $rolesByUser[(int) $ur['user_id']][] = [
                'id'   => $ur['id'],
                'code' => $ur['code'],
                'name' => $ur['name'],
            ];
        }

        $users = [];
        foreach ($rawUsers as $u) {
            $u['roles'] = $rolesByUser[(int) $u['id']] ?? [];
            $users[]    = $u;
        }

        $appName  = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user     = $_SESSION['user'] ?? [];

        $query    = $request->getQueryParams();
        $flashOk  = $query['ok']  ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf     = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/users.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function rolesPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $roles = $this->db->fetchAll(
            'SELECT id, code, name, description, is_system FROM xx_rjd_roles ORDER BY id'
        );

        $rolePages = [];
        foreach ($this->db->fetchAll('SELECT role_id, page FROM xx_rjd_role_pages') as $rp) {
            $rolePages[(int) $rp['role_id']][$rp['page']] = true;
        }

        $pages = self::PAGES;

        $appName  = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user     = $_SESSION['user'] ?? [];

        $query    = $request->getQueryParams();
        $flashOk  = $query['ok']  ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf     = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/roles.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function stationsPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $query = $request->getQueryParams();
        $search = trim((string) ($query['q'] ?? ''));
        $pageData = $this->stationService()->getPage($search, (int) ($query['page'] ?? 1));
        $stations = $pageData['stations'];
        $totalStations = $pageData['totalStations'];
        $totalPages = $pageData['totalPages'];
        $page = $pageData['page'];
        $perPage = $pageData['perPage'];

        $appName  = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user     = $_SESSION['user'] ?? [];
        $flashOk  = $query['ok']  ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf     = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/stations.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function saveUserRoles(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $userId  = (int) ($body['user_id'] ?? 0);
        $roleIds = array_map('intval', (array) ($body['role_ids'] ?? []));
        $roleIds = array_filter($roleIds, static fn(int $id) => $id > 0);

        if ($userId <= 0) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Пользователь не найден'));
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM xx_rjd_user_roles WHERE user_id = :user_id', ['user_id' => $userId]);
            foreach ($roleIds as $roleId) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                    ['user_id' => $userId, 'role_id' => $roleId]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/users?err=' . urlencode('Не удалось обновить роли'));
        }

        return $this->redirect($response, '/admin/users?ok=' . urlencode('Роли обновлены'));
    }

    public function toggleActive(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $userId = (int) ($body['user_id'] ?? 0);
        $active = (int) ($body['is_active'] ?? 0) === 1 ? 1 : 0;

        if ($userId <= 0) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Пользователь не найден'));
        }

        $this->db->execute(
            'UPDATE xx_rjd_users SET is_active = :active WHERE id = :id',
            ['active' => $active, 'id' => $userId]
        );

        return $this->redirect(
            $response,
            '/admin/users?ok=' . urlencode($active === 1 ? 'Пользователь разблокирован' : 'Пользователь заблокирован')
        );
    }

    public function createUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $username    = trim((string) ($body['username'] ?? ''));
        $displayName = trim((string) ($body['display_name'] ?? ''));
        $email       = trim((string) ($body['email'] ?? ''));
        $password    = (string) ($body['password'] ?? '');
        $roleIds     = array_map('intval', (array) ($body['role_ids'] ?? []));
        $roleIds     = array_filter($roleIds, static fn(int $id) => $id > 0);

        if ($username === '' || $displayName === '') {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Укажите логин и имя пользователя'));
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_users WHERE username = :username',
            ['username' => $username]
        );
        if ($exists) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Пользователь с таким логином уже существует'));
        }

        $hash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : '';

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO xx_rjd_users (username, display_name, email, password_hash, is_active)
                 VALUES (:username, :display_name, :email, :hash, 1)',
                [
                    'username'     => $username,
                    'display_name' => $displayName,
                    'email'        => $email !== '' ? $email : null,
                    'hash'         => $hash,
                ]
            );

            if (!empty($roleIds)) {
                $newUser = $this->db->fetchOne(
                    'SELECT id FROM xx_rjd_users WHERE username = :username',
                    ['username' => $username]
                );
                $newUserId = (int) ($newUser['id'] ?? 0);
                foreach ($roleIds as $roleId) {
                    $this->db->execute(
                        'INSERT INTO xx_rjd_user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                        ['user_id' => $newUserId, 'role_id' => $roleId]
                    );
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/users?err=' . urlencode('Не удалось создать пользователя'));
        }

        return $this->redirect($response, '/admin/users?ok=' . urlencode('Пользователь создан'));
    }

    public function saveUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $userId      = (int) ($body['user_id'] ?? 0);
        $username    = trim((string) ($body['username'] ?? ''));
        $displayName = trim((string) ($body['display_name'] ?? ''));
        $email       = trim((string) ($body['email'] ?? ''));
        $password    = (string) ($body['password'] ?? '');

        if ($userId <= 0 || $username === '' || $displayName === '') {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Укажите логин и имя пользователя'));
        }

        $conflict = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_users WHERE username = :username AND id != :id',
            ['username' => $username, 'id' => $userId]
        );
        if ($conflict) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Пользователь с таким логином уже существует'));
        }

        $bindings = [
            'username'     => $username,
            'display_name' => $displayName,
            'email'        => $email !== '' ? $email : null,
            'id'           => $userId,
        ];
        if ($password !== '') {
            $bindings['hash'] = password_hash($password, PASSWORD_BCRYPT);
            $this->db->execute(
                'UPDATE xx_rjd_users SET username = :username, display_name = :display_name, email = :email, password_hash = :hash WHERE id = :id',
                $bindings
            );
        } else {
            $this->db->execute(
                'UPDATE xx_rjd_users SET username = :username, display_name = :display_name, email = :email WHERE id = :id',
                $bindings
            );
        }

        return $this->redirect($response, '/admin/users?ok=' . urlencode('Пользователь сохранён'));
    }

    public function resetPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $userId   = (int) ($body['user_id'] ?? 0);
        $password = (string) ($body['password'] ?? '');

        if ($userId <= 0 || $password === '') {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Укажите новый пароль'));
        }

        $this->db->execute(
            'UPDATE xx_rjd_users SET password_hash = :hash WHERE id = :id',
            ['hash' => password_hash($password, PASSWORD_BCRYPT), 'id' => $userId]
        );

        return $this->redirect($response, '/admin/users?ok=' . urlencode('Пароль обновлён'));
    }

    public function createRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        $name = trim((string) ($body['name'] ?? ''));
        $desc = trim((string) ($body['description'] ?? ''));

        if ($code === '' || $name === '') {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Укажите код и название роли'));
        }
        if (!preg_match('/^[A-Z][A-Z0-9_]{1,29}$/', $code)) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Код роли: латиница, цифры и _, начинается с буквы'));
        }

        $exists = $this->db->fetchOne('SELECT id FROM xx_rjd_roles WHERE code = :code', ['code' => $code]);
        if ($exists) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль с таким кодом уже существует'));
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO xx_rjd_roles (code, name, description, is_system) VALUES (:code, :name, :dsc, 0)',
                ['code' => $code, 'name' => $name, 'dsc' => $desc !== '' ? $desc : null]
            );
            $row = $this->db->fetchOne('SELECT id FROM xx_rjd_roles WHERE code = :code', ['code' => $code]);
            $this->savePages((int) $row['id'], (array) ($body['pages'] ?? []));
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Не удалось создать роль'));
        }

        return $this->redirect($response, '/admin/roles?ok=' . urlencode('Роль создана'));
    }

    public function saveRolePages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $roleId = (int) ($body['role_id'] ?? 0);
        if ($roleId <= 0) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль не найдена'));
        }

        $role = $this->db->fetchOne('SELECT id, code FROM xx_rjd_roles WHERE id = :id', ['id' => $roleId]);
        if (!$role) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль не найдена'));
        }
        if (($role['code'] ?? '') === 'ADMIN') {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Доступ роли «Администратор» изменить нельзя'));
        }

        $name = trim((string) ($body['name'] ?? ''));
        $desc = trim((string) ($body['description'] ?? ''));

        $this->db->beginTransaction();
        try {
            if ($name !== '') {
                $this->db->execute(
                    'UPDATE xx_rjd_roles SET name = :name, description = :dsc WHERE id = :id',
                    ['name' => $name, 'dsc' => $desc !== '' ? $desc : null, 'id' => $roleId]
                );
            }
            $this->db->execute('DELETE FROM xx_rjd_role_pages WHERE role_id = :id', ['id' => $roleId]);
            $this->savePages($roleId, (array) ($body['pages'] ?? []));
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Не удалось сохранить роль'));
        }

        return $this->redirect($response, '/admin/roles?ok=' . urlencode('Роль обновлена'));
    }

    public function deleteRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $roleId = (int) ($body['role_id'] ?? 0);
        $role = $this->db->fetchOne('SELECT id, code FROM xx_rjd_roles WHERE id = :id', ['id' => $roleId]);
        if (!$role) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль не найдена'));
        }
        if (($role['code'] ?? '') === 'ADMIN') {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль «Администратор» удалить нельзя'));
        }

        $used = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM xx_rjd_user_roles WHERE role_id = :id',
            ['id' => $roleId]
        );
        if ((int) ($used['cnt'] ?? 0) > 0) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль назначена пользователям — сначала переназначьте их'));
        }

        $this->db->execute('DELETE FROM xx_rjd_roles WHERE id = :id', ['id' => $roleId]);

        return $this->redirect($response, '/admin/roles?ok=' . urlencode('Роль удалена'));
    }

    public function saveStation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/directories/stations?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $esrCode = trim((string) ($body['esr_code'] ?? ''));
        $stationName = trim((string) ($body['station_name'] ?? ''));

        try {
            $this->stationService()->saveStation(
                $esrCode,
                $stationName,
                $body['latitude'] ?? null,
                $body['longitude'] ?? null
            );
        } catch (\Throwable $error) {
            $errorId = ErrorLogger::logThrowable($error, [
                'module' => self::class,
                'function' => 'saveStation',
                'params' => [
                    'esr_code' => $esrCode,
                    'station_name' => $stationName,
                    'latitude' => $body['latitude'] ?? null,
                    'longitude' => $body['longitude'] ?? null,
                ],
            ], $request);
            return $this->redirect(
                $response,
                '/admin/directories/stations?err=' . urlencode($this->cleanDbMessage($error) . '. Код ошибки: ' . $errorId)
            );
        }

        return $this->redirect($response, '/admin/directories/stations?ok=' . urlencode('Станция сохранена'));
    }

    public function deleteStation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/directories/stations?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $esrCode = trim((string) ($body['esr_code'] ?? ''));
        try {
            $this->stationService()->deleteStation($esrCode);
        } catch (\Throwable $error) {
            $errorId = ErrorLogger::logThrowable($error, [
                'module' => self::class,
                'function' => 'deleteStation',
                'params' => ['esr_code' => $esrCode],
            ], $request);
            return $this->redirect(
                $response,
                '/admin/directories/stations?err=' . urlencode($this->cleanDbMessage($error) . '. Код ошибки: ' . $errorId)
            );
        }

        return $this->redirect($response, '/admin/directories/stations?ok=' . urlencode('Станция удалена'));
    }

    public function importStationFromFreicon(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/directories/stations?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $esrCode = trim((string) ($body['esr_code'] ?? ''));

        try {
            $stationFromFreicon = (new FreiconStationClient())->getStation($esrCode);
            $this->stationService()->saveStation(
                $stationFromFreicon['esr_code'],
                $stationFromFreicon['station_name'],
                $stationFromFreicon['latitude'],
                $stationFromFreicon['longitude']
            );
        } catch (\Throwable $error) {
            $errorId = ErrorLogger::logThrowable($error, [
                'module' => self::class,
                'function' => 'importStationFromFreicon',
                'params' => ['esr_code' => $esrCode],
            ], $request);
            return $this->redirect(
                $response,
                '/admin/directories/stations?err=' . urlencode($this->cleanDbMessage($error) . '. Код ошибки: ' . $errorId)
            );
        }

        return $this->redirect(
            $response,
            '/admin/directories/stations?q=' . urlencode($stationFromFreicon['esr_code'])
            . '&ok=' . urlencode('Станция загружена из FreiCON')
        );
    }

    private function savePages(int $roleId, array $pages): void
    {
        foreach ($pages as $page) {
            if (!isset(self::PAGES[$page])) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO xx_rjd_role_pages (role_id, page) VALUES (:id, :page)',
                ['id' => $roleId, 'page' => $page]
            );
        }
    }

    private function stationService(): StationDirectoryService
    {
        return new StationDirectoryService(new StationDirectoryRepository($this->db));
    }

    private function cleanDbMessage(\Throwable $error): string
    {
        $message = $error->getMessage();
        if (preg_match('/ORA-\d+:\s*([^\n]+)/u', $message, $m)) {
            return trim($m[1]);
        }
        return $message !== '' ? $message : 'Операция не выполнена';
    }

    private function isAdmin(): bool
    {
        $u = $_SESSION['user'] ?? [];
        if ($u['is_admin'] ?? false) {
            return true;
        }

        // bootstrap: если admin-ов ещё нет — пускаем, чтобы можно было назначить первого
        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt FROM xx_rjd_user_roles ur
                  JOIN xx_rjd_roles r ON r.id = ur.role_id
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
