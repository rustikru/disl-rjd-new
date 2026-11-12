<?php
declare(strict_types=1);

namespace App\Services\DislDataSource;

/** Общие источники дополнительных данных для отчётов дислокации. */
final class DislDataSource
{
    public function pnFields(array $options = []): array
    {
        $fields = [
            'pn.pn_car_type AS pn_car_type',
            'pn.pn_weight_net AS pn_weight_net',
        ];

        if (($options['cargo_state'] ?? '') === 'pn') {
            $fields[] = "
                CASE
                    WHEN pn.pn_weight_net IS NULL THEN 'неопр.'
                    ELSE XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_CARGO_STATE(
                        pn.pn_car_type,
                        pn.pn_weight_net
                    )
                END AS pn_cargo_state
            ";
        }

        return $fields;
    }

    public function pnJoin(string $wagonField = 'd.wagon_no'): string
    {
        return "
            LEFT JOIN (
                SELECT
                    dr.railcar_id,
                    MAX(rai.car_type) AS pn_car_type,
                    SUM(NVL(dcai.weight_net, rai.weight_net)) AS pn_weight_net
                FROM xx_etw.xx_disl_round dr
                LEFT JOIN xx_etw.xx_disl_railcar_add_info rai
                    ON rai.round_id = dr.round_id
                   AND rai.last_record = 'Y'
                LEFT JOIN xx_etw.xx_disl_cont_history dch
                    ON dch.place_id = dr.round_id
                   AND dch.place_type = 'railcar'
                LEFT JOIN xx_etw.xx_disl_cont_round dcr
                    ON dcr.cont_round_id = dch.cont_round_id
                LEFT JOIN xx_etw.xx_disl_cont_add_info dcai
                    ON dcai.cont_round_id = dcr.cont_round_id
                   AND dcai.last_record = 'Y'
                WHERE dr.station_hist_id_out IS NULL
                GROUP BY dr.railcar_id
            ) pn
                ON pn.railcar_id = {$wagonField}
        ";
    }

    public function approachSource(string $whereSql): string
    {
        return "(
            WITH report_rows AS (
                SELECT d.*
                FROM xx_dislocation_rjd d
                WHERE $whereSql
            ),
            pn AS (
                SELECT
                    dr.railcar_id,
                    MAX(rai.car_type) AS pn_car_type,
                    SUM(NVL(dcai.weight_net, rai.weight_net)) AS pn_weight_net
                FROM xx_etw.xx_disl_round dr
                LEFT JOIN xx_etw.xx_disl_railcar_add_info rai
                    ON rai.round_id = dr.round_id
                   AND rai.last_record = 'Y'
                LEFT JOIN xx_etw.xx_disl_cont_history dch
                    ON dch.place_id = dr.round_id
                   AND dch.place_type = 'railcar'
                LEFT JOIN xx_etw.xx_disl_cont_round dcr
                    ON dcr.cont_round_id = dch.cont_round_id
                LEFT JOIN xx_etw.xx_disl_cont_add_info dcai
                    ON dcai.cont_round_id = dcr.cont_round_id
                   AND dcai.last_record = 'Y'
                WHERE dr.station_hist_id_out IS NULL
                  AND dr.railcar_id IN (
                      SELECT wagon_no
                      FROM report_rows
                      WHERE UPPER(NVL(oper_station, ' ')) LIKE '%УГЛЕУРАЛ%'
                  )
                GROUP BY dr.railcar_id
            )
            SELECT
                report_rows.*,
                pn.pn_car_type AS pn_car_type,
                pn.pn_weight_net AS pn_weight_net,
                CASE
                    WHEN UPPER(NVL(report_rows.oper_station, ' ')) NOT LIKE '%УГЛЕУРАЛ%'
                        THEN XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_CARGO_STATE(
                            report_rows.wagon_type_code,
                            report_rows.cargo_weight_kg
                        )
                    WHEN pn.pn_weight_net IS NULL THEN 'неопр.'
                    ELSE XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_CARGO_STATE(
                        pn.pn_car_type,
                        pn.pn_weight_net
                    )
                END AS pn_cargo_state
            FROM report_rows
            LEFT JOIN pn
                ON pn.railcar_id = report_rows.wagon_no
        )";
    }
}
