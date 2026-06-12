<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * JSON API для фронтенда.
 * Все запросы к таблице xx_dislocation_rjd.
 */
class ApiController
{
    private DbInterface $db;
    private ?array $cachedTableColumns = null;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /** Возвращает список колонок таблицы xx_dislocation_rjd (кеш на запрос). */
    private function getColumns(): array
    {
        if ($this->cachedTableColumns === null) {
            $rows = $this->db->fetchAll(
                "SELECT LOWER(column_name) AS col FROM user_tab_columns WHERE table_name = 'XX_DISLOCATION_RJD'"
            );
            $this->cachedTableColumns = array_column($rows, 'col');
        }
        return $this->cachedTableColumns;
    }

    /**
     * Строит безопасную строку для SELECT из списка полей, переданных клиентом.
     * Каждое поле проверяется по реальным колонкам таблицы (user_tab_columns).
     */
    private function selectFields(string $raw): string
    {
        $allowed = $this->getColumns();
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => $f !== '' && in_array($f, $allowed, true)
        ));
        return $fields ? implode(', ', $fields) : 'wagon_no';
    }

    /**
     * Парсит group_by=field1,field2,... и возвращает все проверенные поля.
     * Количество полей не ограничено — добавь в groupCols, backend подхватит.
     * При пустом/невалидном raw — fallback на $defaults.
     */
    private function groupFields(string $raw, array $defaults): array
    {
        $allowed = $this->getColumns();
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => $f !== '' && in_array($f, $allowed, true)
        ));
        return $fields ?: $defaults;
    }

    /** GET /api/dislocation/filters — список загруженных справок */
    public function dislFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            'SELECT TRUNC(report_dt) AS report_date, type_reference, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             GROUP BY TRUNC(report_dt), type_reference
             ORDER BY TRUNC(report_dt) DESC, type_reference
             '
        );

        $reports = array_map(function (array $r) {
            $dt = (string) ($r['report_date'] ?? '');
            $label = $dt;
            try {
                $d = new \DateTime($dt);
                $label = $d->format('d.m.Y');
            } catch (\Exception $e) {
            }
            return [
                'report_dt' => $dt,
                'type_reference' => (string) ($r['type_reference'] ?? ''),
                'label' => $label . ($r['type_reference'] ? ' (' . $r['type_reference'] . ')' : ''),
                'cnt' => (int) $r['cnt'],
            ];
        }, $rows);

        return $this->json($response, ['reports' => $reports]);
    }

    /** GET /api/dashboard — KPI-сводка */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dtsByType = $this->getLatestDtsByType(null, ['Подход', 'Отправка']);

        if (empty($dtsByType)) {
            return $this->json($response, [
                'updated_at' => null,
                'sections' => [],
            ]);
        }

        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $rows = $this->db->fetchAll(
            "SELECT park_type, COUNT(*) AS total, wagon_type_code
             FROM xx_dislocation_rjd xdr
             WHERE {$cond['sql']}
             GROUP BY park_type, wagon_type_code",
            $cond['params']
        );
        $dt = max($dtsByType);

        $sections = [];
        foreach ($rows as $r) {
            $sectionName = trim(explode(',', (string) ($r['park_type'] ?? ''))[0]);
            if (!isset($sections[$sectionName])) {
                $sections[$sectionName] = [
                    'id' => md5($sectionName),
                    'name' => $sectionName,
                    'total' => 0,
                    'tank_total' => 0,
                ];
            }
            $cnt = (int) $r['total'];
            $sections[$sectionName]['total'] += $cnt;
            if (mb_stripos((string) ($r['wagon_type_code'] ?? ''), 'цистерн') !== false) {
                $sections[$sectionName]['tank_total'] += $cnt;
            }
        }

        try {
            $d = new \DateTime($dt);
            $dtLabel = $d->format('d.m.Y H:i');
        } catch (\Exception $e) {
            $dtLabel = $dt;
        }

        return $this->json($response, [
            'updated_at' => $dtLabel,
            'sections' => array_values($sections),
        ]);
    }

    /** GET /api/dislocation/summary?report_dt=... — Сводная таблица */
    public function dislSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params    = $request->getQueryParams();
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);

        if (empty($dtsByType)) {
            return $this->json($response, $this->makeSummary([], ''));
        }

        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $rows = $this->db->fetchAll(
            "SELECT park_type,
                    XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(wagon_type_code) AS wagon_type_code,
                    COUNT(*) AS wagon_count
             FROM xx_dislocation_rjd xdr
             WHERE {$cond['sql']}
             GROUP BY park_type, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(wagon_type_code)
             ORDER BY park_type, wagon_type_code",
            $cond['params']
        );

        $latestDt = max($dtsByType);
        try {
            $label = (new \DateTime($latestDt))->format('d.m.Y H:i');
        } catch (\Exception $e) {
            $label = $latestDt;
        }

        return $this->json($response, $this->makeSummary($rows, $label));
    }

    /** GET /api/dislocation/detail — Расширенная дислокация */
    public function dislDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $params['report_dt'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $parkType = $params['park_type'] ?? null;
        $section = $params['section'] ?? null;

        $dtsByType = $this->getLatestDtsByType($reportDt, ['Подход', 'Отправка']);
        $cond      = $this->latestDtCondition($dtsByType, 'xdr');
        $where     = '';
        $bindings  = $cond['params'];

        if ($wagType) {
            $where .= ' AND XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(wagon_type_code) = :wtype';
            $bindings['wtype'] = $wagType;
        }
        if ($parkType) {
            $where .= ' AND park_type = :park_type';
            $bindings['park_type'] = $parkType;
        }
        if ($section) {
            $where .= ' AND (park_type = :section OR park_type LIKE :section_like)';
            $bindings['section']      = $section;
            $bindings['section_like'] = $section . ',%';
        }

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, train_no, oper_station, depart_station, dest_station,
                    cargo_name, park_type, oper_mnemonic, idle_time_days, asoup_arrive_dt,
                    owner, lessee
             FROM xx_dislocation_rjd xdr
             WHERE {$cond['sql']} {$where}
             ORDER BY oper_station",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach/summary — Сводная подход: Дорога→Станция, колонки=тип вагона */
    public function approachSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        $cargo = $params['cargo'] ?? null;
        $prevCargo = $params['prev_cargo'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        [$where, $bindings] = $this->approachWhere($reportDt, $cargo, $prevCargo);

        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $gfStr = implode(', ', $gf);

        $rows = $this->db->fetchAll(
            "SELECT $gfStr, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(WAGON_TYPE_CODE) as wagon_type_code
                        , CASE WHEN CARGO_WEIGHT_KG > 0 THEN 'ГР' ELSE 'ПОР' END AS CARGO_W_TYPE
                        , COUNT(*) AS cnt
             FROM xx_dislocation_rjd WHERE {$where}
             GROUP BY $gfStr, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(WAGON_TYPE_CODE), CASE WHEN CARGO_WEIGHT_KG > 0 THEN 'ГР' ELSE 'ПОР' END 
             ORDER BY $gfStr, wagon_type_code",
            $bindings
        );

        return $this->json($response, $this->roadTable($rows, $gf, ['cargo_w_type']));
    }

    /** GET /api/approach/detail — Список вагонов подхода */
    public function approachDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        $cargo = $params['cargo'] ?? null;
        $prevCargo = $params['prev_cargo'] ?? null;
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $gfStr = implode(', ', $gf);
        [$where, $bindings] = $this->approachWhere($reportDt, $cargo, $prevCargo);

        foreach (array_filter([0 => $road, 1 => $station]) as $idx => $val) {
            if (isset($gf[$idx])) {
                $where .= " AND {$gf[$idx]} = :gf_$idx";
                $bindings["gf_$idx"] = $val;
            }
        }
        if ($wagType) {
            $where .= ' AND XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(WAGON_TYPE_CODE) = :wtype';
            $bindings['wtype'] = $wagType;
        }
        $cargoState = $params['cargo_state'] ?? null;
        if ($cargoState === 'ГР') {
            $where .= ' AND CARGO_WEIGHT_KG > 0';
        } elseif ($cargoState === 'ПОР') {
            $where .= ' AND (CARGO_WEIGHT_KG IS NULL OR CARGO_WEIGHT_KG = 0)';
        }

        $select = isset($params['fields']) && $params['fields'] !== ''
            ? $this->selectFields($params['fields'])
            : 'wagon_no, wagon_type_code, cargo_name, prev_cargo, dist_remain_km,
               depart_station, depart_road, dest_station, dest_road, oper_station,
               train_index, oper_dt, norm_delivery_dt, oper_mnemonic';

        $rows = $this->db->fetchAll(
            "SELECT $select FROM xx_dislocation_rjd WHERE {$where} ORDER BY $gfStr " . "",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach/filters — Уникальные значения для фильтров */
    public function approachFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');

        if (!$reportDt) {
            return $this->json($response, ['cargo' => [], 'prev_cargo' => []]);
        }

        $bindings = ['report_dt' => $reportDt];

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt AND type_reference = 'Подход'
               AND cargo_name IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY cargo_name ",
            $bindings
        );
        $prevCargo = $this->db->fetchAll(
            "SELECT DISTINCT prev_cargo FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt AND type_reference = 'Подход'
               AND prev_cargo IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY prev_cargo ",
            $bindings
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
            'prev_cargo' => array_column($prevCargo, 'prev_cargo'),
        ]);
    }

    /** WHERE-условие для запросов «Подход» (wagons in transit: dist_remain_km > 0) */
    private function approachWhere(string $reportDt, ?string $cargo, ?string $prevCargo): array
    {
        $where = "report_dt = :report_dt AND type_reference = 'Подход' AND dist_remain_km IS NOT NULL and dist_remain_km != 0";
        $bindings = ['report_dt' => $reportDt];

        if ($cargo) {
            $where .= " AND UPPER(REPLACE(COALESCE(cargo_name,''), 'Ё', 'Е')) = UPPER(REPLACE(:cargo_f, 'Ё', 'Е'))";
            $bindings['cargo_f'] = $cargo;
        }
        if ($prevCargo) {
            $where .= " AND UPPER(REPLACE(COALESCE(prev_cargo,''), 'Ё', 'Е')) = UPPER(REPLACE(:prev_cargo_f, 'Ё', 'Е'))";
            $bindings['prev_cargo_f'] = $prevCargo;
        }

        return [$where, $bindings];
    }


    /** GET /api/departure/filters — значения для фильтров вкладки «Отправление» */
    public function departureFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');

        if (!$reportDt) {
            return $this->json($response, ['cargo' => [], 'dest_station' => []]);
        }

        $bindings = ['report_dt' => $reportDt];

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt AND type_reference = 'Отправка'
               AND oper_mnemonic = 'ОТПР' AND cargo_name IS NOT NULL
             ORDER BY cargo_name " . $this->db->limit(150),
            $bindings
        );
        $destStation = $this->db->fetchAll(
            "SELECT DISTINCT dest_station FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt AND type_reference = 'Отправка'
               AND oper_mnemonic = 'ОТПР' AND dest_station IS NOT NULL
             ORDER BY dest_station " . $this->db->limit(300),
            $bindings
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
            'dest_station' => array_column($destStation, 'dest_station'),
        ]);
    }

    /** GET /api/departure/summary — Сводная отправление: Дорога→Станция, колонки=тип вагона */
    public function departureSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        $cargo = $params['cargo'] ?? null;
        $destStation = $params['dest_station'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $gfStr = implode(', ', $gf);

        $where = "report_dt = :report_dt AND type_reference = 'Отправка' AND oper_mnemonic = 'ОТПР'";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }
        if ($destStation) {
            $where .= ' AND dest_station = :dest_station';
            $bindings['dest_station'] = $destStation;
        }

        $rows = $this->db->fetchAll(
            "SELECT $gfStr, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(xdr.WAGON_TYPE_CODE) AS wagon_type_code, COUNT(*) AS cnt
             FROM xx_dislocation_rjd xdr WHERE {$where}
             GROUP BY $gfStr, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(xdr.WAGON_TYPE_CODE)
             ORDER BY $gfStr, wagon_type_code",
            $bindings
        );

        return $this->json($response, $this->roadTable($rows, $gf));
    }

    /** GET /api/departure/detail — Список отправленных вагонов */
    public function departureDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        $cargo = $params['cargo'] ?? null;
        $destStation = $params['dest_station'] ?? null;
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $gfStr = implode(', ', $gf);

        $where = "report_dt = :report_dt AND type_reference = 'Отправка' AND oper_mnemonic = 'ОТПР'";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= ' AND UPPER(COALESCE(cargo_name,\'\')) = UPPER(:cargo_f)';
            $bindings['cargo_f'] = $cargo;
        }
        if ($destStation) {
            $where .= ' AND dest_station = :dest_station';
            $bindings['dest_station'] = $destStation;
        }
        foreach (array_filter([0 => $road, 1 => $station]) as $idx => $val) {
            if (isset($gf[$idx])) {
                $where .= " AND {$gf[$idx]} = :gf_$idx";
                $bindings["gf_$idx"] = $val;
            }
        }
        if ($wagType) {
            $where .= ' AND XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(WAGON_TYPE_CODE) = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $select = isset($params['fields']) && $params['fields'] !== ''
            ? $this->selectFields($params['fields'])
            : 'wagon_no, wagon_type_code, cargo_name, cargo_weight_kg,
               depart_station, depart_road, dest_station, dest_road,
               oper_station, oper_dt, dist_remain_km, norm_delivery_dt, waybill_no';

        $rows = $this->db->fetchAll(
            "SELECT $select FROM xx_dislocation_rjd WHERE {$where} ORDER BY $gfStr " . "",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }


    /** GET /api/loading/summary — Погруженные вагоны по станциям отправления */
    public function loadingSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        $cargo = $params['cargo'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $gfStr = implode(', ', $gf);

        $where = "report_dt = :report_dt AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }

        $rows = $this->db->fetchAll(
            "SELECT $gfStr, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(WAGON_TYPE_CODE) as wagon_type_code, COUNT(*) AS cnt
             FROM xx_dislocation_rjd WHERE {$where}
             GROUP BY $gfStr, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(WAGON_TYPE_CODE)
             ORDER BY $gfStr, wagon_type_code",
            $bindings
        );

        return $this->json($response, $this->roadTable($rows, $gf));
    }

    /** GET /api/loading/detail — Список погруженных вагонов */
    public function loadingDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        $cargo = $params['cargo'] ?? null;
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $gfStr = implode(', ', $gf);

        $where = "report_dt = :report_dt AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= ' AND UPPER(COALESCE(cargo_name,\'\')) = UPPER(:cargo_f)';
            $bindings['cargo_f'] = $cargo;
        }
        foreach (array_filter([0 => $road, 1 => $station]) as $idx => $val) {
            if (isset($gf[$idx])) {
                $where .= " AND {$gf[$idx]} = :gf_$idx";
                $bindings["gf_$idx"] = $val;
            }
        }
        if ($wagType) {
            $where .= ' AND XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(WAGON_TYPE_CODE) = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $select = isset($params['fields']) && $params['fields'] !== ''
            ? $this->selectFields($params['fields'])
            : 'wagon_no, wagon_type_code, cargo_name, cargo_weight_kg,
               depart_station, depart_road, dest_station, dest_road,
               oper_station, oper_mnemonic, oper_dt, waybill_no';

        $rows = $this->db->fetchAll(
            "SELECT $select FROM xx_dislocation_rjd WHERE {$where} ORDER BY $gfStr " . "",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }


    /** GET /api/downtime/summary — Сводная простоев × тип вагона */
    public function downtimeSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params   = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        $gf       = $this->groupFields($params['group_by'] ?? '', ['oper_road', 'oper_station']);
        $gfStr    = implode(', ', $gf);
        $minDays  = max(0, (int) ($params['min_days'] ?? 1));
        $maxDays  = isset($params['max_days']) && $params['max_days'] !== '' ? (int) $params['max_days'] : null;

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $where    = "report_dt = :report_dt AND idle_time_days IS NOT NULL AND nvl(idle_time_days,0) != 0";
        $bindings = ['report_dt' => $reportDt];
        if ($minDays > 0) {
            $where .= ' AND idle_time_days >= :min_days';
            $bindings['min_days'] = $minDays;
        }
        if ($maxDays !== null) {
            $where .= ' AND idle_time_days <= :max_days';
            $bindings['max_days'] = $maxDays;
        }

        $rows = $this->db->fetchAll(
            "SELECT $gfStr, wagon_type_code, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             WHERE {$where}
             GROUP BY $gfStr, wagon_type_code
             ORDER BY $gfStr",
            $bindings
        );

        return $this->json($response, $this->roadTable($rows, $gf));
    }

    /** GET /api/downtime/detail — Список простаивающих вагонов */
    public function downtimeDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        $station = $params['station'] ?? null;
        $road = $params['road'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $minDays = max(0, (int) ($params['min_days'] ?? 1));
        $maxDays = isset($params['max_days']) && $params['max_days'] !== '' ? (int) $params['max_days'] : null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $where = "report_dt = :report_dt AND idle_time_days IS NOT NULL AND idle_time_days != 0 ";
        $bindings = ['report_dt' => $reportDt];
        if ($minDays > 0) {
            $where .= ' AND idle_time_days >= :min_days';
            $bindings['min_days'] = $minDays;
        }
        if ($maxDays !== null) {
            $where .= ' AND idle_time_days <= :max_days';
            $bindings['max_days'] = $maxDays;
        }
        if ($station) {
            $where .= ' AND oper_station = :oper_station';
            $bindings['oper_station'] = $station;
        }
        if ($road) {
            $where .= ' AND oper_road = :oper_road';
            $bindings['oper_road'] = $road;
        }
        if ($wagType) {
            $where .= ' AND wagon_type_code = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, wagon_type_code, cargo_name, park_type,
                    oper_station, oper_road, idle_time_days, idle_time_hhmmss,
                    depart_station, dest_station, owner, lessee
             FROM xx_dislocation_rjd
             WHERE {$where}
             ORDER BY idle_time_days DESC
             " . "",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }


    /** GET /api/raw-material/summary — Сводная сырья */
    public function rawSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params   = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        $gf       = $this->groupFields($params['group_by'] ?? '', ['cargo_name']);
        $gfStr    = implode(', ', $gf);

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0, 'max_idle' => 0]);
        }

        $rows = $this->db->fetchAll(
            "SELECT $gfStr,
                    XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(wagon_type_code) AS wagon_type_code,
                    COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt
               AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0
               AND idle_time_days IS NOT NULL AND idle_time_days != 0
             GROUP BY $gfStr,
                      XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(wagon_type_code)
             ORDER BY $gfStr",
            ['report_dt' => $reportDt]
        );

        $maxIdleRow = $this->db->fetchOne(
            "SELECT MAX(idle_time_days) AS max_idle FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0",
            ['report_dt' => $reportDt]
        );

        $result = $this->roadTable($rows, $gf);
        $result['max_idle'] = (float) ($maxIdleRow['max_idle'] ?? 0);
        return $this->json($response, $result);
    }

    /** GET /api/raw-material/detail — Список вагонов с сырьём */
    public function rawDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $gf    = $this->groupFields($params['group_by'] ?? '', ['cargo_name']);
        $gfStr = implode(', ', $gf);

        $where    = "report_dt = :report_dt AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
        $bindings = ['report_dt' => $reportDt];

        foreach (array_filter([0 => $road, 1 => $station]) as $idx => $val) {
            if (isset($gf[$idx])) {
                $where .= " AND {$gf[$idx]} = :gf_$idx";
                $bindings["gf_$idx"] = $val;
            }
        }
        if ($wagType) {
            $where .= ' AND XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(wagon_type_code) = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, wagon_type_code, cargo_name, cargo_weight_kg,
                    idle_time_days, idle_time_hhmmss,
                    oper_station, oper_road, depart_station, depart_road,
                    dest_station, owner, waybill_no
             FROM xx_dislocation_rjd
             WHERE {$where}
             ORDER BY $gfStr, idle_time_days DESC",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }


    /**
     * Возвращает переданный report_dt или последний MAX(report_dt) для указанного типа справки.
     * $typeRef = 'Подход' | 'Отправка' | null (любой тип)
     */
    private function getReportDt(?string $dt, ?string $typeRef = null): ?string
    {
        if ($dt)
            return $dt;
        $sql = 'SELECT MAX(report_dt) AS dt FROM xx_dislocation_rjd';
        $params = [];
        if ($typeRef !== null) {
            $sql .= ' WHERE type_reference = :type_ref';
            $params['type_ref'] = $typeRef;
        }
        $row = $this->db->fetchOne($sql, $params);
        return $row['dt'] ?? null;
    }

    /**
     * Возвращает [type_reference => MAX(report_dt)] для всех типов.
     * Если передан конкретный $dt — возвращает его для всех типов без запроса к БД.
     * $types = ['Подход', 'Отправка'] — ограничить список типов (null = все).
     */
    private function getLatestDtsByType(?string $dt = null, ?array $types = null): array
    {
        if ($types !== null && count($types) === 0) {
            return [];
        }
        $sql = 'SELECT type_reference, MAX(report_dt) AS dt FROM xx_dislocation_rjd';
        $params = [];
        if ($types !== null) {
            $placeholders = implode(',', array_map(fn($i) => ":t$i", array_keys($types)));
            $sql .= " WHERE type_reference IN ($placeholders)";
            foreach ($types as $i => $t) {
                $params["t$i"] = $t;
            }
        }
        $sql .= ' GROUP BY type_reference';
        $rows = $this->db->fetchAll($sql, $params);
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['type_reference']] = $dt ?? (string) $r['dt'];
        }
        return $map;
    }

    /**
     * Строит SQL-фрагмент для WHERE из карты [type_reference => report_dt].
     * Результат: ['sql' => '(type_reference=:ldt_type_0 AND report_dt=:ldt_dt_0) OR ...', 'params' => [...]]
     * $alias — префикс таблицы, например 'xdr' → 'xdr.type_reference'
     */
    private function latestDtCondition(array $dtsByType, string $alias = ''): array
    {
        if (empty($dtsByType)) {
            return ['sql' => '1=0', 'params' => []];
        }
        $col = fn(string $c) => $alias !== '' ? "$alias.$c" : $c;
        $parts = [];
        $params = [];
        $i = 0;
        foreach ($dtsByType as $type => $dt) {
            $parts[] = "({$col('type_reference')} = :ldt_type_{$i} AND {$col('report_dt')} = :ldt_dt_{$i})";
            $params["ldt_type_{$i}"] = $type;
            $params["ldt_dt_{$i}"]   = $dt;
            $i++;
        }
        return ['sql' => '(' . implode(' OR ', $parts) . ')', 'params' => $params];
    }

    /**
     * Строит иерархию groupKeys[0]→groupKeys[last]
     */
    private function roadTable(array $rows, array $groupKeys, array $subGroupFields = []): array
    {
        $roadKey = $groupKeys[0];
        $stationKey = $groupKeys[count($groupKeys) - 1];

        // Ось колонок: тип вагона + произвольное число под-уровней
        $axisFields = array_merge(['wagon_type_code'], $subGroupFields);
        $nLevels = count($axisFields);

        $values = array_fill(0, $nLevels, []); // уникальные значения уровня в порядке появления
        $index = array_fill(0, $nLevels, []); // значение => позиция

        foreach ($rows as $r) {
            foreach ($axisFields as $k => $f) {
                $v = (string) ($r[$f] ?? '');
                if ($v !== '' && !isset($index[$k][$v])) {
                    $index[$k][$v] = count($values[$k]);
                    $values[$k][] = $v;
                }
            }
        }

        $dims = array_map(fn($vals) => max(1, count($vals)), $values);
        $nFlat = (int) array_product($dims);

        $roads = [];
        foreach ($rows as $r) {
            $road = (string) ($r[$roadKey] ?? 'Не указана');
            $station = (string) ($r[$stationKey] ?? 'Не указана');
            $cnt = (int) $r['cnt'];

            if (!isset($roads[$road])) {
                $roads[$road] = [$roadKey => $road, 'stations' => [], 'total' => array_fill(0, $nFlat, 0), 'grand_total' => 0];
            }
            if (!isset($roads[$road]['stations'][$station])) {
                $roads[$road]['stations'][$station] = [$stationKey => $station, 'v' => array_fill(0, $nFlat, 0)];
            }

            $t = (string) ($r['wagon_type_code'] ?? '');
            if ($t === '' || !isset($index[0][$t])) {
                continue;
            }
            // Плоский индекс: позиционная система счисления по уровням оси
            $fi = 0;
            foreach ($axisFields as $k => $f) {
                $v = (string) ($r[$f] ?? '');
                $fi = $fi * $dims[$k] + ($index[$k][$v] ?? 0);
            }

            $roads[$road]['stations'][$station]['v'][$fi] += $cnt;
            $roads[$road]['total'][$fi] += $cnt;
            $roads[$road]['grand_total'] += $cnt;
        }

        foreach ($roads as &$road) {
            $road['stations'] = array_values($road['stations']);
        }
        unset($road);

        $roadList = array_values($roads);
        usort($roadList, fn($a, $b) => $b['grand_total'] - $a['grand_total']);

        $metrics = array_map(fn($r) => ['label' => $r[$roadKey], 'total' => $r['grand_total']], $roadList);
        $grandTotal = array_sum(array_column($metrics, 'total'));

        if ($subGroupFields === []) {
            return ['cols' => $values[0], 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 20), 'total' => $grandTotal];
        }

        // Дерево шапки: полное декартово произведение значений уровней, листья — строки
        $buildTree = function (int $level) use (&$buildTree, $values, $nLevels) {
            $leaf = $level === $nLevels - 1;
            $out = [];
            foreach ($values[$level] as $v) {
                $out[] = $leaf ? $v : ['label' => $v, 'subs' => $buildTree($level + 1)];
            }
            return $out;
        };

        return ['col_groups' => $buildTree(0), 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 20), 'total' => $grandTotal];
    }


    private function makeSummary(array $rows, string $dateLabel): array
    {
        // Собираем упорядоченный список типов вагонов (колонки)
        $colOrder = [];
        foreach ($rows as $r) {
            $t = (string) ($r['wagon_type_code'] ?? '');
            if ($t !== '' && !isset($colOrder[$t])) {
                $colOrder[$t] = true;
            }
        }
        $cols = array_map(fn($t) => ['label' => $t, 'group' => ''], array_keys($colOrder));
        $colIndex = array_flip(array_column($cols, 'label'));

        // Группируем по первому слову park_type → раздел, полная строка → подраздел
        $sections = [];
        foreach ($rows as $r) {
            $parkType = (string) ($r['park_type'] ?? '');
            $sectionName = trim(explode(',', $parkType)[0]);
            $cnt = (int) ($r['wagon_count'] ?? 0);

            if (!isset($sections[$sectionName])) {
                $sections[$sectionName] = [
                    'id' => md5($sectionName),
                    'name' => $sectionName,
                    'rows' => [],
                    'total' => array_fill(0, count($cols), 0),
                    'grand_total' => 0,
                ];
            }

            if (!isset($sections[$sectionName]['rows'][$parkType])) {
                $sections[$sectionName]['rows'][$parkType] = [
                    'sub' => $parkType,
                    'park' => '',
                    'v' => array_fill(0, count($cols), 0),
                ];
            }

            $ci = $colIndex[$r['wagon_type_code'] ?? ''] ?? null;
            if ($ci !== null) {
                $sections[$sectionName]['rows'][$parkType]['v'][$ci] += $cnt;
                $sections[$sectionName]['total'][$ci] += $cnt;
                $sections[$sectionName]['grand_total'] += $cnt;
            }
        }

        foreach ($sections as &$sec) {
            $sec['rows'] = array_values($sec['rows']);
        }

        return [
            'date' => $dateLabel,
            'report_dt_label' => $dateLabel,
            'cols' => $cols,
            'sections' => array_values($sections),
        ];
    }

    private function json(ResponseInterface $response, $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
