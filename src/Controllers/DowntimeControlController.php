<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DowntimeControlController
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /** GET /api/downtime-control/detail */
    public function detail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';

        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo !== '') {
            $wagons = array_values(array_filter(array_map('trim', explode(';', $wagonNo))));
            if ($wagons) {
                $placeholders = [];
                foreach ($wagons as $index => $wagon) {
                    $key = 'wagon_no_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = $wagon;
                }
                $whereCond .= ' AND car_number IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $dateFrom = $params['date_from'] ?? '';
        if ($dateFrom !== '') {
            $whereCond .= " AND (end_date IS NULL OR TRUNC(end_date) >= TO_DATE(:date_from, 'YYYY-MM-DD'))";
            $bindings['date_from'] = $dateFrom;
        }

        $dateTo = $params['date_to'] ?? '';
        if ($dateTo !== '') {
            $whereCond .= " AND (start_date IS NULL OR TRUNC(start_date) <= TO_DATE(:date_to, 'YYYY-MM-DD'))";
            $bindings['date_to'] = $dateTo;
        }

        $rows = $this->db->fetchAll(
            "SELECT *
               FROM xx_disl_idle_control_v
              WHERE $whereCond
              ORDER BY id_control DESC",
            $bindings
        );

        $response->getBody()->write(
            json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
