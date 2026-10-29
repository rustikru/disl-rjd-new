<?php
declare(strict_types=1);

namespace App\Auth;

use App\Database\DbInterface;
use App\Services\AuditLog;

// AD → fallback на локальный пароль
class AuthService
{
    private DbInterface $db;
    private LdapAuth $ldap;
    private bool $adEnabled;
    private bool $kerberosAutoCreateUser;

    public function __construct(DbInterface $db, array $config)
    {
        $this->db = $db;
        $this->adEnabled = $config['ad_enabled'];
        $this->kerberosAutoCreateUser = (bool) ($config['kerberos_auto_create_user'] ?? true);
        $this->ldap = new LdapAuth($config);
    }

    public function loginKerberos(array $identity): ?array
    {
        $username = trim((string) ($identity['username'] ?? ''));
        if ($username === '') {
            return null;
        }

        $user = $this->db->fetchOne(
            'SELECT id, username, display_name, email, is_active
               FROM xx_rjd_users
              WHERE username = :username',
            ['username' => $username]
        );

        if (!$user && $this->kerberosAutoCreateUser) {
            $this->ensureUserExists(
                $username,
                (string) ($identity['display_name'] ?? $username),
                (string) ($identity['email'] ?? '')
            );
            $user = $this->db->fetchOne(
                'SELECT id, username, display_name, email, is_active
                   FROM xx_rjd_users
                  WHERE username = :username',
                ['username' => $username]
            );
        }

        if (!$user || !$user['is_active']) {
            return null;
        }

        $this->markSuccessfulLogin((int) $user['id']);
        (new AuditLog($this->db))->write('LOGIN', 'USER', (int) $user['id'], 'Вход через Kerberos', (int) $user['id'], (string) $user['username']);
        $roles = $this->fetchUserRoles((int) $user['id']);
        $roleCodes = array_column($roles, 'code');

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'email' => $user['email'],
            'auth_source' => 'kerberos',
            'role_codes' => $roleCodes,
            'role_names' => array_column($roles, 'name'),
            'is_admin' => in_array('ADMIN', $roleCodes, true),
        ];
    }

    public function login(string $username, string $password): ?array
    {
        // Шаг 1: Active Directory
        if ($this->adEnabled) {
            $adUser = $this->ldap->authenticate($username, $password);
            if ($adUser !== null) {
                // При первом входе через AD создаём запись в БД
                $this->ensureUserExists($username, $adUser['display_name'], $adUser['email']);
                $dbUser = $this->db->fetchOne(
                    'SELECT id, username, display_name, email FROM xx_rjd_users WHERE username = :username',
                    ['username' => $username]
                );
                $this->markSuccessfulLogin((int) ($dbUser['id'] ?? 0));
                (new AuditLog($this->db))->write('LOGIN', 'USER', (int) ($dbUser['id'] ?? 0), 'Вход через Active Directory', (int) ($dbUser['id'] ?? 0), (string) ($dbUser['username'] ?? $username));
                $adUser['id'] = $dbUser['id'] ?? null;
                $adUser['username'] = $dbUser['username'] ?? $username;
                $adUser['display_name'] = $dbUser['display_name'] ?? $adUser['display_name'];
                $adUser['email'] = $dbUser['email'] ?? $adUser['email'];
                $roles = $this->fetchUserRoles((int) ($dbUser['id'] ?? 0));
                $adUser['role_codes'] = array_column($roles, 'code');
                $adUser['role_names'] = array_column($roles, 'name');
                $adUser['is_admin']   = in_array('ADMIN', $adUser['role_codes'], true);
                return $adUser;
            }
        }


        $user = $this->db->fetchOne(
            'SELECT u.id, u.username, u.display_name, u.email, u.password_hash, u.is_active
             FROM xx_rjd_users u
             WHERE u.username = :username',
            ['username' => $username]
        );

        if (!$user || !$user['is_active']) {
            return null;
        }

        if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        $roles = $this->fetchUserRoles((int) $user['id']);
        $this->markSuccessfulLogin((int) $user['id']);
        (new AuditLog($this->db))->write('LOGIN', 'USER', (int) $user['id'], 'Вход по локальному паролю', (int) $user['id'], (string) $user['username']);

        return [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'email'        => $user['email'],
            'auth_source'  => 'local',
            'role_codes'   => array_column($roles, 'code'),
            'role_names'   => array_column($roles, 'name'),
            'is_admin'     => in_array('ADMIN', array_column($roles, 'code'), true),
        ];
    }


    public function setPassword(string $username, string $newPassword): void
    {
        $this->db->execute(
            'UPDATE xx_rjd_users SET password_hash = :hash WHERE username = :username',
            [
                'hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                'username' => $username,
            ]
        );
    }

    private function ensureUserExists(string $username, string $displayName, string $email): void
    {
        $exists = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_users WHERE username = :username',
            ['username' => $username]
        );

        if ($exists) {
            return;
        }

        // без роли — admin назначит через /admin/users, иначе сразу 403
        $this->db->execute(
            'INSERT INTO xx_rjd_users (username, display_name, email, password_hash, is_active)
             VALUES (:username, :display_name, :email, :hash, 1)',
            [
                'username'     => $username,
                'display_name' => $displayName,
                'email'        => $email,
                'hash'         => '',
            ]
        );
    }

    private function fetchUserRoles(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT r.id, r.code, r.name
               FROM xx_rjd_roles r
               JOIN xx_rjd_user_roles ur ON ur.role_id = r.id
              WHERE ur.user_id = :user_id',
            ['user_id' => $userId]
        );
    }

    private function markSuccessfulLogin(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            $this->db->execute(
                'UPDATE xx_rjd_users SET last_login_at = SYSDATE WHERE id = :id',
                ['id' => $userId]
            );
        } catch (\Throwable $error) {
            // До применения миграции отсутствие служебной даты не должно мешать входу.
        }
    }
}
