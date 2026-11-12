<?php
declare(strict_types=1);

namespace App\Services\Wagon;

final class WagonCargoState
{
    public const SQL_EXPRESSION = "XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_CARGO_STATE(wagon_type_code, cargo_weight_kg)";
}
