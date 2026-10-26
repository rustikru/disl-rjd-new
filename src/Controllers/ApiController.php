<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Services\OrganizationService;
use App\Services\WagonCargoState;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ApiController
{
    private const WAG_TYPE_EXPR = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code)";
    private const WAG_STATE = WagonCargoState::SQL_EXPRESSION;
    private const EXCLUDED_WAGONS_COND = "
        AND NOT EXISTS (
            SELECT 1
              FROM xx_disl_idle_control_v dic
             WHERE 1=1 and TRIM(dic.car_number) = TRIM(TO_CHAR(wagon_no))
               AND UPPER(TRIM(dic.is_excluded)) = 'Y'
               AND UPPER(REPLACE(TRIM(dic.idle_reasons_name), 'Ё', 'Е'))
                   IN ('ЛОМ', 'МЕТАЛЛОЛОМ','СНЯТЬ С АВТОСЛЕЖЕНИЯ')
        )";

    private DbInterface $db;
    private OrganizationService $organizations;

    public function __construct(DbInterface $db, ?OrganizationService $organizations = null)
    {
        $this->db = $db;
        $this->organizations = $organizations ?? new OrganizationService($db);
    }

    // =========================================================================
    // endpoint
    // =========================================================================

    /** GET /api/dashboard */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dtsByType = $this->getLatestDtsByType(null, ['Подход', 'Отправка']);

        if (empty($dtsByType)) {
            return $this->json($response, ['updated_at' => null, 'sections' => [], 'trends' => []]);
        }

        $dt = max($dtsByType);

        $sections = [];

        try {
            $dtLabel = (new \DateTime($dt))->format('d.m.Y H:i');
        } catch (\Exception $e) {
            $dtLabel = $dt;
        }

        return $this->json($response, [
            'updated_at' => $dtLabel,
            'sections' => array_values($sections),
            'trends' => '',
        ]);
    }
    /** GET /api/kpi/summary */
    public function kpiSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filter = $this->organizations->filter();
        $dtRow = $this->db->fetchAll(
            "SELECT MAX(report_dt) AS latest_dt
               FROM xx_dislocation_rjd
              WHERE {$filter['sql']}",
            $filter['params']
        );
        $latestDt = $dtRow[0]['latest_dt'] ?? null;
        try {
            $updatedAt = $latestDt ? (new \DateTime($latestDt))->format('d.m.Y H:i') : null;
        } catch (\Exception $e) {
            $updatedAt = $latestDt;
        }

        if (!$this->organizations->isMtf()) {
            return $this->json($response, [
                'updated_at' => $updatedAt,
                'sections' => [['values' => []]],
            ]);
        }

        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';

        $kpiType = trim($params['kpi_type'] ?? '');
        if ($kpiType === 'loading_kpi') {
            return $this->loadingKpiSummary($request, $response);
        }
        if ($kpiType !== '') {
            $whereCond .= ' AND type = :kpi_type';
            $bindings['kpi_type'] = $kpiType;
        }

        // Прогрессивный fallback:
        //   1. get_kpi_row — pipelined-функция: prv_kpi_trend вызывается 1 раз на карточку 
        $queries = [
            "SELECT /*+ CARDINALITY(t 1) */ kpi.id, kpi.type, kpi.label AS x_label,
                    t.x_value, t.trend_pct, t.trend_dir
             FROM XX_RJD_KPI_TBL_V kpi,
                  TABLE(xx_rjd_dislocation_new_pkg.get_kpi_row(kpi.id)) t
             WHERE $whereCond
            order by kpi.ORDER_BY",

            "SELECT kpi.id, kpi.type, kpi.label AS x_label,
                    xx_rjd_dislocation_new_pkg.set_kpi_label(kpi.id)         AS x_value,
                    xx_rjd_dislocation_new_pkg.fnc_get_kpi_trend_pct(kpi.id) AS trend_pct,
                    xx_rjd_dislocation_new_pkg.fnc_get_kpi_trend_dir(kpi.id) AS trend_dir
             FROM XX_RJD_KPI_TBL_V kpi
             WHERE $whereCond
            order by kpi.ORDER_BY",

            "SELECT kpi.id, kpi.type, kpi.label AS x_label,
                    xx_rjd_dislocation_new_pkg.set_kpi_label(kpi.id) AS x_value,
                    NULL AS trend_pct,
                    NULL AS trend_dir
             FROM XX_RJD_KPI_TBL_V kpi
             WHERE $whereCond
            order by kpi.ORDER_BY",
        ];
        $rows = null;
        foreach ($queries as $sql) {
            try {
                $rows = $this->db->fetchAll($sql, $bindings);
                break;
            } catch (\Throwable $e) {
                continue;
            }
        }
        $rows ??= [];

        $values = [];
        foreach ($rows as $r) {
            $values[] = [
                'id' => $r['id'] ?? '',
                'value' => $r['x_value'] ?? '0',
                'label' => $r['x_label'] ?? '',
                'trend' => $r['trend_dir'] ?? '',
                'change' => $r['trend_pct'] ?? '',
            ];
        }

        return $this->json($response, [
            'updated_at' => $updatedAt,
            'sections' => [['values' => $values]],
        ]);
    }
    /** Карточки погрузки для GET /api/kpi/summary. */
    private function loadingKpiSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        unset($params['loading_period']);
        $source = $this->loadingFrom($params);
        $cards = $this->db->fetchAll(
            "SELECT id, code, label FROM XX_RJD_KPI_TBL_V WHERE type = 'loading_kpi' ORDER BY order_by, id"
        );
        $bindings = $source['bindings'];
        $select = [];
        foreach ($cards as $i => $card) {
            $condition = $this->loadingPeriodCondition($card['code'], $bindings, 'kpi_' . $i);
            $select[] = "COUNT(CASE WHEN $condition THEN 1 END) AS kpi_$i";
        }
        $counts = $source['reportDt'] && $select
            ? $this->db->fetchOne('SELECT ' . implode(', ', $select) . " FROM {$source['from']}", $bindings)
            : [];
        $values = [];
        foreach ($cards as $i => $card) {
            $values[] = [
                'id' => $card['id'],
                'label' => $card['label'],
                'value' => (int) ($counts['kpi_' . $i] ?? 0),
                'detail' => ['ctx' => 'loading', 'params' => ['loading_period' => $card['code']]],
            ];
        }
        $updatedAt = $source['reportDt'] ? (new \DateTimeImmutable($source['reportDt']))->format('d.m.Y H:i') : null;
        return $this->json($response, ['updated_at' => $updatedAt, 'sections' => [['values' => $values]]]);
    }

    /** Условие отбора по периоду погрузки. */
    private function loadingPeriodCondition(string $period, array &$bindings, string $prefix = 'loading'): string
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Moscow'));
        switch ($period) {
            case 'loading_today':
                $from = $today;
                $to = $today->modify('+1 day');
                break;
            case 'loading_yesterday':
                $from = $today->modify('-1 day');
                $to = $today;
                break;
            case 'loading_month':
                $from = $today->modify('first day of this month');
                $to = $today->modify('+1 day');
                break;
            default:
                return '1=0';
        }
        $bindings[$prefix . '_from'] = $from->format('Y-m-d');
        $bindings[$prefix . '_to'] = $to->format('Y-m-d');
        return "trip_start_dt >= TO_DATE(:{$prefix}_from, 'YYYY-MM-DD')"
            . " AND trip_start_dt < TO_DATE(:{$prefix}_to, 'YYYY-MM-DD')";
    }

    /** GET /api/dislocation/filters */
    public function dislFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filter = $this->organizations->filter();
        $rows = $this->db->fetchAll(
            'SELECT TRUNC(report_dt) AS report_date, type_reference, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             WHERE ' . $filter['sql'] . '
             GROUP BY TRUNC(report_dt), type_reference
             ORDER BY TRUNC(report_dt) DESC, type_reference',
            $filter['params']
        );

        $source = $this->dislFrom([]);
        $cargo = $source['reportDt'] ? $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']} WHERE cargo_name IS NOT NULL ORDER BY cargo_name",
            $source['bindings']
        ) : [];

        $reports = array_map(function (array $r) {
            $dt = (string) ($r['report_date'] ?? '');
            try {
                $label = (new \DateTime($dt))->format('d.m.Y');
            } catch (\Exception $e) {
                $label = $dt;
            }
            return [
                'report_dt' => $dt,
                'type_reference' => (string) ($r['type_reference'] ?? ''),
                'label' => $label . ($r['type_reference'] ? ' (' . $r['type_reference'] . ')' : ''),
                'cnt' => (int) $r['cnt'],
            ];
        }, $rows);

        return $this->json($response, [
            'reports' => $reports,
            'cargo' => array_column($cargo, 'cargo_name')
        ]);
    }

    /** GET /api/dislocation/summary */
    public function dislSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->dislFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_state', 'dest_road']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);

        return $this->json($response, $this->summaryReport($source, $rowDims, $colDefs));
    }

    /** GET /api/dislocation/detail */
    public function dislDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->dislFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_state']);
        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);

        // Детализация для карточек KPI по ID карточки
        $kpiId = trim($params['kpi_id'] ?? '');
        if ($kpiId !== '') {
            $kpi = $this->db->fetchOne(
                'SELECT XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.get_kpi_where(:kpi_id) AS kpi_condition FROM dual',
                ['kpi_id' => (int) $kpiId]
            );
            $condition = trim((string) ($kpi['kpi_condition'] ?? '')) ?: '1=0';
            $whereCond .= ' AND (' . $condition . ')';
        }

        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims) . ', oper_station');

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} xdr WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach/filters */
    public function approachFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->approachFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$source['reportDt']) {
            return $this->json($response, ['cargo' => [], 'prev_cargo' => []]);
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']}
             WHERE cargo_name IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY cargo_name",
            $source['bindings']
        );
        $prevCargo = $this->db->fetchAll(
            "SELECT DISTINCT prev_cargo FROM {$source['from']}
             WHERE prev_cargo IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY prev_cargo",
            $source['bindings']
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
            'prev_cargo' => array_column($prevCargo, 'prev_cargo'),
        ]);
    }

    /** GET /api/approach/summary */
    public function approachSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->approachFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        return $this->json($response, $this->summaryReport($source, $rowDims, $colDefs));
    }

    /** GET /api/approach/detail */
    public function approachDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $source = $this->approachFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);

        $excl = $params['exclude_station'] ?? null;
        if ($excl) {
            $whereCond .= " AND UPPER(oper_station) NOT LIKE '%' || UPPER(:excl_st) || '%'";
            $bindings['excl_st'] = strtoupper($excl);
        }

        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims));

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/departure/filters */
    public function departureFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->departureFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$source['reportDt']) {
            return $this->json($response, ['cargo' => [], 'dest_station' => []]);
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']} WHERE cargo_name IS NOT NULL ORDER BY cargo_name",
            $source['bindings']
        );
        $destStation = $this->db->fetchAll(
            "SELECT DISTINCT dest_station FROM {$source['from']} WHERE dest_station IS NOT NULL ORDER BY dest_station ",
            $source['bindings']
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
            'dest_station' => array_column($destStation, 'dest_station'),
        ]);
    }

    /** GET /api/departure/summary 
     * Отправление вагонов
     */
    public function departureSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->departureFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        return $this->json($response, $this->summaryReport($source, $rowDims, $colDefs));
    }

    /** GET /api/departure/detail */
    public function departureDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $source = $this->departureFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);
        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims));

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }


    /** GET /api/loading/filters */
    public function loadingFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->loadingFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$source['reportDt']) {
            return $this->json($response, ['cargo' => []]);
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']}
             WHERE cargo_name IS NOT NULL
             ORDER BY cargo_name",
            $source['bindings']
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
        ]);
    }

    /** GET /api/loading/summary */
    public function loadingSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->loadingFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        return $this->json($response, $this->summaryReport($source, $rowDims, $colDefs));
    }

    /** GET /api/loading/detail */
    public function loadingDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $source = $this->loadingFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);
        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims));

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/downtime/filters */
    public function downtimeFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $source = $this->downtimeFrom($request->getQueryParams());

        if (!$source['reportDt']) {
            return $this->json($response, ['dest_station' => []]);
        }

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT dest_station FROM {$source['from']}
             WHERE dest_station IS NOT NULL
             ORDER BY dest_station",
            $source['bindings']
        );

        return $this->json($response, ['dest_station' => array_column($rows, 'dest_station')]);
    }

    /** GET /api/downtime/summary */
    public function downtimeSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->downtimeFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        // Простои используют колонки из подзапроса (downtimeFrom)
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['oper_road', 'oper_station']);
        $colAliases = $this->parseGroupBy($params['col_by'] ?? '', ['fixed_col_label', 'm_wagon_type_code']);
        $selectRow = implode(', ', $rowDims);
        $selectCols = implode(', ', $colAliases);

        $rows = $this->db->fetchAll(
            "SELECT $selectRow, $selectCols, COUNT(*) AS cnt
             FROM {$source['from']}
             GROUP BY idle_time_order_by, $selectRow, $selectCols
             ORDER BY idle_time_order_by ASC, $selectRow",
            $source['bindings']
        );

        return $this->json($response, $this->roadTable($rows, $rowDims, $colAliases));
    }

    /** GET /api/downtime/detail */
    public function downtimeDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['idle_time_name']);
        $source = $this->downtimeFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);

        // wagon_type_code использует функцию из пакета (xx_dislocation_rjd_pkg)
        $rawFields = array_values(array_filter(
            array_map('trim', explode(',', $params['fields'] ?? '')),
            fn($f) => self::isSafeField($f)
        ));
        $selectParts = array_map(
            fn($f) => $f === 'wagon_type_code' ? self::WAG_TYPE_EXPR . ' AS wagon_type_code' : $f,
            $rawFields
        );
        $selectCols = $selectParts ? implode(', ', $selectParts) : 'wagon_no';
        $selectCols = preg_replace('/\bidle_time_days\b/', 'station_idle_days AS idle_time_days', $selectCols);
        $orderBy = $this->orderBY($params, 'station_idle_days DESC NULLS LAST');
        $orderBy = preg_replace('/\bidle_time_days\b/', 'station_idle_days', $orderBy);

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/raw-material/summary */
    public function rawSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->rawFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0, 'max_idle' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['raw_material_idle_label', 'cargo_name']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
        $rowOrder = implode(', ', array_map(
            fn($field) => $field === 'raw_material_idle_label' ? 'MIN(LEAST(raw_material_idle_days, 13))' : $field,
            $rowDims
        ));
        $result = $this->summaryReport($source, $rowDims, $colDefs, $rowOrder);

        $maxIdleRow = $this->db->fetchOne("SELECT MAX(raw_material_idle_days) AS max_idle FROM {$source['from']}", $source['bindings']);
        $result['max_idle'] = (float) ($maxIdleRow['max_idle'] ?? 0);

        return $this->json($response, $result);
    }

    /** GET /api/raw-material/detail */
    public function rawDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['raw_material_idle_label', 'cargo_name']);
        $source = $this->rawFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);
        $selectCols = $this->selectFields($params['fields'] ?? '');
        $selectCols = preg_replace('/\bidle_time_days\b/', 'raw_material_idle_days AS idle_time_days', $selectCols);
        $orderBy = $this->orderBY($params, implode(', ', $rowDims) . ', raw_material_idle_days DESC');
        $orderBy = preg_replace('/\bidle_time_days\b/', 'raw_material_idle_days', $orderBy);

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/technical-state/filters */
    public function technicalFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $source = $this->technicalFrom([]);
        if (!$source['reportDt']) {
            return $this->json($response, ['wagon_type' => [], 'wagon_state' => []]);
        }
        $types = $this->db->fetchAll(
            "SELECT DISTINCT wagon_type_code FROM {$source['from']} WHERE wagon_type_code IS NOT NULL ORDER BY wagon_type_code",
            $source['bindings']
        );
        $states = $this->db->fetchAll(
            "SELECT DISTINCT wagon_state FROM {$source['from']} WHERE wagon_state IS NOT NULL ORDER BY wagon_state",
            $source['bindings']
        );
        return $this->json($response, ['wagon_type' => array_column($types, 'wagon_type_code'),
                                     'wagon_state' => array_column($states, 'wagon_state')]);
    }

    /** GET /api/technical-state/detail */
    public function technicalDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $repair = (string) ($params['repair'] ?? '');
        if (!in_array($repair, ['', 'overdue', 'soon', 'missing'], true)) {
            return $this->json($response->withStatus(400), ['error' => 'Выберите срок ремонта']);
        }
        $source = $this->technicalFrom($params);
        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => [], 'reports' => []]);
        }
        $bindings = $source['bindings'];
        $where = '1=1' . $this->wagonNoCond($params, $bindings);
        foreach (['wagon_type' => 'wagon_type_code', 'wagon_state' => 'wagon_state'] as $param => $field) {
            if (($params[$param] ?? '') !== '') {
                $where .= " AND $field = :$param";
                $bindings[$param] = $params[$param];
            }
        }
        if ($repair === 'overdue') {
            $where .= ' AND TRUNC(next_repair_dt) < TRUNC(report_dt)';
        } elseif ($repair === 'soon') {
            $where .= ' AND TRUNC(next_repair_dt) BETWEEN TRUNC(report_dt) AND TRUNC(report_dt) + 30';
        } elseif ($repair === 'missing') {
            $where .= ' AND next_repair_dt IS NULL';
        }
        $fields = $this->selectFields((string) ($params['fields'] ?? ''));
        $order = $this->orderBY($params, 'wagon_no');
        $rows = $this->db->fetchAll("SELECT $fields FROM {$source['from']} WHERE $where ORDER BY $order", $bindings);
        return $this->json($response, ['rows' => $rows, 'reports' => $source['reports']]);
    }

    /** Технические данные из актуальных справок без дублей вагонов. */
    private function technicalFrom(array $params): array
    {
        $source = $this->dislFrom(['report_dt' => $params['report_dt'] ?? null]);
        if (!$source['reportDt']) {
            return $source;
        }
        $source['reports'] = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        $source['from'] = "(SELECT * FROM (
            SELECT xdr.*, ROW_NUMBER() OVER (
                PARTITION BY TRIM(wagon_no)
                ORDER BY oper_dt DESC NULLS LAST, report_dt DESC,
                         CASE WHEN type_reference = 'Отправка' THEN 0 ELSE 1 END, id DESC
            ) AS technical_row FROM {$source['from']} xdr
        ) WHERE technical_row = 1 AND TRIM(wagon_no) NOT LIKE '0%'
            AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%')";
        return $source;
    }

    /** GET /api/claims/filters */
    public function claimsFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $source = $this->claimsFrom($request->getQueryParams());
        if (!$source['from']) {
            return $this->json($response, ['stations' => [], 'wagon_type' => []]);
        }
        $stations = $this->db->fetchAll(
            "SELECT station_code, MIN(dest_station) AS station_name FROM {$source['from']}
              WHERE station_code IS NOT NULL GROUP BY station_code ORDER BY station_name",
            $source['bindings']
        );
        $types = $this->db->fetchAll(
            "SELECT DISTINCT wagon_type_code FROM {$source['from']} ORDER BY wagon_type_code",
            $source['bindings']
        );
        return $this->json($response, ['stations' => $stations, 'wagon_type' => array_column($types, 'wagon_type_code')]);
    }

    /** GET /api/claims/summary */
    public function claimsSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->claimsFrom($params);
        try {
            $rows = (new \App\Services\ClaimService($this->db, (int) $this->organizations->id()))->rows($source, $params);
        } catch (\InvalidArgumentException $error) {
            return $this->json($response->withStatus(400), ['error' => $error->getMessage()]);
        }
        $groups = [];
        $empty = ['wagon_type_code' => 'Общий итог', 'total' => 0, 'penalty_count' => 0,
                  'payable_days' => 0, 'amount' => 0, 'incomplete_count' => 0];
        $total = $empty;
        foreach ($rows as $row) {
            $type = $row['wagon_type_code'] ?: 'Прочие';
            if (!isset($groups[$type])) {
                $groups[$type] = array_replace($empty, ['wagon_type_code' => $type]);
            }
            foreach ([$type, null] as $key) {
                $group = &$total;
                if ($key !== null) {
                    $group = &$groups[$key];
                }
                $group['total']++;
                $group['penalty_count'] += (int) ($row['claim_status'] === 'penalty');
                $group['incomplete_count'] += (int) ($row['claim_status'] === 'incomplete');
                $group['payable_days'] += $row['payable_days'] ?? 0;
                $group['amount'] = round($group['amount'] + ($row['amount'] ?? 0), 2);
                unset($group);
            }
        }
        ksort($groups);
        return $this->json($response, ['rows' => array_values($groups), 'totals' => $total,
                                     'total' => count($rows), 'reports' => $source['reports']]);
    }

    /** GET /api/claims/detail */
    public function claimsDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->claimsFrom($params);
        try {
            $rows = (new \App\Services\ClaimService($this->db, (int) $this->organizations->id()))->rows($source, $params);
        } catch (\InvalidArgumentException $error) {
            return $this->json($response->withStatus(400), ['error' => $error->getMessage()]);
        }
        $fields = array_values(array_filter(array_map('trim', explode(',', (string) ($params['fields'] ?? '')))));
        $sort = array_values(array_filter(array_map('trim', explode(',', (string) ($params['sort'] ?? '')))));
        $directions = explode(',', (string) ($params['sort_dir'] ?? ''));
        if ($sort && $rows) {
            $allowed = array_keys($rows[0]);
            $sort = array_values(array_filter($sort, static fn($key) => in_array($key, $allowed, true)));
            usort($rows, static function ($a, $b) use ($sort, $directions) {
                foreach ($sort as $i => $key) {
                    if ($a[$key] === $b[$key]) continue;
                    if ($a[$key] === null) return 1;
                    if ($b[$key] === null) return -1;
                    $result = is_numeric($a[$key]) && is_numeric($b[$key])
                        ? (float) $a[$key] <=> (float) $b[$key] : strcmp((string) $a[$key], (string) $b[$key]);
                    if ($result) return strtolower($directions[$i] ?? 'asc') === 'desc' ? -$result : $result;
                }
                return strcmp($a['wagon_no'], $b['wagon_no']);
            });
        }
        if ($fields) {
            $keys = array_flip($fields);
            $rows = array_map(static fn($row) => array_intersect_key($row, $keys), $rows);
        }
        return $this->json($response, ['rows' => $rows]);
    }

    /** Вагоны на станциях назначения из актуальных справок. */
    private function claimsFrom(array $params): array
    {
        $reports = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        if (!$reports) {
            return ['from' => '', 'bindings' => [], 'reports' => []];
        }
        $condition = $this->latestDtCondition($reports, 'xdr');
        $from = "(SELECT TRIM(wagon_no) AS wagon_no, wagon_type_code AS wagon_type_name,
                         " . self::WAG_TYPE_EXPR . " AS wagon_type_code,
                         cargo_name, prev_cargo, owner, consignor, consignor_name, consignee, consignee_name,
                         oper_station, dest_station, trip_end_dt, asoup_arrive_dt, report_dt, waybill_no, type_reference,
                         REGEXP_SUBSTR(dest_station, '[0-9]{6}') AS station_code
                  FROM (
                      SELECT xdr.*, ROW_NUMBER() OVER (
                          PARTITION BY TRIM(wagon_no)
                          ORDER BY oper_dt DESC NULLS LAST, report_dt DESC,
                                   CASE WHEN type_reference = 'Подход' THEN 0 ELSE 1 END, id DESC
                      ) AS claim_row
                        FROM xx_dislocation_rjd xdr WHERE {$condition['sql']}
                  )
                 WHERE claim_row = 1 AND dist_remain_km = 0
                   AND REGEXP_SUBSTR(oper_station, '[0-9]{6}') = REGEXP_SUBSTR(dest_station, '[0-9]{6}')
                   AND TRIM(wagon_no) NOT LIKE '0%'
                   AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                       NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%')";
        return ['from' => $from, 'bindings' => $condition['params'], 'reports' => $reports];
    }

    public function analysisFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';
        $this->organizations->addFilter($whereCond, $bindings);

        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo !== '') {
            $whereCond .= ' AND wagon_no IN (' . $this->parserInValues($wagonNo, ';', 'wagon_no', $bindings) . ')';
        }

        $dateFrom = $params['date_from'] ?? '';
        if ($dateFrom !== '') {
            $whereCond .= " AND TRUNC(oper_dt) >= TO_DATE(:date_from, 'YYYY-MM-DD')";
            $bindings['date_from'] = $dateFrom;
        }

        $dateTo = $params['date_to'] ?? '';
        if ($dateTo !== '') {
            $whereCond .= " AND TRUNC(oper_dt) <= TO_DATE(:date_to, 'YYYY-MM-DD')";
            $bindings['date_to'] = $dateTo;
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name
             FROM xx_dislocation_rjd
             WHERE $whereCond
             ORDER BY cargo_name",
            $bindings
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
        ]);
    }
    /** GET /api/analysis/period/detail */
    public function analysisPeriod(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';
        $this->organizations->addFilter($whereCond, $bindings);

        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo !== '') {
            $whereCond .= ' AND wagon_no IN (' . $this->parserInValues($wagonNo, ';', 'wagon_no', $bindings) . ')';
        }

        $dateFrom = $params['date_from'] ?? '';
        if ($dateFrom !== '') {
            $whereCond .= " AND TRUNC(oper_dt) >= TO_DATE(:date_from, 'YYYY-MM-DD')";
            $bindings['date_from'] = $dateFrom;
        }

        $dateTo = $params['date_to'] ?? '';
        if ($dateTo === '') {
            $dateTo = date('Y-m-d');
        }
        $whereCond .= " AND TRUNC(oper_dt) <= TO_DATE(:date_to, 'YYYY-MM-DD')";
        $bindings['date_to'] = $dateTo;

        $cargo = $params['cargo'] ?? '';
        if ($cargo !== '') {
            $whereCond .= " AND cargo_name = :cargo";
            $bindings['cargo'] = $cargo;
        }

        $selectCols = $this->selectFields($params['fields'] ?? '');

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT $selectCols
             FROM xx_dislocation_rjd
             WHERE $whereCond
             ORDER BY oper_dt DESC, wagon_no",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    // =========================================================================
    // Источники данных (FROM + базовый WHERE каждого раздела)
    // Каждый возвращает: ['from' => <subquery>, 'bindings' => [...], 'reportDt' => string|null]
    // =========================================================================

    /** Дислокация: объединение двух типов справок ('Подход' + 'Отправка'). */
    private function dislFrom(array $params): array
    {
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        if (empty($dtsByType)) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }
        $cond = $this->latestDtCondition($dtsByType);
        $bindings = $cond['params'];
        $whereCond = $cond['sql'];

        $cargo = $params['cargo'] ?? null;
        if ($cargo) {
            $whereCond .= " AND UPPER(REPLACE(COALESCE(cargo_name,''), 'Ё', 'Е')) = UPPER(REPLACE(:cargo_f, 'Ё', 'Е'))";
            $bindings['cargo_f'] = $cargo;
        }
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        return [
            'from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)",
            'bindings' => $bindings,
            'reportDt' => max($dtsByType),
        ];
    }

    /** Подход: вагоны станция назначения УГЛ  (type_reference='Подход'). */
    private function approachFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND type_reference = 'Подход'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND UPPER(dest_station) LIKE '%УГЛ%'";
            // Не учитывать служебные номера и вагоны, исключённые из модели дороги.
            $whereCond .= " AND TRIM(wagon_no) NOT LIKE '0%'";
            $whereCond .= " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                               NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        }
        $this->organizations->addFilter($whereCond, $bindings);

        $cargo = $params['cargo'] ?? null;
        if ($cargo) {
            $whereCond .= " AND UPPER(REPLACE(COALESCE(cargo_name,''), 'Ё', 'Е')) = UPPER(REPLACE(:cargo_f, 'Ё', 'Е'))";
            $bindings['cargo_f'] = $cargo;
        }
        $prevCargo = $params['prev_cargo'] ?? null;
        if ($prevCargo) {
            $whereCond .= " AND UPPER(REPLACE(COALESCE(prev_cargo,''), 'Ё', 'Е')) = UPPER(REPLACE(:prev_cargo_f, 'Ё', 'Е'))";
            $bindings['prev_cargo_f'] = $prevCargo;
        }
        $whereCond .= $this->wagonNoCond($params, $bindings);
        // Фильтр по реестру исключений временно отключён.
        // $whereCond .= $this->excludedWagonsCondition();

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Отправление: вагоны с положительным оставшимся расстоянием. */
    private function departureFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND type_reference = 'Отправка'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND REGEXP_LIKE(UPPER(TRIM(depart_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')";
            $whereCond .= " AND dist_remain_km > 0";
            $whereCond .= " AND TRIM(wagon_no) NOT LIKE '0%'";
            $whereCond .= " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                               NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        }
        $this->organizations->addFilter($whereCond, $bindings);

        $cargo = $params['cargo'] ?? null;
        if ($cargo) {
            $whereCond .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }
        $destStation = $params['dest_station'] ?? null;
        if ($destStation) {
            $whereCond .= ' AND dest_station = :dest_station';
            $bindings['dest_station'] = $destStation;
        }
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Погрузка: рейсы со станции отправления без ограничения по дате начала. */
    private function loadingFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS')"
            . " AND type_reference = 'Отправка'"
            . " AND trip_start_dt IS NOT NULL"
            . " AND TRIM(wagon_no) NOT LIKE '0%'"
            . " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                   NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND REGEXP_LIKE(UPPER(TRIM(depart_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')";
        }
        $this->organizations->addFilter($whereCond, $bindings);
        $period = trim($params['loading_period'] ?? '');
        if ($period !== '') {
            $whereCond .= ' AND (' . $this->loadingPeriodCondition($period, $bindings) . ')';
        }


        $cargo = $params['cargo'] ?? null;
        if ($cargo) {
            $whereCond .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Сырьё: прибывшие вагоны с грузом тяжелее 12 тонн. */
    private function rawFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS')"
            . " AND type_reference = 'Подход'"
            . " AND dist_remain_km = 0"
            . " AND cargo_weight_kg > 12000"
            . " AND trip_end_dt IS NOT NULL AND TRUNC(trip_end_dt) <= TRUNC(report_dt)"
            . " AND TRIM(wagon_no) NOT LIKE '0%'"
            . " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                   NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND REGEXP_LIKE(UPPER(TRIM(dest_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')"
                . " AND REGEXP_LIKE(UPPER(TRIM(oper_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')";
        }
        $this->organizations->addFilter($whereCond, $bindings);
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        $from = "(SELECT xdr.*,
                         GREATEST(1, TRUNC(report_dt) - TRUNC(trip_end_dt)) AS raw_material_idle_days
                    FROM xx_dislocation_rjd xdr WHERE $whereCond)";
        $from = "(SELECT raw_data.*,
                         CASE
                             WHEN raw_material_idle_days > 12 THEN 'Простой свыше 12 суток'
                             WHEN raw_material_idle_days = 1 THEN 'Простой 1 сутки'
                             ELSE 'Простой ' || TO_CHAR(raw_material_idle_days) || ' суток'
                         END AS raw_material_idle_label
                    FROM $from raw_data)";
        return ['from' => $from, 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Простои: добавляет вычисляемые колонки (idle_time_name, m_wagon_type_code) в подзапрос. */
    private function downtimeFrom(array $params): array
    {
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        if (!$dtsByType) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }
        $cond = $this->latestDtCondition($dtsByType);
        $bindings = $cond['params'];
        $baseCond = $cond['sql'] . $this->wagonNoCond($params, $bindings);
        // Фильтр по реестру исключений временно отключён.
        // $baseCond .= $this->excludedWagonsCondition();
        $source = [
            'from' => "(SELECT * FROM xx_dislocation_rjd WHERE $baseCond)",
            'reportDt' => max($dtsByType),
        ];
        $whereCond = "dist_remain_km = 0 AND UPPER(TRIM(oper_station)) = UPPER(TRIM(dest_station))";
        $whereCond .= " AND TRIM(wagon_no) NOT LIKE '0%'"
            . " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                   NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        $destStation = trim($params['dest_station'] ?? '');
        if ($destStation !== '') {
            $whereCond .= ' AND dest_station = :dest_station';
            $bindings['dest_station'] = $destStation;
        }
        $bindings['idle_fact_date'] = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Moscow')))->format('Y-m-d');
        $from = "(SELECT xdr.*,
                         CASE WHEN trip_end_dt IS NOT NULL
                                   AND TRUNC(trip_end_dt) <= TO_DATE(:idle_fact_date, 'YYYY-MM-DD')
                              THEN GREATEST(1, TO_DATE(:idle_fact_date, 'YYYY-MM-DD') - TRUNC(trip_end_dt))
                         END AS station_idle_days
                    FROM {$source['from']} xdr WHERE $whereCond)";

        $idleCond = '1=1';
        if (isset($params['min_days']) && $params['min_days'] !== '') {
            $idleCond .= ' AND station_idle_days >= :min_days';
            $bindings['min_days'] = max(0, (int) $params['min_days']);
        }
        if (isset($params['max_days']) && $params['max_days'] !== '') {
            $idleCond .= ' AND station_idle_days <= :max_days';
            $bindings['max_days'] = (int) $params['max_days'];
        }
        $from = "(SELECT idle_data.*,
                         CASE
                             WHEN station_idle_days IS NULL THEN 'Недостаточно данных о начале простоя'
                             WHEN station_idle_days < 5 THEN 'простой до 5 сут.'
                             WHEN station_idle_days < 7 THEN 'простой от 5 до 7 сут.'
                             WHEN station_idle_days < 10 THEN 'простой от 7 до 10 сут.'
                             WHEN station_idle_days < 20 THEN 'простой от 10 до 20 сут.'
                             WHEN station_idle_days < 30 THEN 'простой от 20 до 30 сут.'
                             ELSE 'простой от 30 и более'
                         END AS idle_time_name,
                         CASE
                             WHEN station_idle_days IS NULL THEN 7
                             WHEN station_idle_days < 5 THEN 1
                             WHEN station_idle_days < 7 THEN 2
                             WHEN station_idle_days < 10 THEN 3
                             WHEN station_idle_days < 20 THEN 4
                             WHEN station_idle_days < 30 THEN 5
                             ELSE 6
                         END AS idle_time_order_by,
                         " . self::WAG_TYPE_EXPR . " AS m_wagon_type_code,
                         " . self::WAG_STATE . " AS m_wag_state,
                         'Кол-во' AS fixed_col_label
                    FROM $from idle_data WHERE $idleCond)";

        return ['from' => $from, 'bindings' => $bindings, 'reportDt' => $source['reportDt']];
    }

    // =========================================================================
    // Построители SQL-запросов (общие)
    // =========================================================================

    /**
     * Строит и выполняет запрос сводной таблицы.
     * Общий метод для всех разделов 
     *
     * $source   — источник данных из *From(): ['from', 'bindings']
     * $rowDims  — поля строк сводной (GROUP BY строки)
     * $colDefs  — колонки сводной: [['alias' => ..., 'expr' => ...], ...]
     */
    private function summaryReport(array $source, array $rowDims, array $colDefs, ?string $rowOrder = null): array
    {
        $selectRow = implode(', ', $rowDims);
        $rowOrder ??= $selectRow;
        $selectCols = implode(', ', array_map(fn($c) => $this->applyFormat($c) . " AS {$c['alias']}", $colDefs));
        $groupByCols = implode(', ', array_map(fn($c) => $this->applyFormat($c), $colDefs));
        $colAliases = array_column($colDefs, 'alias');
        $orderByCols = implode(', ', $colAliases);

        $rows = $this->db->fetchAll(
            "SELECT $selectRow, $selectCols, COUNT(*) AS cnt
             FROM {$source['from']}
             GROUP BY $selectRow, $groupByCols
             ORDER BY $rowOrder, $orderByCols",
            $source['bindings']
        );

        return $this->roadTable($rows, $rowDims, $colAliases);
    }

    /**
     * WHERE для детализации: фильтр по строкам сводной + фильтр по колонкам сводной.
     *
     * $rowDims — поля GROUP BY строк (из group_by параметра)
     * $params  — все URL-параметры запроса
     * $bindings — биндинги, пополняются по ссылке
     */
    private function applyDetailFilters(array $rowDims, array $params, array &$bindings): string
    {
        // Фильтр по строкам: для каждого поля из rowDims ищет одноимённый параметр в URL
        $whereCond = '';
        foreach ($rowDims as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                $safe = preg_replace('/[^a-z0-9_]/i', '', $field);
                $whereCond .= " AND $field = :row_$safe";
                $bindings["row_$safe"] = $params[$field];
            }
        }

        // Фильтр по колонкам: берёт выражения из единого реестра colDimRegistry
        foreach ($this->colDimRegistry() as $alias => $def) {
            $value = $params[$def['param']] ?? null;
            if ($value !== null && $value !== '') {
                $bind = 'col_' . $alias;
                $whereCond .= " AND {$def['expr']} = :$bind";
                $bindings[$bind] = $value;
            }
        }

        return $whereCond;
    }

    // =========================================================================
    // Реестры и вспомогательные SQL-шаблоны
    // =========================================================================

    /**
     * Единый подход вычисляемых колонок сводной.
     * Источник для summaryReport (expr в SELECT/GROUP BY)
     * и для applyDetailFilters (expr в WHERE, param — имя URL-параметра).
     *
     * Добавить новую вычисляемую колонку
     * param должен совпадать с colDims[].paramName в app.js.
     */
    private function colDimRegistry(): array
    {
        return [
            'wagon_type_code' => ['expr' => self::WAG_TYPE_EXPR, 'param' => 'wagon_type'],
            'cargo_w_type' => ['expr' => self::WAG_STATE, 'param' => 'cargo_state'],
        ];
    }

    /**
     * Раскрывает col_by (алиасы через запятую) в определения колонок для summaryReport.
     * Возвращает [['alias' => ..., 'expr' => ...], ...].
     */
    private function resolveColDims(string $colBy, array $defaultAliases): array
    {
        $registry = $this->colDimRegistry();
        $aliases = $this->parseGroupBy($colBy, $defaultAliases);
        return array_values(array_filter(
            array_map(
                fn($a) => isset($registry[$a]) ? ['alias' => $a, 'expr' => $registry[$a]['expr']] : null,
                $aliases
            ),
            fn($c) => $c !== null
        ));
    }

    /** Если у колонки задан formatData — оборачивает expr в TO_CHAR(expr, 'mask'). */
    private function applyFormat(array $colDef): string
    {
        if (!empty($colDef['formatData'])) {
            return "TO_CHAR({$colDef['expr']}, '{$colDef['formatData']}')";
        }
        return $colDef['expr'];
    }

    /**
     * Парсит строку «field1,field2,...» в массив безопасных имён полей.
     * Если строка пуста или содержит только небезопасные имена — возвращает $defaults.
     */
    private function parseGroupBy(string $raw, array $defaults): array
    {
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => self::isSafeField($f)
        ));
        return $fields ?: $defaults;
    }

    /** Строит SELECT-список из переданных полей; fallback — wagon_no. */
    private function selectFields(string $raw): string
    {
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => self::isSafeField($f)
        ));
        return $fields ? implode(', ', $fields) : 'wagon_no';
    }

    /** Строит ORDER BY из параметров sort/sort_dir/sort_type; fallback — $default. */
    private function orderBY(array $params, string $default): string
    {
        $sortRaw = trim($params['sort'] ?? '');
        if ($sortRaw === '') {
            return $default;
        }
        $fields = array_map('trim', explode(',', $sortRaw));
        $dirs = array_map('trim', explode(',', $params['sort_dir'] ?? ''));
        $types = array_map('trim', explode(',', $params['sort_type'] ?? ''));

        $parts = [];
        foreach ($fields as $i => $field) {
            if ($field === '' || !self::isSafeField($field)) {
                continue;
            }
            $dir = strtoupper($dirs[$i] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $expr = strtolower($types[$i] ?? '') === 'number' ? "TO_NUMBER($field)" : $field;
            $parts[] = "$expr $dir";
        }

        return $parts ? implode(', ', $parts) : $default;
    }

    /** Добавляет фильтр wagon_no IN (...) если передан параметр wagon_no (через ';'). */
    private function wagonNoCond(array $params, array &$bindings): string
    {
        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo === '')
            return '';
        return ' AND wagon_no IN (' . $this->parserInValues($wagonNo, ';', 'wagon_no', $bindings) . ')';
    }

    /** Строит плейсхолдеры для SQL IN из строки с разделителем. */
    private function parserInValues(string $raw, string $delimiter, string $paramPrefix, array &$bindings): string
    {
        $items = array_filter(array_map('trim', explode($delimiter, $raw)));
        if (empty($items)) {
            return '';
        }
        $placeholders = [];
        foreach ($items as $i => $value) {
            $key = "{$paramPrefix}_{$i}";
            $placeholders[] = ':' . $key;
            $bindings[$key] = $value;
        }
        return implode(', ', $placeholders);
    }

    /** Допускает только безопасные имена полей: буквы, цифры, _ */
    private static function isSafeField(string $f): bool
    {
        return $f !== '' && (bool) preg_match('/^[a-z_][a-z0-9_]*$/iD', $f);
    }

    private function excludedWagonsCondition(): string
    {
        return $this->organizations->isMtf() ? self::EXCLUDED_WAGONS_COND : '';
    }

    // =========================================================================
    // Вспомогательные методы для работы с датами справок
    // =========================================================================

    /** MAX(report_dt) для указанного типа справки; если $dt передан — возвращает его сразу. */
    private function getReportDt(?string $dt, ?string $typeRef = null): ?string
    {
        if ($dt) {
            return $dt;
        }
        $sql = 'SELECT MAX(report_dt) AS dt FROM xx_dislocation_rjd';
        $filter = $this->organizations->filter();
        $sql .= ' WHERE ' . $filter['sql'];
        $params = $filter['params'];
        if ($typeRef !== null) {
            $sql .= ' AND type_reference = :type_ref';
            $params['type_ref'] = $typeRef;
        }
        $row = $this->db->fetchOne($sql, $params);
        return $row['dt'] ?? null;
    }

    /** [type_reference => MAX(report_dt)] для указанных типов справок. */
    public function getLatestDtsByType(?string $dt = null, ?array $types = null): array
    {
        return (new \App\Services\ReportService($this->db, $this->organizations))->getLatestDtsByType($dt, $types);
    }

    /** Условие отбора актуальных справок с ограничением по организации. */
    public function latestDtCondition(array $dtsByType, string $alias = ''): array
    {
        return (new \App\Services\ReportService($this->db, $this->organizations))->latestDtCondition($dtsByType, $alias);
    }

    // =========================================================================
    // Построитель иерархической структуры для сводных таблиц
    // =========================================================================

    /**
     * Преобразует плоский результат SELECT (строки × колонки) в структуру для фронтенда:
     * { roads: [{road, stations: [{...v[]}], total: [], grand_total}, ...], col_groups/cols, metrics, total }
     *
     * $rows      — строки из БД
     * $rowDims   — поля строк (groupKeys[0] = верхний уровень, последний = нижний)
     * $colAliases — алиасы колонок в порядке, соответствующем flat-индексу
     */
    private function roadTable(array $rows, array $rowDims, array $colAliases): array
    {
        $topKey = $rowDims[0];
        $subKeys = array_slice($rowDims, 1);
        $nColLevels = count($colAliases);

        // Собираем все уникальные значения по каждому уровню колонок
        $colValues = array_fill(0, $nColLevels, []);
        $colIndex = array_fill(0, $nColLevels, []);
        foreach ($rows as $r) {
            foreach ($colAliases as $k => $alias) {
                $v = (string) ($r[$alias] ?? '');
                if ($v !== '' && !isset($colIndex[$k][$v])) {
                    $colIndex[$k][$v] = count($colValues[$k]);
                    $colValues[$k][] = $v;
                }
            }
        }

        $colDims = array_map(fn($vals) => max(1, count($vals)), $colValues);
        $nFlat = (int) array_product($colDims);
        $roads = [];

        foreach ($rows as $r) {
            $topVal = (string) ($r[$topKey] ?? 'Не указана');
            $subComposite = implode('|', array_map(fn($k) => (string) ($r[$k] ?? ''), $subKeys)) ?: 'Не указана';
            $cnt = (int) $r['cnt'];

            if (!isset($roads[$topVal])) {
                $roads[$topVal] = [$topKey => $topVal, 'stations' => [], 'total' => array_fill(0, $nFlat, 0), 'grand_total' => 0];
            }
            if (!isset($roads[$topVal]['stations'][$subComposite])) {
                $stData = ['v' => array_fill(0, $nFlat, 0)];
                foreach ($subKeys as $k) {
                    $stData[$k] = (string) ($r[$k] ?? '');
                }
                $roads[$topVal]['stations'][$subComposite] = $stData;
            }

            $firstColVal = (string) ($r[$colAliases[0]] ?? '');
            if ($firstColVal === '' || !isset($colIndex[0][$firstColVal])) {
                continue;
            }

            $flatIdx = 0;
            foreach ($colAliases as $k => $alias) {
                $v = (string) ($r[$alias] ?? '');
                $flatIdx = $flatIdx * $colDims[$k] + ($colIndex[$k][$v] ?? 0);
            }

            $roads[$topVal]['stations'][$subComposite]['v'][$flatIdx] += $cnt;
            $roads[$topVal]['total'][$flatIdx] += $cnt;
            $roads[$topVal]['grand_total'] += $cnt;
        }

        foreach ($roads as &$road) {
            $road['stations'] = array_values($road['stations']);
        }
        unset($road);

        $roadList = array_values($roads);
        $metrics = array_map(fn($r) => ['label' => $r[$topKey], 'total' => $r['grand_total']], $roadList);
        $grandTotal = array_sum(array_column($metrics, 'total'));

        if ($nColLevels <= 1) {
            return ['cols' => $colValues[0], 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 20), 'total' => $grandTotal];
        }

        $buildColTree = function (int $level) use (&$buildColTree, $colValues, $nColLevels) {
            $isLeaf = $level === $nColLevels - 1;
            $out = [];
            foreach ($colValues[$level] as $v) {
                $out[] = $isLeaf ? $v : ['label' => $v, 'subs' => $buildColTree($level + 1)];
            }
            return $out;
        };

        return ['col_groups' => $buildColTree(0), 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 20), 'total' => $grandTotal];
    }

    /**
     * Экспорт виртуальной таблицы (динамической детализации) из JS
     */
    public function exportVirtualTable(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $rawData = json_decode($parsedBody['export_data'] ?? '[]', true);
        $filename = $parsedBody['filename'] ?? 'детализация';

        $cols = $rawData['cols'] ?? [];
        $rows = $rawData['rows'] ?? [];

        return \App\ExcelExporter::download($response, $cols, $rows, $filename);
    }

    /**
     * Экспорт сложных матричных таблиц (двухуровневых шахматок дашборда)
     */
    public function exportMatrixTable(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $rawData = json_decode($parsedBody['matrix_data'] ?? '[]', true);
        $filename = $parsedBody['filename'] ?? 'Сводный_отчет';

        $colGroups = $rawData['col_groups'] ?? [];
        $cols = $rawData['cols'] ?? [];
        $groupCols = $rawData['group_cols'] ?? [];
        $roads = $rawData['roads'] ?? [];

        return \App\ExcelExporter::downloadMatrix($response, $colGroups, $roads, $filename, $groupCols, $cols);
    }

    private function json(ResponseInterface $response, $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
