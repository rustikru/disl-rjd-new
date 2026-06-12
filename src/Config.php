<?php
declare(strict_types=1);

// Загружаем .env файл 
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strncmp(trim($line), '#', 1) === 0) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $_ENV[trim($key)] = trim($value);
    }
}

/**
 * Читает переменную окружения, возвращает $default если не задана.
 */
function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? ((string) getenv($key)) ?: $default;
}

return [
    'app_env' => env('APP_ENV', 'development'), //
    'app_name' => env('APP_NAME', 'АО «Метафракс Кемикалс»'),


    'db_driver' => env('DB_DRIVER', 'oracle'), // 'oracle' или 'pgsql'
    'db_host' => env('DB_HOST', 'localhost'),
    'db_port' => env('DB_PORT', '5432'),
    'db_name' => env('DB_NAME', 'disl_rzd'),
    'db_user' => env('DB_USER', ''),
    'db_pass' => env('DB_PASS', ''),

    // AD_ENABLED=true → сначала проверяем AD, затем локальный пароль
    'ad_enabled' => env('AD_ENABLED', 'false') === 'true',
    'ad_host' => env('AD_HOST', ''),
    'ad_domain' => env('AD_DOMAIN', ''),
    'ad_base_dn' => env('AD_BASE_DN', ''),

    'session_name' => env('SESSION_NAME', 'disl_session'),

    // Базовый путь приложение 
    'base_path' => env('APP_BASE_PATH', ''),
];
