<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
// 1. Исправили namespace и добавили точку с запятой
use App\Controllers\ApiController;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class MapsController
{
    private DbInterface $db;
    private array $config;

    public function __construct(DbInterface $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function showMaps(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $apiController = new ApiController($this->db);
        $dtsByType = $apiController->getLatestDtsByType(null, ['Подход', 'Отправка']);

        // Получаем условия для SQL-запроса
        $cond = $apiController->latestDtCondition($dtsByType, 'xdr');
        $params = $cond['params'] ?? [];
        // 3. Сначала делаем запрос к базе данных, чтобы переменная $reports была готова
        $reports = $this->db->fetchAll(
            "SELECT xdr.wagon_no, 
                         rs.esr_code, 
                         rs.STATION_NAME,
                         rs.LATITUDE,
                         rs.LONGITUDE
             FROM xx_dislocation_rjd xdr left join xx_rjd_stations rs on xdr.dest_station_esr_code = rs.esr_code
             WHERE {$cond['sql']}  
            ",
            $params
        );

        // 4. Только ПОСЛЕ получения данных включаем буфер и подключаем шаблон
        ob_start();
        include __DIR__ . '/../../templates/maps.php';
        $html = ob_get_clean();

        $appName = $this->config['app_name'] ?? 'Карта Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        $base = $this->config['base_path'] ?? '';
        return $response->withHeader('Location', $base . $url)->withStatus(302);
    }
}