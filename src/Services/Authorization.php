<?php
declare(strict_types=1);
namespace App\Services;

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
}
