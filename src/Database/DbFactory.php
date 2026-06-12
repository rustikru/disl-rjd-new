<?php
declare(strict_types=1);

namespace App\Database;

class DbFactory
{
    public static function create(array $config): DbInterface
    {
        if ($config['db_driver'] === 'postgres') {
            return new PostgresDb($config);
        }
        if ($config['db_driver'] === 'oracle') {
            return new OracleDb($config);
        }
        throw new \InvalidArgumentException(
            "Неизвестный драйвер БД: '{$config['db_driver']}'."
        );
    }
}
