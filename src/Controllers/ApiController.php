<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * JSON API 
 */
class ApiController
{
    private const WAG_TYPE_EXPR = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code)"; // краткое название типа вагона для сводных таблиц
    private const WAG_STATE = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_get_state_wagon(cargo_weight_kg)"; // краткое состояние вагона (пор. / гр. )

    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /** Допускает только безопасные имена полей: буквы, цифры, _ (без точки и пробелов) */
    private static function isSafeField(string $f): bool
    {
        return $f !== '' && (bool) preg_match('/^[a-z_][a-z0-9_]*$/iD', $f);
    }

    /** Строит строку SELECT из переданных полей. */
    private function selectFields(string $raw): string
    {
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => self::isSafeField($f)
        ));
        return $fields ? implode(', ', $fields) : 'wagon_no';
    }

    /**
     * Парсит group_by=field1,field2,...
     */
    private function groupFields(string $raw, array $defaults, array $extra = []): array
    {
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => self::isSafeField($f)
        ));
        return $fields ?: $defaults;
    }

    /**
     * Строит ORDER BY из параметров sort/sort_dir/sort_type 
     */
    private function orderClause(array $params, string $default): string
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
            $expr = strtolower($types[$i] ?? '') === 'number'
                ? "TO_NUMBER($field)"
                : $field;
            $parts[] = "$expr $dir";
        }

        return $parts ? implode(', ', $parts) : $default;
    }

    /**
     * Реестр вычисляемых колонок сводной: alias → SQL-выражение.
     * col_by посылает ключи (alias), бэкенд подставляет выражение.
     * [['alias' => 'wagon_type_code', 'expr' => "..."], ...]
     */
    private function resolveColDims(string $colBy, array $defaultAliases): array
    {
        $wagExpr = self::WAG_TYPE_EXPR;
        $wagState = self::WAG_STATE;

        $registry = [
            'wagon_type_code' => ['alias' => 'wagon_type_code', 'expr' => $wagExpr],
            'cargo_w_type' => ['alias' => 'cargo_w_type', 'expr' => $wagState],
        ];
        $aliases = $this->groupFields($colBy, $defaultAliases);
        return array_values(array_filter(
            array_map(fn($a) => $registry[$a] ?? null, $aliases),
            fn($c) => $c !== null
        ));
    }

    /* Строит и выполняет запрос для сводной таблицы, затем преобразует результат в roadTable-формат */
    private function summaryReport(array $base, array $rowDims, array $colDefs): array
    {
        $rowSelect = implode(', ', $rowDims);
        $colSelect = implode(', ', array_map(fn($c) => "{$c['expr']} AS {$c['alias']}", $colDefs));
        $colGroupBy = implode(', ', array_map(fn($c) => $c['expr'], $colDefs));
        $colFields = array_column($colDefs, 'alias');
        $colOrder = implode(', ', $colFields);

        $rows = $this->db->fetchAll(
            "SELECT $rowSelect, $colSelect, COUNT(*) AS cnt
             FROM {$base['from']}
             GROUP BY $rowSelect, $colGroupBy
             ORDER BY $rowSelect, $colOrder",
            $base['bindings']
        );

        return $this->roadTable($rows, $rowDims, $colFields);
    }

    /** GET /api/dislocation/filters — список загруженных справок */
    public function dislFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            'SELECT TRUNC(report_dt) AS report_date, type_reference, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             GROUP BY TRUNC(report_dt), type_reference
             ORDER BY TRUNC(report_dt) DESC, type_reference'
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
    /*
        Страница дашборда
     */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dtsByType = $this->getLatestDtsByType(null, ['Подход', 'Отправка']); // Дата Справки

        if (empty($dtsByType)) {
            return $this->json($response, ['updated_at' => null, 'sections' => []]);
        }

        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $rows = $this->db->fetchAll(
            "SELECT park_type, 
                    1 AS total, 
                    wagon_type_code,
                    case when dest_station like '%УГЛ%' and oper_station!=dest_station then 1 else 0 end as comming_to_ugl
             FROM xx_dislocation_rjd xdr
             WHERE {$cond['sql']}
             /*GROUP BY park_type, wagon_type_code*/
             ",
            $cond['params']
        );
        $dt = max($dtsByType);

        $sections = [];
        foreach ($rows as $r) {
            $sectionName = trim(explode(',', (string) ($r['park_type'] ?? ''))[0]);
            if (!isset($sections[$sectionName])) {
                $sections[$sectionName] = ['id' => md5($sectionName), 'name' => $sectionName, 'comming_to_ugl' => 0, 'total' => 0, 'tank_total' => 0];
            }

            $cnt = (int) $r['total'];
            $commingToUgl = (int) $r['comming_to_ugl'];
            $sections[$sectionName]['total'] += $cnt;
            $sections[$sectionName]['comming_to_ugl'] += $commingToUgl;
            if (mb_stripos((string) ($r['wagon_type_code'] ?? ''), 'цистерн') !== false) {
                $sections[$sectionName]['tank_total'] += $cnt;
            }
        }

        try {
            $dtLabel = (new \DateTime($dt))->format('d.m.Y H:i');
        } catch (\Exception $e) {
            $dtLabel = $dt;
        }

        return $this->json($response, ['updated_at' => $dtLabel, 'sections' => array_values($sections)]);
    }

    /** GET /api/dislocation/summary — Сводная таблица */
    public function dislSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        $rowDims = $this->groupFields($params['group_by'] ?? '', ['dest_state', 'dest_road']);

        if (empty($dtsByType)) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        $rowSelect = implode(', ', $rowDims);
        $colSelect = implode(', ', array_map(fn($c) => "{$c['expr']} AS {$c['alias']}", $colDefs));
        $colGroupBy = implode(', ', array_map(fn($c) => $c['expr'], $colDefs));
        $colFields = array_column($colDefs, 'alias');
        $colOrder = implode(', ', $colFields);

        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $rows = $this->db->fetchAll(
            "SELECT $rowSelect, $colSelect, COUNT(*) AS cnt
             FROM xx_dislocation_rjd xdr
             WHERE {$cond['sql']}
             GROUP BY $rowSelect, $colGroupBy
             ORDER BY $rowSelect, $colOrder",
            $cond['params']
        );

        return $this->json($response, $this->roadTable($rows, $rowDims, $colFields));
    }

    /** GET /api/dislocation/detail — Расширенная дислокация */
    public function dislDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_state', 'dest_road']);
        $gfStr = implode(', ', $gf);

        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $bindings = $cond['params'];
        $where = $this->applyDetailFilters($gf, $params, $bindings);
        $select = $this->selectFields($params['fields'] ?? '');
        $rows = $this->db->fetchAll(
            "SELECT $select
             FROM xx_dislocation_rjd xdr
             WHERE {$cond['sql']} {$where}
             ORDER BY {$this->orderClause($params, "$gfStr, oper_station")}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach/filters — Уникальные значения для фильтров */
    public function approachFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->approachFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$base['reportDt']) {
            return $this->json($response, ['cargo' => [], 'prev_cargo' => []]);
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$base['from']}
             WHERE cargo_name IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY cargo_name",
            $base['bindings']
        );
        $prevCargo = $this->db->fetchAll(
            "SELECT DISTINCT prev_cargo FROM {$base['from']}
             WHERE prev_cargo IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY prev_cargo",
            $base['bindings']
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
            'prev_cargo' => array_column($prevCargo, 'prev_cargo'),
        ]);
    }

    /** GET /api/approach/summary — Сводная подход: Дорога→Станция, колонки=тип вагона */
    public function approachSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->approachFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->groupFields($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        return $this->json($response, $this->summaryReport($base, $rowDims, $colDefs));
    }

    /** GET /api/approach/detail — Список вагонов подхода */
    public function approachDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $base = $this->approachFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyDetailFilters($gf, $params, $bindings);

        $select = $this->selectFields($params['fields'] ?? '');

        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, $gfStr)}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/departure/filters — значения для фильтров вкладки «Отправление» */
    public function departureFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->departureFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$base['reportDt']) {
            return $this->json($response, ['cargo' => [], 'dest_station' => []]);
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$base['from']} WHERE cargo_name IS NOT NULL ORDER BY cargo_name",
            $base['bindings']
        );
        $destStation = $this->db->fetchAll(
            "SELECT DISTINCT dest_station FROM {$base['from']} WHERE dest_station IS NOT NULL ORDER BY dest_station " . $this->db->limit(300),
            $base['bindings']
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
        $base = $this->departureFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
        return $this->json($response, $this->summaryReport($base, $rowDims, $colDefs));
    }

    /** GET /api/departure/detail — Список отправленных вагонов */
    public function departureDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $base = $this->departureFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyDetailFilters($gf, $params, $bindings);

        $select = $this->selectFields($params['fields'] ?? '');

        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, $gfStr)}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/loading/summary — Погруженные вагоны по станциям отправления */
    public function loadingSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->loadingFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
        return $this->json($response, $this->summaryReport($base, $rowDims, $colDefs));
    }

    /** GET /api/loading/detail — Список погруженных вагонов */
    public function loadingDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $base = $this->loadingFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyDetailFilters($gf, $params, $bindings);

        $select = $this->selectFields($params['fields'] ?? '');

        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, $gfStr)}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/downtime/filters — уникальные станции назначения для дропдауна */
    public function downtimeFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $base = $this->downtimeFrom($request->getQueryParams());

        if (!$base['reportDt']) {
            return $this->json($response, ['dest_station' => []]);
        }

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT dest_station FROM {$base['from']}
             WHERE dest_station IS NOT NULL
             ORDER BY dest_station",
            $base['bindings']
        );

        return $this->json($response, [
            'dest_station' => array_column($rows, 'dest_station'),
        ]);
    }
    /** GET /api/downtime/summary — Сводная простоев */
    public function downtimeSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->downtimeFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->groupFields($params['group_by'] ?? '', ['oper_road', 'oper_station'], ['idle_time_name']);
        $colFields = $this->groupFields($params['col_by'] ?? '', ['fixed_col_label', 'm_wagon_type_code']);

        $rowSelect = implode(', ', $rowDims);
        $colSelect = implode(', ', $colFields);
        $colGroupBy = implode(', ', $colFields);

        $rows = $this->db->fetchAll(
            "SELECT $rowSelect, $colSelect, COUNT(*) AS cnt
             FROM {$base['from']}
             GROUP BY idle_time_order_by, $rowSelect, $colGroupBy
             ORDER BY idle_time_order_by ASC, $rowSelect",
            $base['bindings']
        );

        return $this->json($response, $this->roadTable($rows, $rowDims, $colFields));
    }

    /** GET /api/downtime/detail — Список простаивающих вагонов */
    public function downtimeDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->downtimeFrom($params);
        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gf = $this->groupFields($params['group_by'] ?? '', ['idle_time_name']);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyDetailFilters($gf, $params, $bindings);
        $wagExpr = self::WAG_TYPE_EXPR;
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $params['fields'] ?? '')),
            fn($f) => self::isSafeField($f)
        ));
        $selectParts = array_map(fn($f) => $f === 'wagon_type_code' ? "$wagExpr AS wagon_type_code" : $f, $fields);
        $select = $selectParts ? implode(', ', $selectParts) : 'wagon_no';
        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, 'idle_time_days DESC')}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/analysis/period/detail — Анализ операций за период */
    public function analysisPeriodDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params  = $request->getQueryParams();
        $bindings = [];
        $where   = '1=1';

        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo !== '') {
            $where .= ' AND wagon_no = :wagon_no';
            $bindings['wagon_no'] = $wagonNo;
        }

        $dateFrom = $params['date_from'] ?? '';
        if ($dateFrom !== '') {
            $where .= " AND TRUNC(report_dt) >= TO_DATE(:date_from, 'YYYY-MM-DD')";
            $bindings['date_from'] = $dateFrom;
        }

        $dateTo = $params['date_to'] ?? '';
        if ($dateTo !== '') {
            $where .= " AND TRUNC(report_dt) <= TO_DATE(:date_to, 'YYYY-MM-DD')";
            $bindings['date_to'] = $dateTo;
        }

        $select = $this->selectFields($params['fields'] ?? '');

        $rows = $this->db->fetchAll(
            "SELECT $select
             FROM xx_dislocation_rjd
             WHERE $where
             ORDER BY {$this->orderClause($params, 'wagon_no, report_dt')}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/raw-material/summary — Сводная сырья */
    public function rawSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->rawFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0, 'max_idle' => 0]);
        }

        $rowDims = $this->groupFields($params['group_by'] ?? '', ['cargo_name']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);

        $rowSelect = implode(', ', $rowDims);
        $colSelect = implode(', ', array_map(fn($c) => "{$c['expr']} AS {$c['alias']}", $colDefs));
        $colGroupBy = implode(', ', array_map(fn($c) => $c['expr'], $colDefs));
        $colFields = array_column($colDefs, 'alias');
        $colOrder = implode(', ', $colFields);

        $rows = $this->db->fetchAll(
            "SELECT $rowSelect, $colSelect, COUNT(*) AS cnt
             FROM {$base['from']}
             GROUP BY $rowSelect, $colGroupBy
             ORDER BY $rowSelect, $colOrder",
            $base['bindings']
        );

        $maxIdleRow = $this->db->fetchOne(
            "SELECT MAX(idle_time_days) AS max_idle FROM {$base['from']}",
            $base['bindings']
        );

        $result = $this->roadTable($rows, $rowDims, $colFields);
        $result['max_idle'] = (float) ($maxIdleRow['max_idle'] ?? 0);
        return $this->json($response, $result);
    }

    /** GET /api/raw-material/detail — Список вагонов с сырьём */
    public function rawDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $gf = $this->groupFields($params['group_by'] ?? '', ['cargo_name']);
        $base = $this->rawFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyDetailFilters($gf, $params, $bindings);

        $select = $this->selectFields($params['fields'] ?? '');
        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, "$gfStr, idle_time_days DESC")}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    //Анализ за период
    public function analysisPeriod(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rows = $this->db->fetchAll(
            "SELECT *
             FROM xx_dislocation_rjd
             WHERE report_dt >= TO_DATE(:start_dt, 'YYYY-MM-DD HH24:MI:SS') AND report_dt <= TO_DATE(:end_dt, 'YYYY-MM-DD HH24:MI:SS')
             GROUP BY report_dt, type_reference
             ORDER BY report_dt DESC, type_reference",
            [
                'start_dt' => $params['start_dt'] ?? '',
                'end_dt' => $params['end_dt'] ?? '',
            ]
        );

        return $this->json($response, ['rows' => $rows]);
    }


    // =========================================================================
    // Базовые подзапросы(FROM и т.д): каждый возвращает ['from' => $subquery, 'bindings' => [...], 'reportDt' => $dt|null]
    // =========================================================================

    /**
     * Базовый подзапрос для «Подход»: вагоны с ненулевым остатком пути.
     * Фильтрует по type_reference='Подход', dist_remain_km, опционально cargo и prev_cargo.
     */
    private function approachFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $cargo = $params['cargo'] ?? null;
        $prevCargo = $params['prev_cargo'] ?? null;

        $innerWhere = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND type_reference = 'Подход' AND dist_remain_km IS NOT NULL AND dist_remain_km != 0";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $innerWhere .= " AND UPPER(REPLACE(COALESCE(cargo_name,''), 'Ё', 'Е')) = UPPER(REPLACE(:cargo_f, 'Ё', 'Е'))";
            $bindings['cargo_f'] = $cargo;
        }
        if ($prevCargo) {
            $innerWhere .= " AND UPPER(REPLACE(COALESCE(prev_cargo,''), 'Ё', 'Е')) = UPPER(REPLACE(:prev_cargo_f, 'Ё', 'Е'))";
            $bindings['prev_cargo_f'] = $prevCargo;
        }

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $innerWhere)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /**
     * Базовый подзапрос для «Отправка»: отправленные вагоны (oper_mnemonic='ОТПР').
     * Опционально фильтрует по cargo и dest_station.
     */
    private function departureFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $cargo = $params['cargo'] ?? null;
        $destStation = $params['dest_station'] ?? null;

        $innerWhere = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND type_reference = 'Отправка' AND oper_mnemonic = 'ОТПР'";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $innerWhere .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }
        if ($destStation) {
            $innerWhere .= ' AND dest_station = :dest_station';
            $bindings['dest_station'] = $destStation;
        }

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $innerWhere)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /**
     * Базовый подзапрос для «Погрузка»: вагоны с грузом (cargo_weight_kg > 0).
     * Опционально фильтрует по cargo.
     */
    private function loadingFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $cargo = $params['cargo'] ?? null;

        $innerWhere = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $innerWhere .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $innerWhere)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /**
     * Базовый подзапрос для «Сырьё»: гружёные вагоны с ненулевым простоем.
     * Оба метода (summary и detail) работают с одной и той же базой.
     */
    private function rawFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null);
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $innerWhere = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS')"
            . " AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0"
            . " AND idle_time_days IS NOT NULL AND idle_time_days != 0";

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $innerWhere)", 'bindings' => ['report_dt' => $reportDt], 'reportDt' => $reportDt];
    }

    /**
     * Базовый подзапрос для «Простои»: вычисляет idle_time_name и фильтрует по актуальным датам
     * справок (через latestDtCondition), idle_time_days, min_days, max_days.
     */
    private function downtimeFrom(array $params): array
    {
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null);
        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $minDays = max(0, (int) ($params['min_days'] ?? 1));
        $maxDays = isset($params['max_days']) && $params['max_days'] !== '' ? (int) $params['max_days'] : null;

        $innerWhere = "{$cond['sql']} AND idle_time_days IS NOT NULL AND nvl(idle_time_days,0) != 0";
        $bindings = $cond['params'];
        if ($minDays > 0) {
            $innerWhere .= ' AND idle_time_days >= :min_days';
            $bindings['min_days'] = $minDays;
        }
        if ($maxDays !== null) {
            $innerWhere .= ' AND idle_time_days <= :max_days';
            $bindings['max_days'] = $maxDays;
        }
        $destStation = trim($params['dest_station'] ?? '');
        if ($destStation !== '') {
            $innerWhere .= ' AND dest_station = :dest_station';
            $bindings['dest_station'] = $destStation;
        }
        $wagExpr = self::WAG_TYPE_EXPR;
        $reportDt = !empty($dtsByType) ? max($dtsByType) : null;
        $from = "(SELECT xdr.*
                        ,XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.fnc_get_downtime_wagon(idle_time_days,'name') AS idle_time_name 
                        ,to_number(XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.fnc_get_downtime_wagon(idle_time_days,'order_by')) AS idle_time_order_by
                        ,$wagExpr as m_wagon_type_code
                        ,'Кол-во' AS fixed_col_label
                        FROM xx_dislocation_rjd xdr WHERE $innerWhere 
                        order by idle_time_order_by
                  )";

        return ['from' => $from, 'bindings' => $bindings, 'reportDt' => $reportDt];
    }


    // =========================================================================
    // Утилиты
    // =========================================================================

    /**
     * Строит outerWhere по groupFields: для каждого поля ищет одноимённый параметр
     * в $params и добавляет AND field = :gfv_field.
     */
    private function applyGfFilters(array $gf, array $params, array &$bindings): string
    {
        $where = '';
        foreach ($gf as $k) {
            if (isset($params[$k]) && $params[$k] !== '') {
                $safe = preg_replace('/[^a-z0-9_]/i', '', $k);
                $where .= " AND $k = :gfv_$safe";
                $bindings["gfv_$safe"] = $params[$k];
            }
        }
        return $where;
    }

    /**
     * Универсальный фильтр для детализации: строки (applyGfFilters) +
     * колонки (wagon_type через WAG_TYPE_EXPR; cargo_state через CARGO_WEIGHT_KG).
     * Все detail-хендлеры вызывают только этот метод — никаких исключений по вкладкам.
     */
    private function applyDetailFilters(array $gf, array $params, array &$bindings): string
    {
        $where = $this->applyGfFilters($gf, $params, $bindings);

        $wagType = $params['wagon_type'] ?? null;
        if ($wagType !== null && $wagType !== '') {
            $where .= ' AND ' . self::WAG_TYPE_EXPR . ' = :col_wtype';
            $bindings['col_wtype'] = $wagType;
        }

        $cargoState = $params['cargo_state'] ?? null;
        if ($cargoState === 'ГР') {
            $where .= ' AND CARGO_WEIGHT_KG > 0';
        } elseif ($cargoState === 'ПОР') {
            $where .= ' AND (CARGO_WEIGHT_KG IS NULL OR CARGO_WEIGHT_KG = 0)';
        }

        return $where;
    }

    /**
     * Возвращает переданный report_dt или MAX(report_dt) для указанного типа справки.
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
     * Если передан $dt — возвращает его для всех типов без запроса к БД.
     */
    private function getLatestDtsByType(?string $dt = null, ?array $types = null): array
    {
        if ($types !== null && count($types) === 0)
            return [];
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
     * Строит SQL-фрагмент WHERE 
     * $alias — префикс таблицы, например 'xdr' → 'xdr.type_reference'
     */
    private function latestDtCondition(array $dtsByType, string $alias = ''): array
    {
        if (empty($dtsByType))
            return ['sql' => '1=0', 'params' => []];
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

    /**
     * Строит иерархическую структуру groupKeys[0]→groupKeys[last]
     * 
     * @param array $rows
     * @param array $groupKeys
     * @param array $colFields
     * @return array{cols: array, metrics: array, roads: array, total: float|int|array{col_groups: array, metrics: array, roads: array, total: float|int}}
     */
    private function roadTable(array $rows, array $groupKeys, array $colFields): array
    {
        $roadKey = $groupKeys[0];
        $axisFields = $colFields;
        $nLevels = count($axisFields);

        $values = array_fill(0, $nLevels, []);
        $index = array_fill(0, $nLevels, []);

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
        $stationParts = array_slice($groupKeys, 1);
        $roads = [];

        foreach ($rows as $r) {
            $road = (string) ($r[$roadKey] ?? 'Не указана');
            $stComposite = implode('|', array_map(fn($k) => (string) ($r[$k] ?? ''), $stationParts)) ?: 'Не указана';
            $cnt = (int) $r['cnt'];

            if (!isset($roads[$road])) {
                $roads[$road] = [$roadKey => $road, 'stations' => [], 'total' => array_fill(0, $nFlat, 0), 'grand_total' => 0];
            }
            if (!isset($roads[$road]['stations'][$stComposite])) {
                $stData = ['v' => array_fill(0, $nFlat, 0)];
                foreach ($stationParts as $k) {
                    $stData[$k] = (string) ($r[$k] ?? '');
                }
                $roads[$road]['stations'][$stComposite] = $stData;
            }

            $t = (string) ($r[$axisFields[0]] ?? '');
            if ($t === '' || !isset($index[0][$t]))
                continue;

            $fi = 0;
            foreach ($axisFields as $k => $f) {
                $v = (string) ($r[$f] ?? '');
                $fi = $fi * $dims[$k] + ($index[$k][$v] ?? 0);
            }

            $roads[$road]['stations'][$stComposite]['v'][$fi] += $cnt;
            $roads[$road]['total'][$fi] += $cnt;
            $roads[$road]['grand_total'] += $cnt;
        }

        foreach ($roads as &$road) {
            $road['stations'] = array_values($road['stations']);
        }
        unset($road);

        $roadList = array_values($roads);
        $metrics = array_map(fn($r) => ['label' => $r[$roadKey], 'total' => $r['grand_total']], $roadList);
        $grandTotal = array_sum(array_column($metrics, 'total'));

        if (count($colFields) <= 1) {
            return ['cols' => $values[0], 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 20), 'total' => $grandTotal];
        }

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

    private function json(ResponseInterface $response, $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
