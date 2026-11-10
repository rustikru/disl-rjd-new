<?php
declare(strict_types=1);

namespace App\Database;

class DbFactory
{
    public static function create(array $config): DbInterface
    {
        $db = new OracleDb($config);
        $organizationId = (int) ($_SESSION['organization_id'] ?? 0);
        $db->execute('DECLARE
            l_organization_id NUMBER := :organization_id;
        BEGIN
            DBMS_APPLICATION_INFO.SET_CLIENT_INFO(:user_id);
            IF l_organization_id > 0 THEN
                DBMS_SESSION.SET_IDENTIFIER(TO_CHAR(l_organization_id));
            ELSE
                DBMS_SESSION.CLEAR_IDENTIFIER;
            END IF;
        END;', [
            'user_id' => (string) (int) ($_SESSION['user']['id'] ?? 0),
            'organization_id' => (string) $organizationId,
        ]);
        return $db;
    }
}
