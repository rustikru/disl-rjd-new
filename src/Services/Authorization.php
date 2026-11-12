<?php
declare(strict_types=1);
namespace App\Services;

use App\Database\DbInterface;

/** Единая семантика системных ролей; серверные policy-проверки остаются обязательными. */
final class Authorization
{
    public const ADMIN='ADMIN';
    public const SUPER='SUPER';

    public static function flags(array $roleCodes): array
    {
        return ['is_admin'=>in_array(self::ADMIN,$roleCodes,true)||in_array(self::SUPER,$roleCodes,true),'is_super'=>in_array(self::SUPER,$roleCodes,true)];
    }
    public static function isAdmin(?array $user=null): bool { $user??=$_SESSION['user']??[]; return !empty($user['is_admin']); }
    public static function isSuper(?array $user=null): bool { $user??=$_SESSION['user']??[]; return !empty($user['is_super']); }

    public static function hasAdministrators(DbInterface $db): bool
    {
        $row = $db->fetchOne("SELECT COUNT(*) AS cnt FROM xx_rjd_user_roles ur
            JOIN xx_rjd_roles r ON r.id = ur.role_id
           WHERE r.code IN ('ADMIN', 'SUPER')");
        return (int) ($row['cnt'] ?? 0) > 0;
    }
}
