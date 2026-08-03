<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Controllers\ApiController;
use App\Services\OrganizationService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class MapsController
{
    private DbInterface $db;
    private array $config;
    private OrganizationService $organizations;

    public function __construct(
        DbInterface $db,
        array $config = [],
        ?OrganizationService $organizations = null
    )
    {
        $this->db = $db;
        $this->config = $config;
        $this->organizations = $organizations ?? new OrganizationService($db);
    }

    public function showMaps(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? 'Дислокация';
        $user = $_SESSION['user'] ?? ['display_name' => '', 'username' => '', 'auth_source' => ''];

        $apiController = new ApiController($this->db, $this->organizations);
        $dtsByType = $apiController->getLatestDtsByType(null, ['Подход', 'Отправка']);
        $cond = $apiController->latestDtCondition($dtsByType, 'xdr');
        $stationsWithoutCoordinates = $this->db->fetchAll(
            "SELECT xdr.oper_station_esr_code AS esr_code,
                    MAX(NVL(rs.station_name, xdr.oper_station)) AS station_name,
                    COUNT(*) AS wagon_count
               FROM xx_dislocation_rjd xdr
               LEFT JOIN xx_rjd_stations rs
                 ON rs.esr_code = xdr.oper_station_esr_code
              WHERE {$cond['sql']}
                AND xdr.oper_station_esr_code IS NOT NULL
                AND (rs.esr_code IS NULL OR rs.latitude IS NULL OR rs.longitude IS NULL)
              GROUP BY xdr.oper_station_esr_code
              ORDER BY MAX(NVL(rs.station_name, xdr.oper_station))",
            $cond['params']
        );

        $reportDtLabel = '';
        if (!empty($dtsByType)) {
            $dt = max($dtsByType);
            try {
                $reportDtLabel = (new \DateTime($dt))->format('d.m.Y H:i:s');
            } catch (\Exception $e) {
                $reportDtLabel = $dt;
            }
        }

        $excludedWagons = '';
        if ($this->organizations->isMtf()) {
            $excludedWagons = "
               AND NOT EXISTS (
                   SELECT 1
                     FROM xx_disl_idle_control_v dic
                    WHERE TRIM(dic.car_number) = TRIM(TO_CHAR(xdr.wagon_no))
                      AND UPPER(TRIM(dic.is_excluded)) = 'Y'
                      AND UPPER(REPLACE(TRIM(dic.idle_reasons_name), 'Ё', 'Е'))
                          IN ('ЛОМ', 'МЕТАЛЛОЛОМ')
               )";
        }

        $rows = $this->db->fetchAll(
            "SELECT  
                    xdr.wagon_no,
                    xdr.wagon_type_code,
                    xdr.cargo_weight_kg,
                    xdr.cargo_name,
                    xdr.dest_station,
                    xdr.wagon_state,
                    xdr.idle_time_days,
                    xdr.oper_road,
                    xdr.oper_station,
                    xdr.days_no_oper,
                    xdr.days_no_move,
                    xdr.lessee,
                    xdr.lease_home_station,
                    rs.esr_code,
                    rs.station_name,
                    rs.latitude,
                    rs.longitude
             FROM xx_dislocation_rjd xdr
             LEFT JOIN xx_rjd_stations rs ON xdr.oper_station_esr_code = rs.esr_code
             WHERE {$cond['sql']}
               $excludedWagons",
            $cond['params']
        );

        $stationsMap = [];
        $cargosSet = [];
        foreach ($rows as $r) {
            $code = (string) ($r['esr_code'] ?? $r['dest_station_esr_code'] ?? '');
            if (!$code) {
                continue;
            }
            if ($r['latitude'] === null || $r['longitude'] === null) {
                continue;
            }
            if (!isset($stationsMap[$code])) {
                $stationsMap[$code] = [
                    'code' => $code,
                    'name' => (string) ($r['station_name'] ?? $r['oper_station'] ?? ''),
                    'road' => (string) ($r['oper_road'] ?? ''),
                    'lat' => (float) $r['latitude'],
                    'lng' => (float) $r['longitude'],
                    'count' => 0,
                    'wagons' => [],
                ];
            }
            $stationsMap[$code]['count']++;
            $stationsMap[$code]['wagons'][] = [
                'wagon_num' => (string) ($r['wagon_no'] ?? ''),
                'wagon_type' => (string) ($r['wagon_type_code'] ?? ''),
                'ld' => isset($r['cargo_weight_kg']) && (float) $r['cargo_weight_kg'] != 0.0,
                'cargo' => (string) ($r['cargo_name'] ?? ''),
                'dest_station' => (string) ($r['dest_station'] ?? ''),
                'wagon_state' => (string) ($r['wagon_state'] ?? ''),
                'days_no_move' => (int) ($r['days_no_move'] ?? 0),
                'days_no_oper' => (int) ($r['days_no_oper'] ?? 0),
                'lessee' => (string) ($r['lessee'] ?? ''),
                'lease_home_station' => (string) ($r['lease_home_station'] ?? ''),

                'latitude' => (string) ($r['latitude'] ?? ''),
                'longitude' => (string) ($r['longitude'] ?? ''),
            ];
            $cargo = trim((string) ($r['cargo_name'] ?? ''));
            if ($cargo !== '') {
                $cargosSet[$cargo] = true;
            }
            $lessee = trim((string) ($r['lessee'] ?? ''));
            if ($lessee !== '') {
                $lesseeSet[$lessee] = true;
            }
            $leaseStation = trim((string) ($r['lease_home_station'] ?? ''));
            if ($leaseStation !== '') {
                $leaseStationSet[$leaseStation] = true;
            }
        }
        // Груз
        $cargos = array_keys($cargosSet);
        sort($cargos);
        // Арендатор
        $lessees = array_keys($lesseeSet);
        sort($lessees);
        // Станция приписки арендатора
        $leaseStations = array_keys($leaseStationSet);
        sort($lessees);
        // Станции
        $stationsJson = json_encode(array_values($stationsMap), JSON_UNESCAPED_UNICODE);
        $cargosJson = json_encode($cargos, JSON_UNESCAPED_UNICODE);
        $lesseesJson = json_encode($lessees, JSON_UNESCAPED_UNICODE);
        $leaseStationsJson = json_encode($leaseStations, JSON_UNESCAPED_UNICODE);
        $stationsWithoutCoordinatesJson = json_encode($stationsWithoutCoordinates, JSON_UNESCAPED_UNICODE);

        ob_start();
        include __DIR__ . '/../../templates/maps.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
