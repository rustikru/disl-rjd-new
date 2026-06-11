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
        if ($config['db_driver'] === 'postgres') { return new PostgresDb($config); }
        if ($config['db_driver'] === 'oracle')   { return new OracleDb($config); }
        throw new \InvalidArgumentException(
            "Неизвестный драйвер БД: '{$config['db_driver']}'. Используйте 'postgres' или 'oracle'."
        );
    }
}
