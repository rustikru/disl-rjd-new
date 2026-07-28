<?php
declare(strict_types=1);

$configFile = __DIR__ . '/../rjd_config.php';

if (!file_exists($configFile)) {
    throw new RuntimeException('Не найден файл конфигурации rjd_config.php');
}

$config = require $configFile;

if (!is_array($config)) {
    throw new RuntimeException('Файл rjd_config.php должен возвращать массив');
}

$bool = static function (mixed $value): bool {
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
};

return [
    'app_env' => (string) ($config['app_env'] ?? 'development'),
    'app_debug' => $bool($config['app_debug'] ?? false),
    'app_name' => (string) ($config['app_name'] ?? 'АО «Метафракс Кемикалс»'),

    'db_driver' => (string) ($config['db_driver'] ?? 'oracle'),
    'db_host' => (string) ($config['db_host'] ?? 'localhost'),
    'db_port' => (string) ($config['db_port'] ?? '1521'),
    'db_name' => (string) ($config['db_name'] ?? ''),
    'db_user' => (string) ($config['db_user'] ?? ''),
    'db_pass' => (string) ($config['db_pass'] ?? ''),

    'ad_debug' => $bool($config['ad_debug'] ?? false),
    'ad_enabled' => $bool($config['ad_enabled'] ?? false),
    'ad_host' => (string) ($config['ad_host'] ?? ''),
    'ad_domain' => (string) ($config['ad_domain'] ?? ''),
    'ad_base_dn' => (string) ($config['ad_base_dn'] ?? ''),

    'kerberos_enabled' => $bool($config['kerberos_enabled'] ?? false),
    'kerberos_mode' => (string) ($config['kerberos_mode'] ?? 'local'),
    'kerberos_server_key' => (string) ($config['kerberos_server_key'] ?? 'REMOTE_USER'),
    'kerberos_local_username' => (string) ($config['kerberos_local_username'] ?? 'administrator'),
    'kerberos_strip_realm' => $bool($config['kerberos_strip_realm'] ?? true),
    'kerberos_auto_create_user' => $bool($config['kerberos_auto_create_user'] ?? true),

    'auth_log_file' => (string) ($config['auth_log_file'] ?? '/tmp/auth_debug.log'),
    'ldap_log_file' => (string) ($config['ldap_log_file'] ?? '/tmp/ldap_debug.log'),

    'session_name' => (string) ($config['session_name'] ?? 'disl_session'),
    'base_path' => rtrim((string) ($config['base_path'] ?? ''), '/'),
];
