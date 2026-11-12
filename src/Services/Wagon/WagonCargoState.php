<?php
declare(strict_types=1);

namespace App\Services\Wagon;

final class WagonCargoState
{
    public const SQL_EXPRESSION = "CASE WHEN type_reference = 'Подход' AND UPPER(NVL(oper_station, ' ')) LIKE '%УГЛЕУРАЛ%' THEN XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_PN_CARGO_STATE(wagon_no) ELSE XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_CARGO_STATE(wagon_type_code, cargo_weight_kg) END";
}
