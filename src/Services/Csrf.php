<?php
declare(strict_types=1);

namespace App\Services;

final class Csrf
{
    public static function valid(string $token): bool
    {
        $expected = (string) ($_SESSION['csrf_token'] ?? '');
        return $token !== '' && $expected !== '' && hash_equals($expected, $token);
    }
}
