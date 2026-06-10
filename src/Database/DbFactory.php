<?php
declare(strict_types=1);

namespace App\Database;

/**
 * Создаёт нужное подключение к БД по значению DB_DRIVER в конфиге.
 * Чтобы добавить новую СУБД — создайте класс, реализующий DbInterface,
 * и добавьте его в match ниже.
 */
class DbFactory
{
    public static function create(array $config): DbInterface
    {
        return match ($config['db_driver']) {
            'postgres' => new PostgresDb($config),
            'oracle'   => new OracleDb($config),
            default    => throw new \InvalidArgumentException(
                "Неизвестный драйвер БД: '{$config['db_driver']}'. Используйте 'postgres' или 'oracle'."
            ),
        };
    }
}
