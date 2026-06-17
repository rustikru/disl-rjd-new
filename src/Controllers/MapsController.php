<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
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
        $basePath = $this->config['base_path'] ?? '';
        $appName  = $this->config['app_name']  ?? 'Дислокация';

        ob_start();
        include __DIR__ . '/../../templates/maps.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}