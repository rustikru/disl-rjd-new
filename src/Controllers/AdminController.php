<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use App\Services\AuditLog;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminController
{
    private DbInterface $db;
    private array $config;

    public function directoriesPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canView()) {
            return $this->forbidden($response);
        }

        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];

        ob_start();
        include __DIR__ . '/../../templates/admin/directories.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function __construct(DbInterface $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $_SESSION['user'] ?? [];
        $pages = \App\Services\Authorization::isSuper($user)
            ? array_keys(\App\Services\PageRegistry::labels())
            : (array) ($user['allowed_pages'] ?? []);
        foreach (\App\Services\PageRegistry::all() as $code => $definition) {
            $item = $definition['admin_navigation'] ?? null;
            if ($item && in_array($code, $pages, true)) {
                return $this->redirect($response, (string) $item['url']);
            }
        }
        return $this->forbidden($response);
    }

    public function auditPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }

        $query = $request->getQueryParams();
        $search = trim((string) ($query['q'] ?? ''));
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = 25;
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = " WHERE LOWER(NVL(a.username, '') || ' ' || a.action_code || ' ' || NVL(a.object_type, '') || ' ' || NVL(a.object_id, '') || ' ' || NVL(a.description, '')) LIKE :search";
            $params['search'] = '%' . mb_strtolower($search) . '%';
        }
        $count = $this->db->fetchOne('SELECT COUNT(*) AS cnt FROM xx_rjd_audit_log a' . $where, $params);
        $total = (int) ($count['cnt'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $params['offset_rows'] = ($page - 1) * $perPage;
        $params['limit_rows'] = $perPage;
        $appTimezone = str_replace("'", "''", (string) ($this->config['app_timezone'] ?? 'Asia/Yekaterinburg'));
        $rows = $this->db->fetchAll(
            "SELECT a.id, a.username, a.action_code, a.object_type, a.object_id,
                    a.description, a.ip_address,
                    TO_CHAR(
                        FROM_TZ(CAST(a.created_at AS TIMESTAMP), DBTIMEZONE) AT TIME ZONE '{$appTimezone}',
                        'DD.MM.YYYY HH24:MI:SS'
                    ) AS created_at
               FROM xx_rjd_audit_log a" . $where . "
              ORDER BY a.id DESC
              OFFSET :offset_rows ROWS FETCH NEXT :limit_rows ROWS ONLY",
            $params
        );

        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        ob_start();
        include __DIR__ . '/../../templates/admin/audit.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function systemPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }

        $root = dirname(__DIR__, 2);
        $phpVersion = PHP_VERSION;
        $os = php_uname('s') . ' ' . php_uname('r');
        $timezone = (string) ($this->config['app_timezone'] ?? date_default_timezone_get());
        $environment = (string) ($this->config['app_env'] ?? 'unknown');
        $checkedAt = date('d.m.Y H:i:s');

        $uptimeSeconds = null;
        if (is_readable('/proc/uptime')) {
            $uptimeContent = @file_get_contents('/proc/uptime');
            if ($uptimeContent !== false) {
                $uptimeSeconds = max(0, (int) (float) strtok($uptimeContent, ' '));
            }
        }

        $memory = null;
        if (is_readable('/proc/meminfo')) {
            $memInfo = (string) @file_get_contents('/proc/meminfo');
            preg_match('/^MemTotal:\\s+(\\d+)\\s+kB$/m', $memInfo, $totalMatch);
            preg_match('/^MemAvailable:\\s+(\\d+)\\s+kB$/m', $memInfo, $availableMatch);
            if (!empty($totalMatch[1]) && isset($availableMatch[1])) {
                $total = (int) $totalMatch[1] * 1024;
                $available = (int) $availableMatch[1] * 1024;
                $used = max(0, $total - $available);
                $memory = [
                    'total' => $total,
                    'used' => $used,
                    'available' => $available,
                    'percent' => (int) round($used / $total * 100),
                ];
            }
        }

        $disk = null;
        $diskTotal = @disk_total_space($root);
        $diskFree = @disk_free_space($root);
        if ($diskTotal !== false && $diskFree !== false && $diskTotal > 0) {
            $diskUsed = $diskTotal - $diskFree;
            $disk = [
                'total' => (float) $diskTotal,
                'used' => (float) $diskUsed,
                'free' => (float) $diskFree,
                'percent' => (int) round($diskUsed / $diskTotal * 100),
            ];
        }

        $loadAverage = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $loadAverage = is_array($loadAverage) ? $loadAverage : null;

        $oracleStatus = 'Не проверено';
        $oracleDescription = 'Контрольное подключение к Oracle';
        $oracleState = 'warning';
        $oracleLatency = null;
        try {
            $startedAt = microtime(true);
            $this->db->fetchOne('SELECT 1 AS status FROM DUAL');
            $oracleLatency = (int) round((microtime(true) - $startedAt) * 1000);
            $oracleStatus = 'Подключено';
            $oracleDescription = 'Контрольный запрос выполнен';
            $oracleState = 'ok';
        } catch (\Throwable $error) {
            $oracleStatus = 'Недоступна';
            $oracleDescription = 'Не удалось выполнить контрольный запрос';
            $oracleState = 'error';
        }

        $tempDirectory = sys_get_temp_dir();
        $components = [
            [
                'name' => 'PHP Runtime',
                'description' => 'Среда выполнения PHP',
                'status' => 'ok',
                'label' => 'Работает',
            ],
            [
                'name' => 'Oracle Database',
                'description' => $oracleDescription,
                'status' => $oracleState,
                'label' => $oracleStatus,
            ],
            [
                'name' => 'Расширение OCI8',
                'description' => 'Драйвер подключения PHP к Oracle',
                'status' => extension_loaded('oci8') ? 'ok' : 'error',
                'label' => extension_loaded('oci8') ? 'Доступно' : 'Не установлено',
            ],
            [
                'name' => 'Файловая система',
                'description' => 'Каталог приложения',
                'status' => is_readable($root) ? 'ok' : 'error',
                'label' => is_readable($root) ? 'Доступна' : 'Нет доступа',
            ],
            [
                'name' => 'Временный каталог',
                'description' => $tempDirectory,
                'status' => is_writable($tempDirectory) ? 'ok' : 'error',
                'label' => is_writable($tempDirectory) ? 'Доступен для записи' : 'Нет записи',
            ],
        ];

        $appName = (string) ($this->config['app_name'] ?? 'Дислокация');
        $basePath = (string) ($this->config['base_path'] ?? '');
        $user = $_SESSION['user'] ?? [];

        ob_start();
        include __DIR__ . '/../../templates/admin/system.php';
        $response->getBody()->write((string) ob_get_clean());

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function usersPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $roles = $this->db->fetchAll("SELECT id, code, name FROM xx_rjd_roles WHERE code <> 'SUPER' ORDER BY id");
        $organizations = $this->db->fetchAll(
            'SELECT id, code, name, short_name, is_active
               FROM xx_rjd_organizations
              ORDER BY is_active DESC, name'
        );

        $dateColumns = $this->userDateColumnsAvailable();
        $appTimezone = str_replace("'", "''", (string) ($this->config['app_timezone'] ?? 'Asia/Yekaterinburg'));
        $dateSelect = $dateColumns
            ? ", TO_CHAR((FROM_TZ(CAST(u.created_at AS TIMESTAMP), DBTIMEZONE) AT TIME ZONE '{$appTimezone}'), 'DD.MM.YYYY HH24:MI') AS created_at,
                 TO_CHAR((FROM_TZ(CAST(u.updated_at AS TIMESTAMP), DBTIMEZONE) AT TIME ZONE '{$appTimezone}'), 'DD.MM.YYYY HH24:MI') AS updated_at,
                 TO_CHAR((FROM_TZ(CAST(u.last_login_at AS TIMESTAMP), DBTIMEZONE) AT TIME ZONE '{$appTimezone}'), 'DD.MM.YYYY HH24:MI') AS last_login_at"
            : ', NULL AS created_at, NULL AS updated_at, NULL AS last_login_at';
        $superUserFilter = $this->isSuper() ? '' : " WHERE NOT EXISTS (
            SELECT 1 FROM xx_rjd_user_roles sur
            JOIN xx_rjd_roles sr ON sr.id = sur.role_id
            WHERE sur.user_id = u.id AND sr.code = 'SUPER'
        )";
        $rawUsers = $this->db->fetchAll(
            'SELECT u.id, u.username, u.display_name, u.email, u.is_active, u.organization_id' . $dateSelect . '
               FROM xx_rjd_users u
              ' . $superUserFilter . '
              ORDER BY NLSSORT(u.display_name, \'NLS_SORT=RUSSIAN\'), u.username'
        );

        $userRolesRaw = $this->db->fetchAll(
            'SELECT ur.user_id, r.id, r.code, r.name
               FROM xx_rjd_user_roles ur
               JOIN xx_rjd_roles r ON r.id = ur.role_id
              WHERE r.code <> \'SUPER\''
        );
        $rolesByUser = [];
        foreach ($userRolesRaw as $ur) {
            $rolesByUser[(int) $ur['user_id']][] = [
                'id'   => $ur['id'],
                'code' => $ur['code'],
                'name' => $ur['name'],
            ];
        }

        $organizationsByUser = [];
        try {
            $userOrganizations = $this->db->fetchAll(
                'SELECT user_id, organization_id FROM xx_rjd_user_organizations'
            );
        } catch (\Throwable $error) {
            $userOrganizations = [];
        }
        foreach ($userOrganizations as $row) {
            $organizationsByUser[(int) $row['user_id']][] = (int) $row['organization_id'];
        }

        $users = [];
        foreach ($rawUsers as $u) {
            $u['roles'] = $rolesByUser[(int) $u['id']] ?? [];
            $u['organization_ids'] = $organizationsByUser[(int) $u['id']] ?? [];
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

    public function userEditPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $userId = (int) ($args['id'] ?? 0);
        $dateColumns = $this->userDateColumnsAvailable();
        $appTimezone = str_replace("'", "''", (string) ($this->config['app_timezone'] ?? 'Asia/Yekaterinburg'));
        $dateSelect = $dateColumns
            ? ", TO_CHAR((FROM_TZ(CAST(created_at AS TIMESTAMP), DBTIMEZONE) AT TIME ZONE '{$appTimezone}'), 'DD.MM.YYYY HH24:MI') AS created_at,
                 TO_CHAR((FROM_TZ(CAST(updated_at AS TIMESTAMP), DBTIMEZONE) AT TIME ZONE '{$appTimezone}'), 'DD.MM.YYYY HH24:MI') AS updated_at,
                 TO_CHAR((FROM_TZ(CAST(last_login_at AS TIMESTAMP), DBTIMEZONE) AT TIME ZONE '{$appTimezone}'), 'DD.MM.YYYY HH24:MI') AS last_login_at"
            : ', NULL AS created_at, NULL AS updated_at, NULL AS last_login_at';
        $editedUser = $this->db->fetchOne(
            'SELECT id, username, display_name, email, is_active, organization_id' . $dateSelect . '
               FROM xx_rjd_users WHERE id = :id',
            ['id' => $userId]
        );
        if (!$editedUser) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Пользователь не найден'));
        }
        if (!$this->canManageUser($userId)) {
            return $this->forbidden($response);
        }
        $isNewUser = false;

        $roles = $this->db->fetchAll("SELECT id, code, name FROM xx_rjd_roles WHERE code <> 'SUPER' ORDER BY id");
        $organizations = $this->db->fetchAll(
            'SELECT id, code, name, short_name, is_active FROM xx_rjd_organizations ORDER BY is_active DESC, name'
        );
        $accessGroups = $this->accessGroupsWithRoles();
        $editedUser['role_ids'] = array_map(
            'intval',
            array_column($this->db->fetchAll(
                'SELECT role_id FROM xx_rjd_user_roles WHERE user_id = :id',
                ['id' => $userId]
            ), 'role_id')
        );
        try {
            $editedUser['organization_ids'] = array_map(
                'intval',
                array_column($this->db->fetchAll(
                    'SELECT organization_id FROM xx_rjd_user_organizations WHERE user_id = :id',
                    ['id' => $userId]
                ), 'organization_id')
            );
        } catch (\Throwable $error) {
            $editedUser['organization_ids'] = [];
        }

        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/user-edit.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function userCreatePage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $editedUser = [
            'id' => 0,
            'username' => '',
            'display_name' => '',
            'email' => '',
            'is_active' => 1,
            'organization_id' => 0,
            'organization_ids' => [],
            'role_ids' => [],
            'created_at' => null,
            'updated_at' => null,
            'last_login_at' => null,
        ];
        $isNewUser = true;
        $roles = $this->db->fetchAll("SELECT id, code, name FROM xx_rjd_roles WHERE code <> 'SUPER' ORDER BY id");
        $organizations = $this->db->fetchAll(
            'SELECT id, code, name, short_name, is_active FROM xx_rjd_organizations ORDER BY is_active DESC, name'
        );
        $accessGroups = $this->accessGroupsWithRoles();
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $query = $request->getQueryParams();
        $flashOk = null;
        $flashErr = $query['err'] ?? null;
        $csrf = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/user-edit.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function accessGroupsPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $groups = $this->db->fetchAll(
            'SELECT g.id, g.name, g.description,
                    (SELECT COUNT(*) FROM xx_rjd_access_group_roles gr WHERE gr.access_group_id = g.id) AS role_count
               FROM xx_rjd_access_groups g
              ORDER BY NLSSORT(g.name, \'NLS_SORT=RUSSIAN\')'
        );
        $groupRoles = [];
        foreach ($this->db->fetchAll(
            'SELECT gr.access_group_id, r.name
               FROM xx_rjd_access_group_roles gr
               JOIN xx_rjd_roles r ON r.id = gr.role_id
              ORDER BY r.name'
        ) as $row) {
            $groupRoles[(int) $row['access_group_id']][] = (string) $row['name'];
        }

        $canManageAccessGroups = $this->isSuper();
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;

        ob_start();
        include __DIR__ . '/../../templates/admin/access-groups.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function accessGroupCreatePage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }
        return $this->accessGroupFormPage($request, $response, [
            'id' => 0,
            'name' => '',
            'description' => '',
        ], [], true);
    }

    public function accessGroupEditPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }

        $groupId = (int) ($args['id'] ?? 0);
        $group = $this->db->fetchOne(
            'SELECT id, name, description FROM xx_rjd_access_groups WHERE id = :id',
            ['id' => $groupId]
        );
        if (!$group) {
            return $this->redirect($response, '/admin/access-groups?err=' . urlencode('Группа доступа не найдена'));
        }
        $roleIds = array_map('intval', array_column($this->db->fetchAll(
            'SELECT role_id FROM xx_rjd_access_group_roles WHERE access_group_id = :id',
            ['id' => $groupId]
        ), 'role_id'));
        return $this->accessGroupFormPage($request, $response, $group, $roleIds, false);
    }

    private function accessGroupFormPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $editedAccessGroup,
        array $selectedRoleIds,
        bool $isNewAccessGroup
    ): ResponseInterface {
        $roles = $this->db->fetchAll("SELECT id, code, name FROM xx_rjd_roles WHERE code <> 'SUPER' ORDER BY name");
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/access-group-edit.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function saveAccessGroup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/access-groups?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $groupId = (int) ($body['access_group_id'] ?? 0);
        $name = trim((string) ($body['name'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        $roleIds = $this->allowedRoleIds((array) ($body['role_ids'] ?? []));
        $formUrl = $groupId > 0 ? '/admin/access-groups/' . $groupId . '/edit' : '/admin/access-groups/new';

        if ($name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 500 || $roleIds === []) {
            return $this->redirect($response, $formUrl . '?err=' . urlencode('Укажите название группы и выберите хотя бы одну роль'));
        }
        $duplicate = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_access_groups WHERE LOWER(name) = LOWER(:name) AND (:exclude_id = 0 OR id <> :exclude_id)',
            ['name' => $name, 'exclude_id' => $groupId]
        );
        if ($duplicate) {
            return $this->redirect($response, $formUrl . '?err=' . urlencode('Группа доступа с таким названием уже существует'));
        }

        $this->db->beginTransaction();
        try {
            if ($groupId > 0) {
                $this->db->execute(
                    'UPDATE xx_rjd_access_groups SET name = :name, description = :description WHERE id = :id',
                    ['name' => $name, 'description' => $description !== '' ? $description : null, 'id' => $groupId]
                );
                $exists = $this->db->fetchOne('SELECT id FROM xx_rjd_access_groups WHERE id = :id', ['id' => $groupId]);
                if (!$exists) {
                    throw new \RuntimeException('Access group not found');
                }
                $this->db->execute('DELETE FROM xx_rjd_access_group_roles WHERE access_group_id = :id', ['id' => $groupId]);
            } else {
                $this->db->execute(
                    'INSERT INTO xx_rjd_access_groups (name, description) VALUES (:name, :description)',
                    ['name' => $name, 'description' => $description !== '' ? $description : null]
                );
                $group = $this->db->fetchOne('SELECT id FROM xx_rjd_access_groups WHERE name = :name', ['name' => $name]);
                $groupId = (int) ($group['id'] ?? 0);
                if ($groupId <= 0) {
                    throw new \RuntimeException('Access group ID was not created');
                }
            }
            foreach ($roleIds as $roleId) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_access_group_roles (access_group_id, role_id) VALUES (:group_id, :role_id)',
                    ['group_id' => $groupId, 'role_id' => $roleId]
                );
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            return $this->redirect($response, $formUrl . '?err=' . urlencode('Не удалось сохранить группу доступа'));
        }

        (new AuditLog($this->db))->write(
            $formUrl === '/admin/access-groups/new' ? 'ACCESS_GROUP_CREATE' : 'ACCESS_GROUP_UPDATE',
            'ACCESS_GROUP',
            $groupId,
            'Сохранена группа доступа «' . $name . '»'
        );
        return $this->redirect($response, '/admin/access-groups/' . $groupId . '/edit?ok=' . urlencode('Группа доступа сохранена'));
    }

    public function deleteAccessGroup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/access-groups?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $groupId = (int) ($body['access_group_id'] ?? 0);
        $group = $this->db->fetchOne('SELECT id, name FROM xx_rjd_access_groups WHERE id = :id', ['id' => $groupId]);
        if (!$group) {
            return $this->redirect($response, '/admin/access-groups?err=' . urlencode('Группа доступа не найдена'));
        }
        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM xx_rjd_access_group_roles WHERE access_group_id = :id', ['id' => $groupId]);
            $this->db->execute('DELETE FROM xx_rjd_access_groups WHERE id = :id', ['id' => $groupId]);
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/access-groups?err=' . urlencode('Не удалось удалить группу доступа'));
        }
        (new AuditLog($this->db))->write('ACCESS_GROUP_DELETE', 'ACCESS_GROUP', $groupId, 'Удалена группа доступа «' . (string) $group['name'] . '»');
        return $this->redirect($response, '/admin/access-groups?ok=' . urlencode('Группа доступа удалена'));
    }

    private function accessGroupsWithRoles(): array
    {
        $groups = $this->db->fetchAll('SELECT id, name, description FROM xx_rjd_access_groups ORDER BY name');
        $roles = $this->db->fetchAll(
            'SELECT access_group_id, role_id FROM xx_rjd_access_group_roles ORDER BY role_id'
        );
        $rolesByGroup = [];
        foreach ($roles as $row) {
            $rolesByGroup[(int) $row['access_group_id']][] = (int) $row['role_id'];
        }
        foreach ($groups as &$group) {
            $group['role_ids'] = $rolesByGroup[(int) $group['id']] ?? [];
        }
        unset($group);
        return $groups;
    }

    private function accessGroupRoleIds(int $groupId): ?array
    {
        $group = $this->db->fetchOne('SELECT id FROM xx_rjd_access_groups WHERE id = :id', ['id' => $groupId]);
        if (!$group) {
            return null;
        }
        return $this->allowedRoleIds(array_column($this->db->fetchAll(
            'SELECT role_id FROM xx_rjd_access_group_roles WHERE access_group_id = :id',
            ['id' => $groupId]
        ), 'role_id'));
    }

    public function rolesPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $roles = $this->db->fetchAll(
            "SELECT id, code, name, description, is_system FROM xx_rjd_roles WHERE code <> 'SUPER' ORDER BY id"
        );
        $canManageRoles = $this->isSuper();

        $rolePages = [];
        foreach ($this->db->fetchAll('SELECT role_id, page FROM xx_rjd_role_pages') as $rp) {
            $rolePages[(int) $rp['role_id']][$rp['page']] = true;
        }

        $pages = \App\Services\PageRegistry::labels();

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

    public function roleCreatePage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }
        return $this->roleFormPage($request, $response, [
            'id' => 0,
            'code' => '',
            'name' => '',
            'description' => '',
            'is_system' => 0,
        ], [], true, false);
    }

    public function roleViewPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }
        if ($this->isSuper()) {
            return $this->redirect($response, '/admin/roles/' . (int) ($args['id'] ?? 0) . '/edit');
        }
        $role = $this->db->fetchOne(
            "SELECT id, code, name, description, is_system FROM xx_rjd_roles WHERE id = :id AND code <> 'SUPER'",
            ['id' => (int) ($args['id'] ?? 0)]
        );
        if (!$role) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль не найдена'));
        }
        $selectedPages = \App\Services\PageRegistry::expandPermissions(array_column($this->db->fetchAll(
            'SELECT page FROM xx_rjd_role_pages WHERE role_id = :id',
            ['id' => (int) $role['id']]
        ), 'page'));
        return $this->roleFormPage($request, $response, $role, $selectedPages, false, true);
    }

    public function roleEditPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }
        $role = $this->db->fetchOne(
            "SELECT id, code, name, description, is_system FROM xx_rjd_roles WHERE id = :id AND code <> 'SUPER'",
            ['id' => (int) ($args['id'] ?? 0)]
        );
        if (!$role) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль не найдена'));
        }
        $selectedPages = \App\Services\PageRegistry::expandPermissions(array_column($this->db->fetchAll(
            'SELECT page FROM xx_rjd_role_pages WHERE role_id = :id',
            ['id' => (int) $role['id']]
        ), 'page'));
        return $this->roleFormPage($request, $response, $role, $selectedPages, false, false);
    }

    private function roleFormPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $editedRole,
        array $selectedPages,
        bool $isNewRole,
        bool $isViewOnly
    ): ResponseInterface {
        $pages = \App\Services\PageRegistry::labels();
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/role-edit.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function normsPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canView()) {
            return $this->forbidden($response);
        }
        $organizations = new \App\Services\Organizations($this->db);
        $organizationId = $organizations->id();
        if (!$organizationId) {
            return $this->forbidden($response);
        }
        $service = new \App\Services\ClaimNorms($this->db, $organizationId);
        $canEdit = (new \App\Services\DirectoryAccess($this->db))->canEdit();
        $query = $request->getQueryParams();
        $filterType = (string) ($query['type'] ?? '');
        $filterDate = (string) ($query['date'] ?? '');
        $filterStation = (string) ($query['station'] ?? '');
        $types = \App\Services\ClaimNorms::TYPES;
        $flashOk = $query['ok'] ?? null;
        $flashErr = $request->getAttribute('norm_error');
        try {
            $norms = $service->list(['type' => $filterType, 'date' => $filterDate, 'station' => $filterStation]);
        } catch (\InvalidArgumentException $error) {
            $norms = [];
            $flashErr = $error->getMessage();
        }
        $form = ['id' => '', 'station_code' => '', 'station_name' => '', 'rule_type' => 'SHIPPER', 'rule_value' => '', 'free_days' => 0,
                 'daily_rate' => 0, 'valid_from' => date('Y-m-d'), 'valid_to' => ''];
        if ($request->getAttribute('norm_error') !== null) {
            $form = array_intersect_key((array) $request->getParsedBody(), $form) + $form;
        } elseif ($canEdit && !empty($query['edit'])) {
            $record = $this->db->fetchOne(
                "SELECT id, station_code,
                        (SELECT MIN(station_name) FROM xx_rjd_stations s WHERE s.esr_code = n.station_code) AS station_name,
                        rule_type, rule_value, free_days, daily_rate,
                        TO_CHAR(valid_from, 'YYYY-MM-DD') AS valid_from,
                        TO_CHAR(valid_to, 'YYYY-MM-DD') AS valid_to
                   FROM xx_rjd_claim_norms n WHERE id = :id AND organization_id = :organization_id",
                ['id' => (int) $query['edit'], 'organization_id' => $organizationId]
            );
            if ($record) {
                $form = $record;
            } else {
                $flashErr = 'Норматив не найден';
            }
        }
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $csrf = $_SESSION['csrf_token'] ?? '';
        ob_start();
        include __DIR__ . '/../../templates/admin/norms.php';
        $response->getBody()->write(ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function saveNorm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canEdit()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->forbidden($response);
        }
        $organizationId = (new \App\Services\Organizations($this->db))->id();
        if (!$organizationId) {
            return $this->forbidden($response);
        }
        try {
            (new \App\Services\ClaimNorms($this->db, $organizationId))->save(
                $body, (int) ($_SESSION['user']['id'] ?? 0)
            );
            (new AuditLog($this->db))->write(
                'NORM_SAVE',
                'CLAIM_NORM',
                (int) ($body['id'] ?? 0) ?: null,
                'Норматив сохранён: ' . trim((string) ($body['rule_value'] ?? ''))
            );
        } catch (\InvalidArgumentException $error) {
            return $this->normsPage($request->withAttribute('norm_error', $error->getMessage()), $response)->withStatus(422);
        }
        return $this->redirect($response, '/directories/norms?ok=' . urlencode('Норматив сохранён'));
    }

    /** POST /directories/norms/delete */
    public function deleteNorm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canEdit()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->forbidden($response);
        }
        $organizationId = (new \App\Services\Organizations($this->db))->id();
        if (!$organizationId) {
            return $this->forbidden($response);
        }
        $deleted = $this->db->execute(
            'DELETE FROM xx_rjd_claim_norms WHERE id = :id AND organization_id = :organization_id',
            ['id' => (int) ($body['id'] ?? 0), 'organization_id' => $organizationId]
        );
        if ($deleted > 0) {
            (new AuditLog($this->db))->write('NORM_DELETE', 'CLAIM_NORM', (int) ($body['id'] ?? 0), 'Норматив удалён');
        }
        return $this->redirect($response, '/directories/norms?' . ($deleted ? 'ok=' . urlencode('Норматив удалён') : 'err=' . urlencode('Норматив не найден')));
    }

    public function normValues(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canView()) {
            return $this->forbidden($response);
        }
        $organizationId = (new \App\Services\Organizations($this->db))->id();
        if (!$organizationId) {
            return $this->forbidden($response);
        }
        $params = $request->getQueryParams();
        try {
            $values = (new \App\Services\ClaimNorms($this->db, $organizationId))->values(
                (string) ($params['type'] ?? ''), mb_substr((string) ($params['q'] ?? ''), 0, 100)
            );
        } catch (\InvalidArgumentException $error) {
            return $this->json($response, ['error' => $error->getMessage()], 400);
        }
        return $this->json($response, ['values' => $values]);
    }

    public function normStations(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canView()) {
            return $this->forbidden($response);
        }
        $organizationId = (new \App\Services\Organizations($this->db))->id();
        if (!$organizationId) {
            return $this->forbidden($response);
        }
        $params = $request->getQueryParams();
        $stations = (new \App\Services\ClaimNorms($this->db, $organizationId))->stations(
            mb_substr((string) ($params['q'] ?? ''), 0, 100)
        );
        return $this->json($response, ['stations' => $stations]);
    }

    public function stationsPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canView()) {
            return $this->forbidden($response);
        }

        $canEdit = (new \App\Services\DirectoryAccess($this->db))->canEdit();
        $query = $request->getQueryParams();
        $search = trim((string) ($query['q'] ?? ''));
        $perPage = 50;
        $page = max(1, (int) ($query['page'] ?? 1));
        $countRow = $this->db->fetchOne(
            'SELECT xx_rjd_dislocation_new_pkg.stations_count(:p_search) AS cnt FROM dual',
            ['p_search' => $search !== '' ? $search : null]
        );
        $totalStations = (int) ($countRow['cnt'] ?? 0);
        $totalPages = max(1, (int) ceil($totalStations / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $stations = $this->db->fetchAll(
            'SELECT * FROM TABLE(xx_rjd_dislocation_new_pkg.stations(:p_search, :p_offset, :p_limit))',
            [
                'p_search' => $search !== '' ? $search : null,
                'p_offset' => ($page - 1) * $perPage,
                'p_limit' => $perPage,
            ]
        );

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

    public function stationCreatePage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canEdit()) {
            return $this->forbidden($response);
        }

        return $this->stationFormPage($request, $response, [
            'esr_code' => '',
            'station_name' => '',
            'latitude' => '',
            'longitude' => '',
        ], true);
    }

    public function stationEditPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if (!(new \App\Services\DirectoryAccess($this->db))->canEdit()) {
            return $this->forbidden($response);
        }

        $station = $this->db->fetchOne(
            'SELECT esr_code, station_name, latitude, longitude
               FROM xx_rjd_stations
              WHERE esr_code = :code',
            ['code' => (string) ($args['code'] ?? '')]
        );
        if (!$station) {
            return $this->redirect($response, '/directories/stations?err=' . urlencode('Станция не найдена'));
        }

        return $this->stationFormPage($request, $response, $station, false);
    }

    private function stationFormPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $editedStation,
        bool $isNewStation
    ): ResponseInterface {
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/station-edit.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function organizationsPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $query = $request->getQueryParams();
        $search = trim((string) ($query['q'] ?? ''));
        $params = [];
        $where = '';

        if ($search !== '') {
            $where = "WHERE UPPER(code) LIKE UPPER(:search)
                         OR UPPER(name) LIKE UPPER(:search)
                         OR UPPER(NVL(short_name, '')) LIKE UPPER(:search)";
            $params['search'] = '%' . $search . '%';
        }

        $organizations = $this->db->fetchAll(
            "SELECT id, code, name, short_name, is_active
               FROM xx_rjd_organizations
               $where
              ORDER BY is_active DESC,
                       NLSSORT(name, 'NLS_SORT=RUSSIAN')",
            $params
        );

        $appName  = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user     = $_SESSION['user'] ?? [];
        $flashOk  = $query['ok']  ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf     = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/organizations.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function organizationCreatePage(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        return $this->organizationFormPage($request, $response, [
            'id' => 0,
            'code' => '',
            'name' => '',
            'short_name' => '',
            'is_active' => 1,
        ], true);
    }

    public function organizationEditPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $organization = $this->db->fetchOne(
            'SELECT id, code, name, short_name, is_active
               FROM xx_rjd_organizations
              WHERE id = :id',
            ['id' => (int) ($args['id'] ?? 0)]
        );
        if (!$organization) {
            return $this->redirect($response, '/admin/directories/organizations?err=' . urlencode('Организация не найдена'));
        }

        return $this->organizationFormPage($request, $response, $organization, false);
    }

    private function organizationFormPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $editedOrganization,
        bool $isNewOrganization
    ): ResponseInterface {
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;
        $csrf = $_SESSION['csrf_token'] ?? '';

        ob_start();
        include __DIR__ . '/../../templates/admin/organization-edit.php';
        $response->getBody()->write((string) ob_get_clean());
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
        if (!$this->canManageUser($userId)) {
            return $this->forbidden($response);
        }
        $roleIds = $this->allowedRoleIds($roleIds);
        $keepSuperRole = $this->userHasSuperRole($userId);
        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM xx_rjd_user_roles WHERE user_id = :user_id', ['user_id' => $userId]);
            foreach ($roleIds as $roleId) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                    ['user_id' => $userId, 'role_id' => $roleId]
                );
            }
            if ($keepSuperRole) {
                $this->db->execute(
                    "INSERT INTO xx_rjd_user_roles (user_id, role_id)
                     SELECT :user_id, id FROM xx_rjd_roles WHERE code = 'SUPER'",
                    ['user_id' => $userId]
                );
            }
            $this->db->commit();
            (new AuditLog($this->db))->write('USER_ROLES_UPDATE', 'USER', $userId, 'Изменены роли пользователя');
        } catch (\Throwable $e) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/users?err=' . urlencode('Не удалось обновить роли'));
        }

        return $this->redirect($response, '/admin/users?ok=' . urlencode('Роли обновлены'));
    }

    public function saveUserOrganizations(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $userId = (int) ($body['user_id'] ?? 0);
        $organizationId = (int) ($body['organization_id'] ?? 0);
        $extraIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($body['organization_ids'] ?? [])),
            static fn(int $id): bool => $id > 0 && $id !== $organizationId
        )));

        $organization = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_organizations WHERE id = :id AND is_active = 1',
            ['id' => $organizationId]
        );
        if ($userId <= 0 || !$organization) {
            return $this->redirect($response, '/admin/users?err=' . urlencode('Выберите основную организацию'));
        }
        if (!$this->canManageUser($userId)) {
            return $this->forbidden($response);
        }
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE xx_rjd_users SET organization_id = :organization_id WHERE id = :user_id',
                ['organization_id' => $organizationId, 'user_id' => $userId]
            );
            $this->db->execute(
                'DELETE FROM xx_rjd_user_organizations WHERE user_id = :user_id',
                ['user_id' => $userId]
            );

            foreach ($extraIds as $extraId) {
                $extra = $this->db->fetchOne(
                    'SELECT id FROM xx_rjd_organizations WHERE id = :id AND is_active = 1',
                    ['id' => $extraId]
                );
                if (!$extra) {
                    continue;
                }
                $this->db->execute(
                    'INSERT INTO xx_rjd_user_organizations (user_id, organization_id)
                     VALUES (:user_id, :organization_id)',
                    ['user_id' => $userId, 'organization_id' => $extraId]
                );
            }
            $this->db->commit();
            (new AuditLog($this->db))->write('USER_ORGANIZATIONS_UPDATE', 'USER', $userId, 'Изменены организации пользователя');
        } catch (\Throwable $error) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/users?err=' . urlencode('Не удалось сохранить организации пользователя'));
        }

        return $this->redirect($response, '/admin/users?ok=' . urlencode('Организации пользователя сохранены'));
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
        if (!$this->canManageUser($userId)) {
            return $this->forbidden($response);
        }

        $this->db->execute(
            'UPDATE xx_rjd_users SET is_active = :active WHERE id = :id',
            ['active' => $active, 'id' => $userId]
        );

        (new AuditLog($this->db))->write(
            $active === 1 ? 'USER_ENABLE' : 'USER_DISABLE',
            'USER',
            $userId,
            $active === 1 ? 'Пользователь включён' : 'Пользователь отключён'
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
        $organizationId = (int) ($body['organization_id'] ?? 0);
        $isActive = (int) ($body['is_active'] ?? 0) === 1 ? 1 : 0;
        $accessGroupId = (int) ($body['access_group_id'] ?? 0);
        $roleIds = $accessGroupId > 0 ? $this->accessGroupRoleIds($accessGroupId) : $this->allowedRoleIds((array) ($body['role_ids'] ?? []));
        if ($roleIds === null) {
            return $this->redirect($response, '/admin/users/new?err=' . urlencode('Выбранная группа доступа не найдена'));
        }
        $extraOrganizationIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($body['organization_ids'] ?? [])),
            static fn(int $id): bool => $id > 0 && $id !== $organizationId
        )));

        if ($username === '' || $displayName === '' || $organizationId <= 0) {
            return $this->redirect($response, '/admin/users/new?err=' . urlencode('Укажите логин, имя и организацию пользователя'));
        }

        $exists = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_users WHERE username = :username',
            ['username' => $username]
        );
        if ($exists) {
            return $this->redirect($response, '/admin/users/new?err=' . urlencode('Пользователь с таким логином уже существует'));
        }

        $hash = $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : '';

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'INSERT INTO xx_rjd_users (username, display_name, email, password_hash, is_active, organization_id)
                 VALUES (:username, :display_name, :email, :hash, :is_active, :organization_id)',
                [
                    'username'     => $username,
                    'display_name' => $displayName,
                    'email'        => $email !== '' ? $email : null,
                    'hash'         => $hash,
                    'is_active'    => $isActive,
                    'organization_id' => $organizationId,
                ]
            );

            $newUser = $this->db->fetchOne(
                'SELECT id FROM xx_rjd_users WHERE username = :username',
                ['username' => $username]
            );
            $newUserId = (int) ($newUser['id'] ?? 0);
            if (!empty($roleIds)) {
                foreach ($roleIds as $roleId) {
                    $this->db->execute(
                        'INSERT INTO xx_rjd_user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                        ['user_id' => $newUserId, 'role_id' => $roleId]
                    );
                }
            }
            foreach ($extraOrganizationIds as $extraOrganizationId) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_user_organizations (user_id, organization_id)
                     VALUES (:user_id, :organization_id)',
                    ['user_id' => $newUserId, 'organization_id' => $extraOrganizationId]
                );
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return $this->redirect($response, '/admin/users/new?err=' . urlencode('Не удалось создать пользователя'));
        }

        (new AuditLog($this->db))->write('USER_CREATE', 'USER', $newUserId, 'Создан пользователь ' . $username);

        return $this->redirect($response, '/admin/users/' . $newUserId . '/edit?ok=' . urlencode('Пользователь создан'));
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
        $isActive    = (int) ($body['is_active'] ?? 0) === 1 ? 1 : 0;
        $organizationId = (int) ($body['organization_id'] ?? 0);
        $accessGroupId = (int) ($body['access_group_id'] ?? 0);
        $roleIds = $accessGroupId > 0 ? $this->accessGroupRoleIds($accessGroupId) : $this->allowedRoleIds((array) ($body['role_ids'] ?? []));
        if ($roleIds === null) {
            return $this->redirect($response, '/admin/users/' . $userId . '/edit?err=' . urlencode('Выбранная группа доступа не найдена'));
        }
        $extraOrganizationIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($body['organization_ids'] ?? [])),
            static fn(int $id): bool => $id > 0 && $id !== $organizationId
        )));

        $editUrl = '/admin/users/' . $userId . '/edit';
        if ($userId <= 0 || $username === '' || $displayName === '' || $organizationId <= 0) {
            return $this->redirect($response, $editUrl . '?err=' . urlencode('Укажите логин, имя и основную организацию'));
        }
        if (!$this->canManageUser($userId)) {
            return $this->forbidden($response);
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->redirect($response, $editUrl . '?err=' . urlencode('Укажите корректный e-mail'));
        }

        $conflict = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_users WHERE username = :username AND id != :id',
            ['username' => $username, 'id' => $userId]
        );
        if ($conflict) {
            return $this->redirect($response, $editUrl . '?err=' . urlencode('Пользователь с таким логином уже существует'));
        }

        $organization = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_organizations WHERE id = :id',
            ['id' => $organizationId]
        );
        if (!$organization) {
            return $this->redirect($response, $editUrl . '?err=' . urlencode('Основная организация не найдена'));
        }

        $bindings = [
            'username'     => $username,
            'display_name' => $displayName,
            'email'        => $email !== '' ? $email : null,
            'is_active'    => $isActive,
            'organization_id' => $organizationId,
            'id'           => $userId,
        ];
        $keepSuperRole = $this->userHasSuperRole($userId);
        $this->db->beginTransaction();
        try {
            if ($password !== '') {
                $bindings['hash'] = password_hash($password, PASSWORD_BCRYPT);
                $this->db->execute(
                    'UPDATE xx_rjd_users
                        SET username = :username, display_name = :display_name, email = :email,
                            password_hash = :hash, is_active = :is_active, organization_id = :organization_id
                      WHERE id = :id',
                    $bindings
                );
            } else {
                $this->db->execute(
                    'UPDATE xx_rjd_users
                        SET username = :username, display_name = :display_name, email = :email,
                            is_active = :is_active, organization_id = :organization_id
                      WHERE id = :id',
                    $bindings
                );
            }

            $this->db->execute('DELETE FROM xx_rjd_user_roles WHERE user_id = :id', ['id' => $userId]);
            foreach ($roleIds as $roleId) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_user_roles (user_id, role_id) VALUES (:user_id, :role_id)',
                    ['user_id' => $userId, 'role_id' => $roleId]
                );
            }
            if ($keepSuperRole) {
                $this->db->execute(
                    "INSERT INTO xx_rjd_user_roles (user_id, role_id)
                     SELECT :user_id, id FROM xx_rjd_roles WHERE code = 'SUPER'",
                    ['user_id' => $userId]
                );
            }

            $this->db->execute('DELETE FROM xx_rjd_user_organizations WHERE user_id = :id', ['id' => $userId]);
            foreach ($extraOrganizationIds as $extraOrganizationId) {
                $extraOrganization = $this->db->fetchOne(
                    'SELECT id FROM xx_rjd_organizations WHERE id = :id',
                    ['id' => $extraOrganizationId]
                );
                if ($extraOrganization) {
                    $this->db->execute(
                        'INSERT INTO xx_rjd_user_organizations (user_id, organization_id)
                         VALUES (:user_id, :organization_id)',
                        ['user_id' => $userId, 'organization_id' => $extraOrganizationId]
                    );
                }
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            return $this->redirect($response, $editUrl . '?err=' . urlencode('Не удалось сохранить пользователя'));
        }

        (new AuditLog($this->db))->write(
            'USER_UPDATE',
            'USER',
            $userId,
            'Изменён пользователь ' . $username . ($password !== '' ? '; изменён пароль' : '')
        );

        return $this->redirect($response, $editUrl . '?ok=' . urlencode('Пользователь сохранён'));
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
        if (!$this->canManageUser($userId)) {
            return $this->forbidden($response);
        }

        $this->db->execute(
            'UPDATE xx_rjd_users SET password_hash = :hash WHERE id = :id',
            ['hash' => password_hash($password, PASSWORD_BCRYPT), 'id' => $userId]
        );

        (new AuditLog($this->db))->write('PASSWORD_CHANGE', 'USER', $userId, 'Пароль изменён администратором');

        return $this->redirect($response, '/admin/users?ok=' . urlencode('Пароль обновлён'));
    }

    public function createRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/roles/new?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        $name = trim((string) ($body['name'] ?? ''));
        $desc = trim((string) ($body['description'] ?? ''));

        if ($code === '' || $name === '') {
            return $this->redirect($response, '/admin/roles/new?err=' . urlencode('Укажите код и название роли'));
        }
        if (!preg_match('/^[A-Z][A-Z0-9_]{1,29}$/', $code)) {
            return $this->redirect($response, '/admin/roles/new?err=' . urlencode('Код роли: латиница, цифры и _, начинается с буквы'));
        }

        $exists = $this->db->fetchOne('SELECT id FROM xx_rjd_roles WHERE code = :code', ['code' => $code]);
        if ($exists) {
            return $this->redirect($response, '/admin/roles/new?err=' . urlencode('Роль с таким кодом уже существует'));
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
            return $this->redirect($response, '/admin/roles/new?err=' . urlencode('Не удалось создать роль'));
        }

        (new AuditLog($this->db))->write('ROLE_CREATE', 'ROLE', (int) $row['id'], 'Создана роль ' . $code . ' — ' . $name);

        return $this->redirect($response, '/admin/roles/' . (int) $row['id'] . '/edit?ok=' . urlencode('Роль создана'));
    }

    public function saveRolePages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
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

        $role = $this->db->fetchOne("SELECT id, code FROM xx_rjd_roles WHERE id = :id AND code <> 'SUPER'", ['id' => $roleId]);
        if (!$role) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Роль не найдена'));
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
            return $this->redirect($response, '/admin/roles/' . $roleId . '/edit?err=' . urlencode('Не удалось сохранить роль'));
        }

        (new AuditLog($this->db))->write('ROLE_UPDATE', 'ROLE', $roleId, 'Роль изменена: ' . ($name !== '' ? $name : (string) $role['code']));

        return $this->redirect($response, '/admin/roles/' . $roleId . '/edit?ok=' . urlencode('Роль обновлена'));
    }

    public function deleteRole(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isSuper()) {
            return $this->forbidden($response);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/roles?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $roleId = (int) ($body['role_id'] ?? 0);
        $role = $this->db->fetchOne("SELECT id, code FROM xx_rjd_roles WHERE id = :id AND code <> 'SUPER'", ['id' => $roleId]);
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
        (new AuditLog($this->db))->write('ROLE_DELETE', 'ROLE', $roleId, 'Удалена роль ' . (string) ($role['code'] ?? ''));

        return $this->redirect($response, '/admin/roles?ok=' . urlencode('Роль удалена'));
    }

    public function saveStation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canEdit()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/directories/stations?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $esrCode = trim((string) ($body['esr_code'] ?? ''));
        $stationName = trim((string) ($body['station_name'] ?? ''));
        $isNew = (string) ($body['is_new'] ?? '') === '1';
        $formPath = $isNew || $esrCode === ''
            ? '/directories/stations/new'
            : '/directories/stations/' . rawurlencode($esrCode) . '/edit';

        if ($esrCode === '' || $stationName === '') {
            return $this->redirect($response, $formPath . '?err=' . urlencode('Укажите код ЕСР и название станции'));
        }

        try {
            $latitude = $this->coordinateOrNull($body['latitude'] ?? null, 'широта', -90, 90);
            $longitude = $this->coordinateOrNull($body['longitude'] ?? null, 'долгота', -180, 180);

            $this->db->execute(
                'BEGIN xx_rjd_dislocation_new_pkg.save_station(:p_esr_code, :p_station_name, :p_latitude, :p_longitude); END;',
                [
                    'p_esr_code' => $esrCode,
                    'p_station_name' => $stationName,
                    'p_latitude' => $latitude,
                    'p_longitude' => $longitude,
                ]
            );
            (new AuditLog($this->db))->write(
                $isNew ? 'STATION_CREATE' : 'STATION_UPDATE',
                'STATION',
                $esrCode,
                ($isNew ? 'Создана станция: ' : 'Изменена станция: ') . $stationName
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
                $formPath . '?err=' . urlencode($this->cleanDbMessage($error) . '. Код ошибки: ' . $errorId)
            );
        }

        return $this->redirect(
            $response,
            '/directories/stations/' . rawurlencode($esrCode) . '/edit?ok=' . urlencode($isNew ? 'Станция создана' : 'Станция сохранена')
        );
    }

    public function deleteStation(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!(new \App\Services\DirectoryAccess($this->db))->canEdit()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/directories/stations?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $esrCode = trim((string) ($body['esr_code'] ?? ''));
        try {
            $this->db->execute(
                'BEGIN xx_rjd_dislocation_new_pkg.delete_station(:p_esr_code); END;',
                ['p_esr_code' => $esrCode]
            );
            (new AuditLog($this->db))->write('STATION_DELETE', 'STATION', $esrCode, 'Станция удалена');
        } catch (\Throwable $error) {
            $errorId = ErrorLogger::logThrowable($error, [
                'module' => self::class,
                'function' => 'deleteStation',
                'params' => ['esr_code' => $esrCode],
            ], $request);
            return $this->redirect(
                $response,
                '/directories/stations?err=' . urlencode($this->cleanDbMessage($error) . '. Код ошибки: ' . $errorId)
            );
        }

        return $this->redirect($response, '/directories/stations?ok=' . urlencode('Станция удалена'));
    }

    public function saveOrganization(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/directories/organizations?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $id = (int) ($body['id'] ?? 0);
        $isNew = $id <= 0;
        $formPath = $id > 0
            ? '/admin/directories/organizations/' . $id . '/edit'
            : '/admin/directories/organizations/new';
        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        $name = trim((string) ($body['name'] ?? ''));
        $shortName = trim((string) ($body['short_name'] ?? ''));
        $isActive = isset($body['is_active']) && (string) $body['is_active'] === '1' ? 1 : 0;

        if ($code === '' || $name === '') {
            return $this->redirect($response, $formPath . '?err=' . urlencode('Укажите код и наименование организации'));
        }
        if (!preg_match('/^[A-Z][A-Z0-9_]{0,49}$/D', $code)) {
            return $this->redirect($response, $formPath . '?err=' . urlencode('Код: латиница, цифры и _, начинается с буквы'));
        }

        $sameCode = $this->db->fetchOne(
            'SELECT id
               FROM xx_rjd_organizations
              WHERE code = :code
                AND (:id = 0 OR id != :id)',
            ['code' => $code, 'id' => $id]
        );
        if ($sameCode) {
            return $this->redirect($response, $formPath . '?err=' . urlencode('Организация с таким кодом уже существует'));
        }

        try {
            if ($id > 0) {
                $organization = $this->db->fetchOne(
                    'SELECT id FROM xx_rjd_organizations WHERE id = :id',
                    ['id' => $id]
                );
                if (!$organization) {
                    return $this->redirect($response, '/admin/directories/organizations?err=' . urlencode('Организация не найдена'));
                }

                $this->db->execute(
                    'UPDATE xx_rjd_organizations
                        SET code = :code,
                            name = :name,
                            short_name = :short_name,
                            is_active = :is_active,
                            updated_at = SYSDATE
                      WHERE id = :id',
                    [
                        'code' => $code,
                        'name' => $name,
                        'short_name' => $shortName !== '' ? $shortName : null,
                        'is_active' => $isActive,
                        'id' => $id,
                    ]
                );
            } else {
                $this->db->execute(
                    'INSERT INTO xx_rjd_organizations (code, name, short_name, is_active)
                     VALUES (:code, :name, :short_name, :is_active)',
                    [
                        'code' => $code,
                        'name' => $name,
                        'short_name' => $shortName !== '' ? $shortName : null,
                        'is_active' => $isActive,
                    ]
                );
                $created = $this->db->fetchOne(
                    'SELECT id FROM xx_rjd_organizations WHERE code = :code',
                    ['code' => $code]
                );
                $id = (int) ($created['id'] ?? 0);
            }
        } catch (\Throwable $error) {
            $errorId = ErrorLogger::logThrowable($error, [
                'module' => self::class,
                'function' => 'saveOrganization',
                'params' => [
                    'id' => $id,
                    'code' => $code,
                    'name' => $name,
                    'short_name' => $shortName,
                ],
            ], $request);
            return $this->redirect(
                $response,
                $formPath . '?err=' . urlencode($this->cleanDbMessage($error) . '. Код ошибки: ' . $errorId)
            );
        }

        (new AuditLog($this->db))->write(
            $isNew ? 'ORGANIZATION_CREATE' : 'ORGANIZATION_UPDATE',
            'ORGANIZATION',
            $id,
            ($isNew ? 'Создана организация: ' : 'Изменена организация: ') . $code . ' — ' . $name
        );

        return $this->redirect(
            $response,
            '/admin/directories/organizations/' . $id . '/edit?ok=' . urlencode($isNew ? 'Организация создана' : 'Организация сохранена')
        );
    }

    public function toggleOrganization(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $this->forbidden($response);
        }

        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/admin/directories/organizations?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $id = (int) ($body['id'] ?? 0);
        $active = (int) ($body['is_active'] ?? 0) === 1 ? 1 : 0;
        if ($id <= 0) {
            return $this->redirect($response, '/admin/directories/organizations?err=' . urlencode('Организация не найдена'));
        }

        try {
            $this->db->execute(
                'UPDATE xx_rjd_organizations
                    SET is_active = :active,
                        updated_at = SYSDATE
                  WHERE id = :id',
                ['active' => $active, 'id' => $id]
            );
        } catch (\Throwable $error) {
            $errorId = ErrorLogger::logThrowable($error, [
                'module' => self::class,
                'function' => 'toggleOrganization',
                'params' => ['id' => $id, 'is_active' => $active],
            ], $request);
            return $this->redirect(
                $response,
                '/admin/directories/organizations?err=' . urlencode($this->cleanDbMessage($error) . '. Код ошибки: ' . $errorId)
            );
        }

        $message = $active === 1 ? 'Организация включена' : 'Организация отключена';
        (new AuditLog($this->db))->write(
            $active === 1 ? 'ORGANIZATION_ENABLE' : 'ORGANIZATION_DISABLE',
            'ORGANIZATION',
            $id,
            $message
        );
        return $this->redirect($response, '/admin/directories/organizations?ok=' . urlencode($message));
    }

    private function savePages(int $roleId, array $pages): void
    {
        foreach ($pages as $page) {
            $definition = \App\Services\PageRegistry::all()[$page] ?? null;
            if ($definition === null || !empty($definition['derived'])) {
                continue;
            }
            $this->db->execute(
                'INSERT INTO xx_rjd_role_pages (role_id, page) VALUES (:id, :page)',
                ['id' => $roleId, 'page' => $page]
            );
        }
    }

    private function coordinateOrNull(mixed $value, string $fieldName, float $min, float $max): ?string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '') {
            return null;
        }

        if (!is_numeric($text)) {
            throw new \InvalidArgumentException('Поле "' . $fieldName . '" должно быть числом');
        }

        $number = (float) $text;
        if ($number < $min || $number > $max) {
            throw new \InvalidArgumentException(
                'Поле "' . $fieldName . '" должно быть в диапазоне от ' . $min . ' до ' . $max
            );
        }

        return $text;
    }

    private function cleanDbMessage(\Throwable $error): string
    {
        $message = $error->getMessage();
        if (preg_match('/ORA-\d+:\s*([^\n]+)/u', $message, $m)) {
            return trim($m[1]);
        }
        return $message !== '' ? $message : 'Операция не выполнена';
    }

    private function userDateColumnsAvailable(): bool
    {
        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt
                   FROM user_tab_columns
                  WHERE table_name = 'XX_RJD_USERS'
                    AND column_name IN ('CREATED_AT', 'UPDATED_AT', 'LAST_LOGIN_AT')"
            );
            return (int) ($row['cnt'] ?? 0) === 3;
        } catch (\Throwable $error) {
            return false;
        }
    }

    private function isAdmin(): bool
    {
        $u = $_SESSION['user'] ?? [];
        if (\App\Services\Authorization::isAdmin($u)) {
            return true;
        }

        // Если администраторов ещё нет, разрешаем назначить первого.
        try {
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) AS cnt FROM xx_rjd_user_roles ur
                  JOIN xx_rjd_roles r ON r.id = ur.role_id
                 WHERE r.code IN ('ADMIN', 'SUPER')"
            );
            return (int) ($row['cnt'] ?? 0) === 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function isSuper(): bool
    {
        return \App\Services\Authorization::isSuper();
    }

    private function canManageUser(int $userId): bool
    {
        if ($userId <= 0 || $this->isSuper()) {
            return $userId > 0;
        }

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
               FROM xx_rjd_user_roles ur
               JOIN xx_rjd_roles r ON r.id = ur.role_id
              WHERE ur.user_id = :user_id AND r.code = 'SUPER'",
            ['user_id' => $userId]
        );
        return (int) ($row['cnt'] ?? 0) === 0;
    }

    private function userHasSuperRole(int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt
               FROM xx_rjd_user_roles ur
               JOIN xx_rjd_roles r ON r.id = ur.role_id
              WHERE ur.user_id = :user_id AND r.code = 'SUPER'",
            ['user_id' => $userId]
        );
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    private function allowedRoleIds(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            return [];
        }

        $allowed = [];
        foreach ($roleIds as $roleId) {
            if ($roleId <= 0) {
                continue;
            }
            $role = $this->db->fetchOne(
                "SELECT id FROM xx_rjd_roles WHERE id = :id AND code <> 'SUPER'",
                ['id' => $roleId]
            );
            if ($role) {
                $allowed[] = $roleId;
            }
        }
        return $allowed;
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

    private function json(
        ResponseInterface $response,
        array $data,
        int $status = 200
    ): ResponseInterface {
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        $base = $this->config['base_path'] ?? '';
        return $response->withHeader('Location', $base . $url)->withStatus(302);
    }
}
