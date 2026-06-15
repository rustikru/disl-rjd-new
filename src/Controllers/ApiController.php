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
    private const WAG_TYPE_EXPR = "XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_MAPPING_WAG_TYPE(wagon_type_code)"; // краткое название типа вагона для сводных таблиц

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
     * Строит ORDER BY из параметров sort/sort_dir/sort_type с валидацией.
     * Поддерживает несколько полей через запятую: sort=f1,f2&sort_dir=asc,desc&sort_type=number,
     * Если sort не передан или все поля невалидны — возвращает $default.
     */
    private function orderClause(array $params, string $default): string
    {
        $sortRaw = trim($params['sort'] ?? '');
        if ($sortRaw === '') {
            return $default;
        }
        $fields = array_map('trim', explode(',', $sortRaw));
        $dirs   = array_map('trim', explode(',', $params['sort_dir']  ?? ''));
        $types  = array_map('trim', explode(',', $params['sort_type'] ?? ''));

        $parts = [];
        foreach ($fields as $i => $field) {
            if ($field === '' || !self::isSafeField($field)) {
                continue;
            }
            $dir  = strtoupper($dirs[$i] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $expr = strtolower($types[$i] ?? '') === 'number'
                ? "TO_NUMBER($field)"
                : $field;
            $parts[] = "$expr $dir";
        }

        return $parts ? implode(', ', $parts) : $default;
    }

    /**
     * @param array $cols  [['alias' => 'wagon_type_code', 'expr' => "..."], ...]
     */
    /* Строит и выполняет запрос для сводной таблицы, затем преобразует результат в формат:
     * ['cols' => ['wagon_type_code', ...], 
     *  'roads' => ['road1', 'road2', ...], 
     *  'metrics' => [['road' => 'road1', 
     *                 'wagon_type_code' => 'type1', 
     *                 'cnt' => 123], ...], 
     *                'total' => 123]
     */
    private function summaryReport(array $base, array $gf, array $cols): array
    {
        $gfStr = implode(', ', $gf);
        $select = [$gfStr];
        $groupBy = [$gfStr];
        $colFields = [];
        $orderTail = '';

        foreach ($cols as $col) {
            $select[] = "{$col['expr']} AS {$col['alias']}";
            $groupBy[] = $col['expr'];
            $colFields[] = $col['alias'];
            $orderTail .= ", {$col['alias']}";
        }

        $rows = $this->db->fetchAll(
            "SELECT " . implode(', ', $select) . ", COUNT(*) AS cnt
             FROM {$base['from']}
             GROUP BY " . implode(', ', $groupBy) . "
             ORDER BY $gfStr$orderTail",
            $base['bindings']
        );

        return $this->roadTable($rows, $gf, $colFields);
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
                $sections[$sectionName] = ['id' => md5($sectionName), 'name' => $sectionName, 'total' => 0, 'tank_total' => 0];
            }
            $cnt = (int) $r['total'];
            $sections[$sectionName]['total'] += $cnt;
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
        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_state', 'dest_road']);
        $gfStr = implode(', ', $gf);

        if (empty($dtsByType)) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $wagExpr = self::WAG_TYPE_EXPR;
        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $rows = $this->db->fetchAll(
            "SELECT $gfStr,
                    $wagExpr AS wagon_type_code,
                    CASE WHEN CARGO_WEIGHT_KG > 0 THEN 'ГР' ELSE 'ПОР' END AS CARGO_W_TYPE,
                    COUNT(*) AS cnt
             FROM xx_dislocation_rjd xdr
             WHERE {$cond['sql']}
             GROUP BY $gfStr, $wagExpr
                 , CASE WHEN CARGO_WEIGHT_KG > 0 THEN 'ГР' ELSE 'ПОР' END
             ORDER BY $gfStr, wagon_type_code, CARGO_W_TYPE",
            $cond['params']
        );

        return $this->json($response, $this->roadTable($rows, $gf, ['wagon_type_code', 'cargo_w_type']));
    }

    /** GET /api/dislocation/detail — Расширенная дислокация */
    public function dislDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_state', 'dest_road']);
        $gfStr = implode(', ', $gf);

        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        $cond = $this->latestDtCondition($dtsByType, 'xdr');
        $bindings = $cond['params'];
        $where = $this->applyGfFilters($gf, $road, $station, $params, $bindings);


        $cargoState = $params['cargo_state'] ?? null;
        if ($cargoState === 'ГР') {
            $where .= ' AND CARGO_WEIGHT_KG > 0';
        } elseif ($cargoState === 'ПОР') {
            $where .= ' AND (CARGO_WEIGHT_KG IS NULL OR CARGO_WEIGHT_KG = 0)';
        }

        if ($wagType) {
            $where .= ' AND ' . self::WAG_TYPE_EXPR . ' = :wtype';
            $bindings['wtype'] = $wagType;
        }

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

        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        return $this->json($response, $this->summaryReport($base, $gf, [
            ['alias' => 'wagon_type_code', 'expr' => self::WAG_TYPE_EXPR],
            ['alias' => 'cargo_w_type', 'expr' => "CASE WHEN CARGO_WEIGHT_KG > 0 THEN 'ГР' ELSE 'ПОР' END"],
        ]));
    }

    /** GET /api/approach/detail — Список вагонов подхода */
    public function approachDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $gf = $this->groupFields($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $base = $this->approachFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyGfFilters($gf, $road, $station, $params, $bindings);
        if ($wagType) {
            $outerWhere .= ' AND ' . self::WAG_TYPE_EXPR . ' = :wtype';
            $bindings['wtype'] = $wagType;
        }
        $cargoState = $params['cargo_state'] ?? null;
        if ($cargoState === 'ГР') {
            $outerWhere .= ' AND CARGO_WEIGHT_KG > 0';
        } elseif ($cargoState === 'ПОР') {
            $outerWhere .= ' AND (CARGO_WEIGHT_KG IS NULL OR CARGO_WEIGHT_KG = 0)';
        }

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

        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        return $this->json($response, $this->summaryReport($base, $gf, [
            ['alias' => 'wagon_type_code', 'expr' => self::WAG_TYPE_EXPR],
        ]));
    }

    /** GET /api/departure/detail — Список отправленных вагонов */
    public function departureDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $base = $this->departureFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyGfFilters($gf, $road, $station, $params, $bindings);
        if ($wagType) {
            $outerWhere .= ' AND ' . self::WAG_TYPE_EXPR . ' = :wtype';
            $bindings['wtype'] = $wagType;
        }

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

        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        return $this->json($response, $this->summaryReport($base, $gf, [
            ['alias' => 'wagon_type_code', 'expr' => self::WAG_TYPE_EXPR],
        ]));
    }

    /** GET /api/loading/detail — Список погруженных вагонов */
    public function loadingDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $gf = $this->groupFields($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $base = $this->loadingFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyGfFilters($gf, $road, $station, $params, $bindings);
        if ($wagType) {
            $outerWhere .= ' AND ' . self::WAG_TYPE_EXPR . ' = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $select = $this->selectFields($params['fields'] ?? '');

        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, $gfStr)}",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/downtime/summary — Сводная простоев */
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

    public function downtimeSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $base = $this->downtimeFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $gf = $this->groupFields($params['group_by'] ?? '', ['oper_road', 'oper_station'], ['idle_time_name']);
        $gfStr = implode(', ', $gf);
        $colLabel = mb_substr(preg_replace('/[^\pL\pN\s.,\-]/u', '', $params['col_label'] ?? 'Кол-во'), 0, 40);

        $rows = $this->db->fetchAll(
            "SELECT $gfStr, '$colLabel' AS wagon_type_code, COUNT(*) AS cnt
             FROM {$base['from']}
             GROUP BY idle_time_order_by, $gfStr
             ORDER BY idle_time_order_by asc, $gfStr",
            $base['bindings']
        );

        return $this->json($response, $this->roadTable($rows, $gf, ['wagon_type_code']));
    }

    /** GET /api/downtime/detail — Список простаивающих вагонов */
    public function downtimeDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $gf = $this->groupFields($params['group_by'] ?? '', ['oper_road', 'oper_station'], ['idle_time_name']);
        $base = $this->downtimeFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $base['bindings'];
        $outerWhere = $this->applyGfFilters($gf, $road, $station, $params, $bindings);
        // wagon_type не фильтруем: wagon_type_code в сводной — синтетическая метка colLabel,
        // а не реальное поле. Группировка идёт через gf (oper_road / oper_station / idle_time_name).

        $select = $this->selectFields($params['fields'] ?? '');
        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, 'idle_time_days DESC')}",
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

        $gf = $this->groupFields($params['group_by'] ?? '', ['cargo_name']);
        $gfStr = implode(', ', $gf);

        $wagExpr = self::WAG_TYPE_EXPR;
        $rows = $this->db->fetchAll(
            "SELECT $gfStr,
                    $wagExpr AS wagon_type_code,
                    COUNT(*) AS cnt
             FROM {$base['from']}
             GROUP BY $gfStr, $wagExpr
             ORDER BY $gfStr",
            $base['bindings']
        );

        $maxIdleRow = $this->db->fetchOne(
            "SELECT MAX(idle_time_days) AS max_idle FROM {$base['from']}",
            $base['bindings']
        );

        $result = $this->roadTable($rows, $gf, ['wagon_type_code']);
        $result['max_idle'] = (float) ($maxIdleRow['max_idle'] ?? 0);
        return $this->json($response, $result);
    }

    /** GET /api/raw-material/detail — Список вагонов с сырьём */
    public function rawDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $gf = $this->groupFields($params['group_by'] ?? '', ['cargo_name']);
        $base = $this->rawFrom($params);

        if (!$base['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $gfStr = implode(', ', $gf);
        $bindings = $base['bindings'];
        $outerWhere = $this->applyGfFilters($gf, $road, $station, $params, $bindings);
        if ($wagType) {
            $outerWhere .= ' AND ' . self::WAG_TYPE_EXPR . ' = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $select = $this->selectFields($params['fields'] ?? '');
        $rows = $this->db->fetchAll(
            "SELECT $select FROM {$base['from']} WHERE 1=1 $outerWhere ORDER BY {$this->orderClause($params, "$gfStr, idle_time_days DESC")}",
            $bindings
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

        $innerWhere = "report_dt = TO_TIMESTAMP(:report_dt, 'YYYY-MM-DD HH24:MI:SS.FF') AND type_reference = 'Подход' AND dist_remain_km IS NOT NULL AND dist_remain_km != 0";
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

        $innerWhere = "report_dt = TO_TIMESTAMP(:report_dt, 'YYYY-MM-DD HH24:MI:SS.FF') AND type_reference = 'Отправка' AND oper_mnemonic = 'ОТПР'";
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

        $innerWhere = "report_dt = TO_TIMESTAMP(:report_dt, 'YYYY-MM-DD HH24:MI:SS.FF') AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
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

        $innerWhere = "report_dt = TO_TIMESTAMP(:report_dt, 'YYYY-MM-DD HH24:MI:SS.FF')"
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

        $reportDt = !empty($dtsByType) ? max($dtsByType) : null;
        $from = "(SELECT xdr.*, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.fnc_get_downtime_wagon(idle_time_days,'name') AS idle_time_name 
                        ,to_number(XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.fnc_get_downtime_wagon(idle_time_days,'order_by')) AS idle_time_order_by
                        FROM xx_dislocation_rjd xdr WHERE $innerWhere 
                        order by idle_time_order_by
                  )";

        return ['from' => $from, 'bindings' => $bindings, 'reportDt' => $reportDt];
    }


    // =========================================================================
    // Утилиты
    // =========================================================================

    /**
     * Строит outerWhere из groupFields: road→gf[0], station→gf[last] (только если last>0),
     * плюс промежуточные уровни через прямое имя поля в $params.
     * Безопасно при одном поле в $gf: station не перетирает road.
     */
    private function applyGfFilters(array $gf, ?string $road, ?string $station, array $params, array &$bindings): string
    {
        $where = '';
        if ($road !== null && $road !== '' && isset($gf[0])) {
            $where .= " AND {$gf[0]} = :gf_0";
            $bindings['gf_0'] = $road;
        }
        $last = count($gf) - 1;
        if ($last > 0 && $station !== null && $station !== '' && isset($gf[$last])) {
            $where .= " AND {$gf[$last]} = :gf_$last";
            $bindings["gf_$last"] = $station;
        }
        foreach ($gf as $idx => $k) {
            if (!array_key_exists("gf_$idx", $bindings) && isset($params[$k]) && $params[$k] !== '') {
                $where .= " AND $k = :dfld_$idx";
                $bindings["dfld_$idx"] = $params[$k];
            }
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
            $parts[] = "({$col('type_reference')} = :ldt_type_{$i} AND {$col('report_dt')} = TO_TIMESTAMP(:ldt_dt_{$i}, 'YYYY-MM-DD HH24:MI:SS.FF'))";
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
