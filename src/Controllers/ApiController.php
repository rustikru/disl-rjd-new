<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * JSON API для фронтенда.
 * Каждый метод — отдельный эндпоинт, возвращает JSON.
 * Чтобы добавить новый раздел — добавьте метод и маршрут в routes.php.
 */
class ApiController
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /** GET /api/dashboard — KPI-сводка для Dashboard */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Суммируем вагоны по разделам за последнюю имеющуюся дату
        $rows = $this->db->fetchAll(
            "SELECT section_id, section_name,
                    SUM(wagon_count) AS total,
                    SUM(CASE WHEN wagon_group = 'Цистерны' THEN wagon_count ELSE 0 END) AS tank_total
             FROM wagon_dislocation
             WHERE report_date = (SELECT MAX(report_date) FROM wagon_dislocation)
             GROUP BY section_id, section_name
             ORDER BY section_id"
        );

        $updatedAt = $this->db->fetchOne(
            'SELECT MAX(report_date) AS dt FROM wagon_dislocation'
        );

        $data = [
            'updated_at' => $updatedAt['dt'] ?? date('d.m.Y'),
            'sections'   => array_map(function (array $r) {
                return [
                    'id'         => $r['section_id'],
                    'name'       => $r['section_name'],
                    'total'      => (int) $r['total'],
                    'tank_total' => (int) $r['tank_total'],
                ];
            }, $rows),
        ];

        return $this->json($response, $data);
    }

    /** GET /api/dislocation/summary?date=YYYY-MM-DD — Сводная таблица дислокации */
    public function dislocationSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $date   = $params['date'] ?? date('Y-m-d');

        // Все строки за дату
        $rows = $this->db->fetchAll(
            'SELECT section_id, section_name, subsection, park,
                    wagon_type, wagon_group, wagon_count
             FROM wagon_dislocation
             WHERE report_date = :date
             ORDER BY section_id, subsection, wagon_type',
            ['date' => $date]
        );

        $data = $this->buildSummaryStructure($rows, $date);
        return $this->json($response, $data);
    }

    /** GET /api/dislocation/extended — Расширенная дислокация по вагонам */
    public function dislocationExtended(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            'SELECT wagon_no, train, current_station, from_station, to_station,
                    cargo, wagon_count, status, status_label, days_en_route,
                    expected_arrival, park
             FROM wagon_extended
             ORDER BY current_station'
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/approach — Подход вагонов */
    public function approach(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = $this->db->fetchAll(
            'SELECT road, direction, wagon_count, wagon_type, destination_station, expected_time
             FROM wagon_approach
             ORDER BY expected_time'
        );

        return $this->json($response, ['rows' => $rows]);
    }

    // ── Формирование структуры для таблицы дислокации ────────────

    private function buildSummaryStructure(array $rows, string $date): array
    {
        // Собираем упорядоченный список типов вагонов (колонок)
        $colOrder = [];
        foreach ($rows as $r) {
            $key = $r['wagon_type'] . '|' . $r['wagon_group'];
            if (!isset($colOrder[$key])) {
                $colOrder[$key] = ['label' => $r['wagon_type'], 'group' => $r['wagon_group']];
            }
        }
        $cols = array_values($colOrder);
        $colIndex = array_flip(array_column($cols, 'label')); // label => position

        // Группируем строки по разделам
        $sections = [];
        foreach ($rows as $r) {
            $sid = $r['section_id'];
            if (!isset($sections[$sid])) {
                $sections[$sid] = [
                    'id'          => $sid,
                    'name'        => $r['section_name'],
                    'rows'        => [],
                    'total'       => array_fill(0, count($cols), 0),
                    'grand_total' => 0,
                ];
            }

            // Ищем строку с тем же sub+park, добавляем значение в нужную колонку
            $rowKey = ($r['subsection'] ?? '') . '|' . ($r['park'] ?? '');
            if (!isset($sections[$sid]['rows'][$rowKey])) {
                $sections[$sid]['rows'][$rowKey] = [
                    'sub'  => $r['subsection'],
                    'park' => $r['park'],
                    'v'    => array_fill(0, count($cols), 0),
                ];
            }

            $ci = $colIndex[$r['wagon_type']] ?? null;
            if ($ci !== null) {
                $sections[$sid]['rows'][$rowKey]['v'][$ci] += (int) $r['wagon_count'];
                $sections[$sid]['total'][$ci]             += (int) $r['wagon_count'];
                $sections[$sid]['grand_total']            += (int) $r['wagon_count'];
            }
        }

        // Убираем ключи из rows (превращаем в индексный массив)
        foreach ($sections as &$sec) {
            $sec['rows'] = array_values($sec['rows']);
        }

        return [
            'date'     => $date,
            'cols'     => $cols,
            'sections' => array_values($sections),
        ];
    }

    // ── Вспомогательный метод — вернуть JSON-ответ ───────────────

    private function json(ResponseInterface $response, mixed $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
