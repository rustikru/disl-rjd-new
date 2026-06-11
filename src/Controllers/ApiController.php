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

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /** GET /api/reports — список загруженных справок */
    public function reports(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            'SELECT TRUNC(report_dt) AS report_date, type_reference, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             GROUP BY TRUNC(report_dt), type_reference
             ORDER BY TRUNC(report_dt) DESC, type_reference
             ' . $this->db->limit(20)
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
        $latestDt = $this->db->fetchOne(
            'SELECT MAX(report_dt) AS dt FROM xx_dislocation_rjd'
        );
        $dt = $latestDt['dt'] ?? null;

        if (!$dt) {
            return $this->json($response, [
                'updated_at' => null,
                'sections' => [],
            ]);
        }

        // Группируем по park_type, в PHP берём первое слово как имя раздела
        $rows = $this->db->fetchAll(
            'SELECT park_type, COUNT(*) AS total, wagon_type_code
             FROM xx_dislocation_rjd
             WHERE report_dt = :dt
             GROUP BY park_type, wagon_type_code',
            ['dt' => $dt]
        );

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
    public function dislocationSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $params['report_dt'] ?? null;

        if (!$reportDt) {
            $latest = $this->db->fetchOne('SELECT MAX(report_dt) AS dt FROM xx_dislocation_rjd');
            $reportDt = $latest['dt'] ?? null;
        }

        if (!$reportDt) {
            return $this->json($response, [
                'date' => null,
                'report_dt_label' => 'Нет загруженных справок',
                'cols' => [],
                'sections' => [],
            ]);
        }

        $rows = $this->db->fetchAll(
            'SELECT park_type, wagon_type_code, COUNT(*) AS wagon_count
             FROM xx_dislocation_rjd
             WHERE report_dt = :dt
             GROUP BY park_type, wagon_type_code
             ORDER BY park_type, wagon_type_code',
            ['dt' => $reportDt]
        );

        try {
            $d = new \DateTime($reportDt);
            $label = $d->format('d.m.Y H:i');
        } catch (\Exception $e) {
            $label = $reportDt;
        }

        $data = $this->buildSummaryStructure($rows, $label);
        return $this->json($response, $data);
    }

    /** GET /api/dislocation/extended — Расширенная дислокация */
    public function dislocationExtended(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $params['report_dt'] ?? null;
        $dtParam = $reportDt
            ? ':dt'
            : '(SELECT MAX(report_dt) FROM xx_dislocation_rjd)';

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, train_no, oper_station, depart_station, dest_station,
                    cargo_name, park_type, oper_mnemonic, idle_time_days, asoup_arrive_dt,
                    owner, lessee
             FROM xx_dislocation_rjd
             WHERE report_dt = $dtParam
             ORDER BY oper_station
             " . $this->db->limit(500),
            $reportDt ? ['dt' => $reportDt] : []
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach/summary — Сводная подход: Дорога→Станция, колонки=тип вагона */
    public function approachSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null, 'Подход');
        $cargo = $params['cargo'] ?? null;
        $prevCargo = $params['prev_cargo'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        [$where, $bindings] = $this->buildApproachWhere($reportDt, $cargo, $prevCargo);

        $rows = $this->db->fetchAll(
            "SELECT oper_road, oper_station, wagon_type_code, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             WHERE {$where}
             GROUP BY oper_road, oper_station, wagon_type_code
             ORDER BY oper_road, oper_station, wagon_type_code",
            $bindings
        );

        return $this->json($response, $this->buildRoadStationTable($rows, 'oper_road', 'oper_station'));
    }

    /** GET /api/approach/detail — Список вагонов подхода */
    public function approachDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null, 'Подход');
        $cargo = $params['cargo'] ?? null;
        $prevCargo = $params['prev_cargo'] ?? null;
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        [$where, $bindings] = $this->buildApproachWhere($reportDt, $cargo, $prevCargo);

        if ($road) {
            $where .= ' AND oper_road = :oper_road';
            $bindings['oper_road'] = $road;
        }
        if ($station) {
            $where .= ' AND oper_station = :oper_station';
            $bindings['oper_station'] = $station;
        }
        if ($wagType) {
            $where .= ' AND wagon_type_code = :wagon_type_code';
            $bindings['wagon_type_code'] = $wagType;
        }

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, wagon_type_code, cargo_name, prev_cargo,
                    dist_remain_km, depart_station, depart_road,
                    dest_station, dest_road, oper_station,
                    train_index, oper_dt, norm_delivery_dt, oper_mnemonic
             FROM xx_dislocation_rjd
             WHERE {$where}
             ORDER BY dest_road, dest_station, dist_remain_km
             ",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach/filters — Уникальные значения для фильтров */
    public function approachFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null, 'Подход');

        if (!$reportDt) {
            return $this->json($response, ['cargo' => [], 'prev_cargo' => []]);
        }

        $bindings = ['report_dt' => $reportDt];

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt AND type_reference = 'Подход'
               AND cargo_name IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY cargo_name " . $this->db->limit(150),
            $bindings
        );
        $prevCargo = $this->db->fetchAll(
            "SELECT DISTINCT prev_cargo FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt AND type_reference = 'Подход'
               AND prev_cargo IS NOT NULL AND nvl(prev_cargo,'*') != '*'
             ORDER BY prev_cargo " . $this->db->limit(150),
            $bindings
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
            'prev_cargo' => array_column($prevCargo, 'prev_cargo'),
        ]);
    }

    /** WHERE-условие для запросов «Подход» (wagons in transit: dist_remain_km > 0) */
    private function buildApproachWhere(string $reportDt, ?string $cargo, ?string $prevCargo): array
    {
        $where = "report_dt = :report_dt AND type_reference = 'Подход' ";
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

    // ── Отправление вагонов ─────────────────────────────────────

    /** GET /api/departure/summary — Сводная: Дорога→Станция отправления, кол-во по типам */
    public function departureSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null, 'Отправка');
        $cargo = $params['cargo'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $where = "report_dt = :report_dt AND type_reference = 'Отправка' AND oper_mnemonic = 'ОТПР'";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }

        $rows = $this->db->fetchAll(
            "SELECT depart_road, depart_station, wagon_type_code, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             WHERE {$where}
             GROUP BY depart_road, depart_station, wagon_type_code
             ORDER BY depart_road, depart_station, wagon_type_code",
            $bindings
        );

        return $this->json($response, $this->buildRoadStationTable($rows, 'depart_road', 'depart_station'));
    }

    /** GET /api/departure/detail — Список отправленных вагонов */
    public function departureDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null, 'Отправка');
        $cargo = $params['cargo'] ?? null;
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $where = "report_dt = :report_dt AND type_reference = 'Отправка' AND oper_mnemonic = 'ОТПР'";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= ' AND UPPER(COALESCE(cargo_name,\'\')) = UPPER(:cargo_f)';
            $bindings['cargo_f'] = $cargo;
        }
        if ($road) {
            $where .= ' AND depart_road = :depart_road';
            $bindings['depart_road'] = $road;
        }
        if ($station) {
            $where .= ' AND depart_station = :depart_station';
            $bindings['depart_station'] = $station;
        }
        if ($wagType) {
            $where .= ' AND wagon_type_code = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, wagon_type_code, cargo_name, cargo_weight_kg,
                    depart_station, depart_road, dest_station, dest_road,
                    oper_station, oper_dt, dist_remain_km, norm_delivery_dt, waybill_no
             FROM xx_dislocation_rjd
             WHERE {$where}
             ORDER BY depart_road, depart_station
             ",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    // ── Погрузка ─────────────────────────────────────────────────

    /** GET /api/loading/summary — Погруженные вагоны по станциям отправления */
    public function loadingSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null);
        $cargo = $params['cargo'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $where = "report_dt = :report_dt AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= " AND UPPER(COALESCE(cargo_name,'')) = UPPER(:cargo_f)";
            $bindings['cargo_f'] = $cargo;
        }

        $rows = $this->db->fetchAll(
            "SELECT depart_road, depart_station, wagon_type_code, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             WHERE {$where}
             GROUP BY depart_road, depart_station, wagon_type_code
             ORDER BY depart_road, depart_station, wagon_type_code",
            $bindings
        );

        return $this->json($response, $this->buildRoadStationTable($rows, 'depart_road', 'depart_station'));
    }

    /** GET /api/loading/detail — Список погруженных вагонов */
    public function loadingDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null);
        $cargo = $params['cargo'] ?? null;
        $road = $params['road'] ?? null;
        $station = $params['station'] ?? null;
        $wagType = $params['wagon_type'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $where = "report_dt = :report_dt AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= ' AND UPPER(COALESCE(cargo_name,\'\')) = UPPER(:cargo_f)';
            $bindings['cargo_f'] = $cargo;
        }
        if ($road) {
            $where .= ' AND depart_road = :depart_road';
            $bindings['depart_road'] = $road;
        }
        if ($station) {
            $where .= ' AND depart_station = :depart_station';
            $bindings['depart_station'] = $station;
        }
        if ($wagType) {
            $where .= ' AND wagon_type_code = :wtype';
            $bindings['wtype'] = $wagType;
        }

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, wagon_type_code, cargo_name, cargo_weight_kg,
                    depart_station, depart_road, dest_station, dest_road,
                    oper_station, oper_mnemonic, oper_dt, waybill_no
             FROM xx_dislocation_rjd
             WHERE {$where}
             ORDER BY depart_road, depart_station
             ",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    // ── Простои ──────────────────────────────────────────────────

    /** GET /api/downtime/summary — Сводная простоев по станциям */
    public function downtimeSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null);
        $minDays = max(0, (int) ($params['min_days'] ?? 1));

        if (!$reportDt) {
            return $this->json($response, ['rows' => [], 'total' => 0]);
        }

        $rows = $this->db->fetchAll(
            "SELECT oper_road, oper_station, wagon_type_code,
                    COUNT(*) AS cnt,
                    MAX(idle_time_days) AS max_idle
             FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt
               AND idle_time_days IS NOT NULL
               AND nvl(idle_time_days,0) != 0
             GROUP BY oper_road, oper_station, wagon_type_code
             ORDER BY cnt DESC
             " . $this->db->limit(200),
            ['report_dt' => $reportDt]
        );

        // Фильтруем по минимальному кол-ву суток в PHP (избегаем CAST для кроссплатформенности)
        if ($minDays > 1) {
            $rows = array_filter($rows, fn($r) => (float) ($r['max_idle'] ?? 0) >= $minDays);
        }

        $total = array_sum(array_column($rows, 'cnt'));

        return $this->json($response, ['rows' => array_values($rows), 'total' => (int) $total]);
    }

    /** GET /api/downtime/detail — Список простаивающих вагонов */
    public function downtimeDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null);
        $station = $params['station'] ?? null;
        $road = $params['road'] ?? null;
        $wagType = $params['wagon_type'] ?? null;
        $minDays = max(0, (int) ($params['min_days'] ?? 1));

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $where = "report_dt = :report_dt AND idle_time_days IS NOT NULL AND idle_time_days != 0 ";
        $bindings = ['report_dt' => $reportDt];
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
             ",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    // ── Сырьё ────────────────────────────────────────────────────

    /** GET /api/raw-material/summary — Сводная сырья (простой гружёных вагонов) */
    public function rawMaterialSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null);

        if (!$reportDt) {
            return $this->json($response, ['rows' => [], 'total' => 0, 'max_idle' => 0]);
        }

        $rows = $this->db->fetchAll(
            "SELECT cargo_name, wagon_type_code,
                    COUNT(*) AS cnt,
                    MAX(idle_time_days) AS max_idle
             FROM xx_dislocation_rjd
             WHERE report_dt = :report_dt
               AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0
               AND idle_time_days IS NOT NULL AND idle_time_days != 0
             GROUP BY cargo_name, wagon_type_code
             ORDER BY cnt DESC
             " . $this->db->limit(100),
            ['report_dt' => $reportDt]
        );

        $total = array_sum(array_column($rows, 'cnt'));
        $maxIdle = $rows ? max(array_column($rows, 'max_idle')) : 0;

        return $this->json($response, ['rows' => $rows, 'total' => (int) $total, 'max_idle' => $maxIdle]);
    }

    /** GET /api/raw-material/detail — Список вагонов с сырьём */
    public function rawMaterialDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $reportDt = $this->resolveReportDt($params['report_dt'] ?? null);
        $cargo = $params['cargo'] ?? null;

        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $where = "report_dt = :report_dt AND cargo_weight_kg IS NOT NULL AND cargo_weight_kg != 0";
        $where = $where . ' AND idle_time_days IS NOT NULL AND idle_time_days != 0';
        $bindings = ['report_dt' => $reportDt];
        if ($cargo) {
            $where .= ' AND UPPER(COALESCE(cargo_name,\'\')) = UPPER(:cargo_f)';
            $bindings['cargo_f'] = $cargo;
        }

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, wagon_type_code, cargo_name, cargo_weight_kg,
                    idle_time_days, idle_time_hhmmss,
                    oper_station, oper_road, depart_station, depart_road,
                    dest_station, owner, waybill_no
             FROM xx_dislocation_rjd
             WHERE {$where}
             ORDER BY idle_time_days DESC
             ",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    // ── Вспомогательные методы ────────────────────────────────────

    /**
     * Возвращает переданный report_dt или последний MAX(report_dt) для указанного типа справки.
     * $typeRef = 'Подход' | 'Отправка' | null (любой тип)
     */
    private function resolveReportDt(?string $dt, ?string $typeRef = null): ?string
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
     * Строит структуру groupKey1→groupKey2 с колонками по типу вагона.
     * Ключи в ответе совпадают с именами полей в БД ($roadKey, $stationKey),
     * что позволяет фронтенду ссылаться на них через groupCols[i].key.
     */
    private function buildRoadStationTable(array $rows, string $roadKey, string $stationKey): array
    {
        $cols = [];
        $colIndex = [];
        foreach ($rows as $r) {
            $t = (string) ($r['wagon_type_code'] ?? '');
            if ($t !== '' && !isset($colIndex[$t])) {
                $colIndex[$t] = count($cols);
                $cols[] = $t;
            }
        }
        $nCols = count($cols);

        $roads = [];
        foreach ($rows as $r) {
            $road = (string) ($r[$roadKey] ?? 'Не указана');
            $station = (string) ($r[$stationKey] ?? 'Не указана');
            $wt = (string) ($r['wagon_type_code'] ?? '');
            $cnt = (int) $r['cnt'];

            if (!isset($roads[$road])) {
                $roads[$road] = [$roadKey => $road, 'stations' => [], 'total' => array_fill(0, $nCols, 0), 'grand_total' => 0];
            }
            if (!isset($roads[$road]['stations'][$station])) {
                $roads[$road]['stations'][$station] = [$stationKey => $station, 'v' => array_fill(0, $nCols, 0)];
            }
            if ($wt !== '' && isset($colIndex[$wt])) {
                $ci = $colIndex[$wt];
                $roads[$road]['stations'][$station]['v'][$ci] += $cnt;
                $roads[$road]['total'][$ci] += $cnt;
                $roads[$road]['grand_total'] += $cnt;
            }
        }
        foreach ($roads as &$road) {
            $road['stations'] = array_values($road['stations']);
        }
        unset($road);

        $roadList = array_values($roads);
        usort($roadList, fn($a, $b) => $b['grand_total'] - $a['grand_total']);

        // label — нейтральное поле для KPI-карточек (не зависит от имени ключа)
        $metrics = array_map(fn($r) => ['label' => $r[$roadKey], 'total' => $r['grand_total']], $roadList);
        $grandTotal = array_sum(array_column($metrics, 'total'));

        return ['cols' => $cols, 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 8), 'total' => $grandTotal];
    }

    // ── Строитель сводной таблицы ────────────────────────────────

    private function buildSummaryStructure(array $rows, string $dateLabel): array
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
