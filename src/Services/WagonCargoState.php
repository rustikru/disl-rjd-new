<?php
declare(strict_types=1);

namespace App\Services;

final class WagonCargoState
{
    public const SQL_EXPRESSION = "CASE
        WHEN NVL(cargo_weight_kg, 0) >
             CASE WHEN UPPER(wagon_type_code) LIKE '%ПЛАТФОРМ%' THEN 12000 ELSE 0 END
        THEN 'гр.' ELSE 'пор.' END";

    public static function isLoaded(?string $wagonType, mixed $weightKg): bool
    {
        $threshold = mb_stripos($wagonType ?? '', 'ПЛАТФОРМ') !== false ? 12000 : 0;
        return (float) ($weightKg ?? 0) > $threshold;
    }
}
