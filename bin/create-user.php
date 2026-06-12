<?php
/**
 * Создание локального пользователя.
 *
 * Использование:
 *   php bin/create-user.php <username> <display_name> <email> <password>
 *
 * Примеры:
 *   php bin/create-user.php admin "Администратор" admin@company.local secret123
 *   php bin/create-user.php ivan  "Иван Петров"   ivan@company.local qwerty
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../src/Config.php';

// ── Аргументы ─────────────────────────────────────────────────────────────────

$args = array_slice($argv, 1);

if (count($args) < 4) {
    fwrite(STDERR, "Использование: php bin/create-user.php <username> <display_name> <email> <password>\n");
    exit(1);
}

[$username, $displayName, $email, $password] = $args;

if (strlen($password) < 6) {
    fwrite(STDERR, "Ошибка: пароль должен быть не короче 6 символов.\n");
    exit(1);
}

// ── БД ────────────────────────────────────────────────────────────────────────

$db = \App\Database\DbFactory::create($config);

$existing = $db->fetchOne(
    'SELECT id FROM users WHERE username = :username',
    ['username' => $username]
);

if ($existing) {
    fwrite(STDERR, "Ошибка: пользователь «{$username}» уже существует.\n");
    fwrite(STDERR, "Для смены пароля используйте: php bin/set-password.php {$username} <новый_пароль>\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

$db->execute(
    'INSERT INTO users (username, display_name, email, password_hash, is_active)
     VALUES (:username, :display_name, :email, :hash, 1)',
    [
        'username'     => $username,
        'display_name' => $displayName,
        'email'        => $email,
        'hash'         => $hash,
    ]
);

echo "Пользователь создан:\n";
echo "  Логин:    {$username}\n";
echo "  Имя:      {$displayName}\n";
echo "  Email:    {$email}\n";
echo "  Пароль:   (bcrypt, cost=10)\n";
