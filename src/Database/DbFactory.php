<?php
declare(strict_types=1);

namespace App\Database;

class DbFactory
{
    public static function create(array $config): DbInterface
    {
        $db = new OracleDb($config);
        $db->execute('BEGIN DBMS_APPLICATION_INFO.SET_CLIENT_INFO(:user_id); END;', [
            'user_id' => (string) (int) ($_SESSION['user']['id'] ?? 0),
        ]);
        return $db;
    }
}
