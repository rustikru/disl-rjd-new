<?php
declare(strict_types=1);

namespace App\Database;

class DbFactory
{
    public static function create(array $config): DbInterface
    {
        return new OracleDb($config);
    }
}
