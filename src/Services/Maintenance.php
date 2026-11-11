<?php
declare(strict_types=1);

namespace App\Services;

final class Maintenance
{
    public static function isActive(array $config): bool
    {
        return self::status($config) === 'active';
    }

    public static function status(array $config): string
    {
        if (empty($config['maintenance_enabled'])) {
            return 'off';
        }
        $now = new \DateTimeImmutable();
        $start = (string) ($config['maintenance_start'] ?? '');
        $end = (string) ($config['maintenance_end'] ?? '');
        if ($start !== '' && $now < new \DateTimeImmutable($start)) {
            return 'scheduled';
        }
        if ($end !== '' && $now >= new \DateTimeImmutable($end)) {
            return 'finished';
        }
        return 'active';
    }
}
