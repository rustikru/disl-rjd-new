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
    private LdapAuth    $ldap;
    private bool        $adEnabled;

    public function __construct(DbInterface $db, array $config)
    {
        $this->db        = $db;
        $this->adEnabled = $config['ad_enabled'];
        $this->ldap      = new LdapAuth($config);
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
                return $adUser;
            }
        }

        // Шаг 2: Локальный пароль из БД
        $user = $this->db->fetchOne(
            'SELECT id, username, display_name, email, password_hash, is_active
             FROM xx_users_rjd WHERE username = :username',
            ['username' => $username]
        );

        if (!$user || !$user['is_active']) {
            return null;
        }

        if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return [
            'id'           => $user['id'],
            'username'     => $user['username'],
            'display_name' => $user['display_name'],
            'email'        => $user['email'],
            'auth_source'  => 'local',
        ];
    }

    /**
     * Установить или сменить локальный пароль пользователя.
     * Вызывается из интерфейса смены пароля.
     */
    public function setPassword(string $username, string $newPassword): void
    {
        $this->db->execute(
            'UPDATE xx_users_rjd SET password_hash = :hash WHERE username = :username',
            [
                'hash'     => password_hash($newPassword, PASSWORD_BCRYPT),
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
            'SELECT id FROM xx_users_rjd WHERE username = :username',
            ['username' => $username]
        );

        if ($exists) {
            return;
        }

        $this->db->execute(
            'INSERT INTO xx_users_rjd (username, display_name, email, password_hash, is_active)
             VALUES (:username, :display_name, :email, :hash, 1)',
            [
                'username'     => $username,
                'display_name' => $displayName,
                'email'        => $email,
                'hash'         => '',
            ]
        );
    }
}
