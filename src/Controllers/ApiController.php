<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ApiController
{
    private const WAG_TYPE_EXPR = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code)";
    private const WAG_STATE = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_get_state_wagon(cargo_weight_kg)";

    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
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
        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';

        $kpiType = trim($params['kpi_type'] ?? '');
        if ($kpiType !== '') {
            $whereCond .= ' AND type = :kpi_type';
            $bindings['kpi_type'] = $kpiType;
        }

        try {
            $rows = $this->db->fetchAll(
                "SELECT kpi.id, kpi.type, kpi.label AS x_label,
                        xx_rjd_dislocation_new_pkg.set_kpi_label(kpi.id)         AS x_value,
                        xx_rjd_dislocation_new_pkg.fnc_get_kpi_trend_pct(kpi.id) AS trend_pct,
                        xx_rjd_dislocation_new_pkg.fnc_get_kpi_trend_dir(kpi.id) AS trend_dir
                 FROM XX_KPI_TABLE_V kpi
                 WHERE $whereCond",
                $bindings
            );
        } catch (\Throwable $e) {
            // тренды недоступны (пакет не скомпилирован) — грузим без них
            $rows = $this->db->fetchAll(
                "SELECT kpi.id, kpi.type, kpi.label AS x_label,
                        xx_rjd_dislocation_new_pkg.set_kpi_label(kpi.id) AS x_value,
                        NULL AS trend_pct,
                        NULL AS trend_dir
                 FROM XX_KPI_TABLE_V kpi
                 WHERE $whereCond",
                $bindings
            );
        }

        $values = [];
        foreach ($rows as $r) {
            $values[] = [
                'id'     => $r['id']      ?? '',
                'value'  => $r['x_value'] ?? '0',
                'label'  => $r['x_label'] ?? '',
                'trend'  => $r['trend_dir'] ?? '',
                'change' => $r['trend_pct'] ?? '',
            ];
        }

        $dtRow = $this->db->fetchAll("SELECT MAX(report_dt) AS latest_dt FROM xx_dislocation_rjd");
        $latestDt = $dtRow[0]['latest_dt'] ?? null;
        try {
            $updatedAt = $latestDt ? (new \DateTime($latestDt))->format('d.m.Y H:i') : null;
        } catch (\Exception $e) {
            $updatedAt = $latestDt;
        }

        return $this->json($response, [
            'updated_at' => $updatedAt,
            'sections'   => [['values' => $values]],
        ]);
    }
    /** GET /api/dislocation/filters */
    public function dislFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            'SELECT TRUNC(report_dt) AS report_date, type_reference, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             GROUP BY TRUNC(report_dt), type_reference
             ORDER BY TRUNC(report_dt) DESC, type_reference'
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
            $whereCond .= ' AND CASE WHEN XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.fnc_check_kpi(:kpi_id, xdr.id) THEN 1 ELSE 0 END = 1';
            $bindings['kpi_id'] = (int) $kpiId;
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
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
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

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
        return $this->json($response, $this->summaryReport($source, $rowDims, $colDefs));
    }

    /** GET /api/loading/detail */
    public function loadingDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['depart_road', 'depart_station']);
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
        $orderBy = $this->orderBY($params, 'idle_time_days DESC');

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

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['cargo_name']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
        $result = $this->summaryReport($source, $rowDims, $colDefs);

        $maxIdleRow = $this->db->fetchOne("SELECT MAX(idle_time_days) AS max_idle FROM {$source['from']}", $source['bindings']);
        $result['max_idle'] = (float) ($maxIdleRow['max_idle'] ?? 0);

        return $this->json($response, $result);
    }

    /** GET /api/raw-material/detail */
    public function rawDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['cargo_name']);
        $source = $this->rawFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);
        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims) . ', idle_time_days DESC');

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    public function analysisFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';

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

        return [
            'from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)",
            'bindings' => $bindings,
            'reportDt' => max($dtsByType),
        ];
    }

    /** Подход: вагоны с ненулевым остатком пути (type_reference='Подход'). */
    private function approachFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND type_reference = 'Подход' and upper(dest_station) like '%УГЛ%'";

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

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Отправление: вагоны с oper_mnemonic='ОТПР' (type_reference='Отправка'). */
    private function departureFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') and upper(dest_station) not like '%УГЛ%'";

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

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Погрузка: вагоны с грузом (cargo_weight_kg > 0). */
    private function loadingFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";

        $cargo = $params['cargo'] ?? null;
        if ($cargo) {
            $whereCond .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }
        $whereCond .= $this->wagonNoCond($params, $bindings);

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Сырьё: гружёные вагоны с ненулевым простоем. */
    private function rawFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS')"
            . " AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0"
            . " AND idle_time_days IS NOT NULL AND idle_time_days != 0";
        $whereCond .= $this->wagonNoCond($params, $bindings);

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Простои: добавляет вычисляемые колонки (idle_time_name, m_wagon_type_code) в подзапрос. */
    private function downtimeFrom(array $params): array
    {
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null);
        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $bindings = $cond['params'];
        $whereCond = "{$cond['sql']} AND idle_time_days IS NOT NULL AND nvl(idle_time_days,0) != 0";

        $minDays = max(0, (int) ($params['min_days'] ?? 1));
        if ($minDays > 0) {
            $whereCond .= ' AND idle_time_days >= :min_days';
            $bindings['min_days'] = $minDays;
        }
        $maxDays = isset($params['max_days']) && $params['max_days'] !== '' ? (int) $params['max_days'] : null;
        if ($maxDays !== null) {
            $whereCond .= ' AND idle_time_days <= :max_days';
            $bindings['max_days'] = $maxDays;
        }
        $destStation = trim($params['dest_station'] ?? '');
        if ($destStation !== '') {
            $whereCond .= ' AND dest_station = :dest_station';
            $bindings['dest_station'] = $destStation;
        }
        $whereCond .= $this->wagonNoCond($params, $bindings);

        $reportDt = !empty($dtsByType) ? max($dtsByType) : null;
        $from = "(SELECT xdr.*
                        , XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.fnc_get_downtime_wagon(idle_time_days,'name')     AS idle_time_name
                        , TO_NUMBER(XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.fnc_get_downtime_wagon(idle_time_days,'order_by')) AS idle_time_order_by
                        , " . self::WAG_TYPE_EXPR . " AS m_wagon_type_code
                        , " . self::WAG_STATE . " AS m_wag_state
                        /*, 'Кол-во' AS fixed_col_label*/
                   FROM xx_dislocation_rjd xdr
                   WHERE $whereCond
                   ORDER BY idle_time_order_by)";

        return ['from' => $from, 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    // =========================================================================
    // Построители SQL-запросов (общие)
    // =========================================================================

    /**
     * Строит и выполняет запрос сводной таблицы.
     * Единый метод для всех разделов 
     *
     * $source   — источник данных из *From(): ['from', 'bindings']
     * $rowDims  — поля строк сводной (GROUP BY строки)
     * $colDefs  — колонки сводной: [['alias' => ..., 'expr' => ...], ...]
     */
    private function summaryReport(array $source, array $rowDims, array $colDefs): array
    {
        $selectRow = implode(', ', $rowDims);
        $selectCols = implode(', ', array_map(fn($c) => $this->applyFormat($c) . " AS {$c['alias']}", $colDefs));
        $groupByCols = implode(', ', array_map(fn($c) => $this->applyFormat($c), $colDefs));
        $colAliases = array_column($colDefs, 'alias');
        $orderByCols = implode(', ', $colAliases);

        $rows = $this->db->fetchAll(
            "SELECT $selectRow, $selectCols, COUNT(*) AS cnt
             FROM {$source['from']}
             GROUP BY $selectRow, $groupByCols
             ORDER BY $selectRow, $orderByCols",
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
        $params = [];
        if ($typeRef !== null) {
            $sql .= ' WHERE type_reference = :type_ref';
            $params['type_ref'] = $typeRef;
        }
        $row = $this->db->fetchOne($sql, $params);
        return $row['dt'] ?? null;
    }

    /** [type_reference => MAX(report_dt)] для всех указанных типов. */
    public function getLatestDtsByType(?string $dt = null, ?array $types = null): array
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
     * Строит WHERE-фрагмент для фильтрации по нескольким report_dt (один на тип справки).
     * $alias — псевдоним таблицы, например 'xdr' → 'xdr.type_reference'.
     */
    public function latestDtCondition(array $dtsByType, string $alias = ''): array
    {
        if (empty($dtsByType)) {
            return ['sql' => '1=0', 'params' => []];
        }
        $col = fn(string $c) => $alias !== '' ? "$alias.$c" : $c;
        $parts = [];
        $params = [];
        $i = 0;
        foreach ($dtsByType as $type => $dt) {
            $parts[] = "({$col('type_reference')} = :ldt_type_{$i} AND {$col('report_dt')} = TO_DATE(:ldt_dt_{$i}, 'YYYY-MM-DD HH24:MI:SS'))";
            $params["ldt_type_{$i}"] = $type;
            $params["ldt_dt_{$i}"] = $dt;
            $i++;
        }
        return ['sql' => '(' . implode(' OR ', $parts) . ')', 'params' => $params];
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

    private function json(ResponseInterface $response, $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
