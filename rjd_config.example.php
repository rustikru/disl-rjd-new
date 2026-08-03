<?php
declare(strict_types=1);

return [
    'app_env' => 'development',
    'app_debug' => false,
    'app_name' => 'АО Метафракс-Кемикалс',

    'db_driver' => 'oracle',
    'db_host' => 'localhost',
    'db_port' => '1521',
    'db_name' => 'FREEPDB1',
    'db_user' => 'xx_etw',
    'db_pass' => '',

    'ad_debug' => false,
    'ad_enabled' => false,
    'ad_host' => 'ldap://172.16.0.192:389',
    'ad_domain' => 'mf.metafrax.ru',
    'ad_base_dn' => 'DC=mf,DC=metafrax,DC=ru',

    // Локальная имитация Kerberos. На PROD: kerberos_mode => 'server'.
    'kerberos_enabled' => true,
    'kerberos_mode' => 'local',
    'kerberos_server_key' => 'REMOTE_USER',
    'kerberos_local_username' => 'user1',
    'kerberos_strip_realm' => true,
    'kerberos_auto_create_user' => true,

    'auth_log_file' => '/tmp/auth_debug.log',
    'ldap_log_file' => '/tmp/ldap_debug.log',

    'session_name' => 'disl_session',
    'base_path' => '',
];
