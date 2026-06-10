<?php
declare(strict_types=1);

namespace App\Auth;

/**
 * Аутентификация через Active Directory по протоколу LDAP.
 * Требуется PHP-расширение ldap (php-ldap).
 */
class LdapAuth
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Пробуем войти в AD с указанными логином и паролем.
     * При успехе возвращаем массив с атрибутами пользователя.
     * При неудаче возвращаем null.
     */
    public function authenticate(string $username, string $password): ?array
    {
        if (!extension_loaded('ldap') || empty($this->config['ad_host'])) {
            return null;
        }

        $connection = @ldap_connect($this->config['ad_host']);
        if (!$connection) {
            return null;
        }

        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        // AD принимает логин в формате user@domain.local (UPN)
        $userPrincipal = $username . '@' . $this->config['ad_domain'];

        $bound = @ldap_bind($connection, $userPrincipal, $password);
        if (!$bound) {
            ldap_close($connection);
            return null;
        }

        // Получаем атрибуты пользователя
        $search  = @ldap_search(
            $connection,
            $this->config['ad_base_dn'],
            '(sAMAccountName=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . ')',
            ['displayName', 'mail']
        );

        $displayName = $username;
        $email       = '';

        if ($search) {
            $entries = ldap_get_entries($connection, $search);
            if ($entries['count'] > 0) {
                $displayName = $entries[0]['displayname'][0] ?? $username;
                $email       = $entries[0]['mail'][0] ?? '';
            }
        }

        ldap_close($connection);

        return [
            'username'     => $username,
            'display_name' => $displayName,
            'email'        => $email,
            'auth_source'  => 'active_directory',
        ];
    }
}
