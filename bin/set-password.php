<?php
/**
 * Смена пароля существующего пользователя.
 *   php bin/set-password.php <username> <новый_пароль>
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../src/Config.php';

$args = array_slice($argv, 1);

if (count($args) < 2) {
    fwrite(STDERR, "Использование: php bin/set-password.php <username> <новый_пароль>\n");
    exit(1);
}

[$username, $password] = $args;
/* 
if (strlen($password) < 6) {
    fwrite(STDERR, "Ошибка: пароль должен быть не короче 6 символов.\n");
    exit(1);
} */

$db = \App\Database\DbFactory::create($config);

$user = $db->fetchOne(
    'SELECT id FROM users WHERE username = :username',
    ['username' => $username]
);

if (!$user) {
    fwrite(STDERR, "Ошибка: пользователь «{$username}» не найден.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

$db->execute(
    'UPDATE users SET password_hash = :hash WHERE username = :username',
    ['hash' => $hash, 'username' => $username]
);

echo "Пароль пользователя «{$username}» обновлён.\n";
