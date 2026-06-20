<?php
declare(strict_types=1);

namespace App\Auth;

use App\Database\DbInterface;

/**
 * Сервис аутентификации.
 *
 * Стратегия входа:
 *  1. Если AD_ENABLED=true — пробуем Active Directory.
 *  2. Если AD недоступен или вернул ошибку — проверяем локальный пароль в БД.
 */
class AuthService
{
    private DbInterface $db;
    private LdapAuth $ldap;
    private bool $adEnabled;

    public function __construct(DbInterface $db, array $config)
    {
        $this->db = $db;
        $this->adEnabled = $config['ad_enabled'];
        $this->ldap = new LdapAuth($config);
    }

    /**
     * Попытка входа. Возвращает массив с данными пользователя или null.
     *
     * @return array<string, mixed>|null
     */
    public function login(string $username, string $password): ?array
    {
        // Шаг 1: Active Directory
        if ($this->adEnabled) {
            $adUser = $this->ldap->authenticate($username, $password);
            if ($adUser !== null) {
                // При первом входе через AD создаём запись в БД
                $this->ensureUserExists($username, $adUser['display_name'], $adUser['email']);
                $dbUser = $this->db->fetchOne(
                    'SELECT id FROM xx_rjd_users WHERE username = :username',
                    ['username' => $username]
                );
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

    /**
     * Создаёт запись пользователя в БД если её ещё нет (для AD-пользователей).
     */
    private function ensureUserExists(string $username, string $displayName, string $email): void
    {
        $exists = $this->db->fetchOne(
            'SELECT id FROM xx_rjd_users WHERE username = :username',
            ['username' => $username]
        );

        if ($exists) {
            return;
        }

        // Новым AD-пользователям назначаем роль «Наблюдатель» (VIEWER) по умолчанию
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

        // Назначаем роль VIEWER через промежуточную таблицу
        $this->db->execute(
            "INSERT INTO xx_rjd_user_roles (user_id, role_id)
             SELECT u.id, r.id
               FROM xx_rjd_users u, xx_rjd_roles r
              WHERE u.username = :username
                AND r.code = 'VIEWER'",
            ['username' => $username]
        );
    }

    /**
     * Загружает роли пользователя по его id.
     *
     * @return array<int, array{id: mixed, code: mixed, name: mixed}>
     */
    private function fetchUserRoles(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT r.id, r.code, r.name
               FROM xx_rjd_roles r
               JOIN xx_rjd_user_roles ur ON ur.role_id = r.id
              WHERE ur.user_id = :uid',
            ['uid' => $userId]
        );
    }
}
