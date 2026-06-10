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
            'SELECT report_dt, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             GROUP BY report_dt
             ORDER BY report_dt DESC
             LIMIT 20'
        );

        $reports = array_map(function (array $r) {
            $dt    = (string) ($r['report_dt'] ?? '');
            $label = $dt;
            try {
                $d     = new \DateTime($dt);
                $label = $d->format('d.m.Y H:i');
            } catch (\Exception $e) {}
            return [
                'report_dt' => $dt,
                'label'     => $label,
                'cnt'       => (int) $r['cnt'],
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
                'sections'   => [],
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
                    'id'         => md5($sectionName),
                    'name'       => $sectionName,
                    'total'      => 0,
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
            $d       = new \DateTime($dt);
            $dtLabel = $d->format('d.m.Y H:i');
        } catch (\Exception $e) {
            $dtLabel = $dt;
        }

        return $this->json($response, [
            'updated_at' => $dtLabel,
            'sections'   => array_values($sections),
        ]);
    }

    /** GET /api/dislocation/summary?report_dt=... — Сводная таблица */
    public function dislocationSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params   = $request->getQueryParams();
        $reportDt = $params['report_dt'] ?? null;

        if (!$reportDt) {
            $latest   = $this->db->fetchOne('SELECT MAX(report_dt) AS dt FROM xx_dislocation_rjd');
            $reportDt = $latest['dt'] ?? null;
        }

        if (!$reportDt) {
            return $this->json($response, [
                'date'            => null,
                'report_dt_label' => 'Нет загруженных справок',
                'cols'            => [],
                'sections'        => [],
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
            $d     = new \DateTime($reportDt);
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
        $params   = $request->getQueryParams();
        $reportDt = $params['report_dt'] ?? null;
        $dtParam  = $reportDt
            ? ':dt'
            : '(SELECT MAX(report_dt) FROM xx_dislocation_rjd)';

        $rows = $this->db->fetchAll(
            "SELECT wagon_no, train_no, oper_station, depart_station, dest_station,
                    cargo_name, park_type, oper_mnemonic, idle_time_days, asoup_arrive_dt,
                    owner, lessee
             FROM xx_dislocation_rjd
             WHERE report_dt = $dtParam
             ORDER BY oper_station
             LIMIT 500",
            $reportDt ? ['dt' => $reportDt] : []
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach — Подход вагонов (отправленные / прибывающие) */
    public function approach(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            "SELECT depart_road, oper_mnemonic, wagon_type_code, dest_station,
                    oper_dt, asoup_arrive_dt
             FROM xx_dislocation_rjd
             WHERE report_dt = (SELECT MAX(report_dt) FROM xx_dislocation_rjd)
               AND oper_mnemonic IN ('ОТПР', 'ПРБТ')
             ORDER BY oper_dt DESC
             LIMIT 200"
        );

        return $this->json($response, ['rows' => $rows]);
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
        $cols     = array_map(fn($t) => ['label' => $t, 'group' => ''], array_keys($colOrder));
        $colIndex = array_flip(array_column($cols, 'label'));

        // Группируем по первому слову park_type → раздел, полная строка → подраздел
        $sections = [];
        foreach ($rows as $r) {
            $parkType    = (string) ($r['park_type'] ?? '');
            $sectionName = trim(explode(',', $parkType)[0]);
            $cnt         = (int) ($r['wagon_count'] ?? 0);

            if (!isset($sections[$sectionName])) {
                $sections[$sectionName] = [
                    'id'          => md5($sectionName),
                    'name'        => $sectionName,
                    'rows'        => [],
                    'total'       => array_fill(0, count($cols), 0),
                    'grand_total' => 0,
                ];
            }

            if (!isset($sections[$sectionName]['rows'][$parkType])) {
                $sections[$sectionName]['rows'][$parkType] = [
                    'sub'  => $parkType,
                    'park' => '',
                    'v'    => array_fill(0, count($cols), 0),
                ];
            }

            $ci = $colIndex[$r['wagon_type_code'] ?? ''] ?? null;
            if ($ci !== null) {
                $sections[$sectionName]['rows'][$parkType]['v'][$ci] += $cnt;
                $sections[$sectionName]['total'][$ci]               += $cnt;
                $sections[$sectionName]['grand_total']              += $cnt;
            }
        }

        foreach ($sections as &$sec) {
            $sec['rows'] = array_values($sec['rows']);
        }

        return [
            'date'            => $dateLabel,
            'report_dt_label' => $dateLabel,
            'cols'            => $cols,
            'sections'        => array_values($sections),
        ];
    }

    private function json(ResponseInterface $response, mixed $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
