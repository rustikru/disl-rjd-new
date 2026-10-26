<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Services\OrganizationService;
use App\Services\WagonMovementService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class WagonController
{
    public function __construct(private DbInterface $db, private array $config = []) {}

    /** GET /wagon */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? 'Дислокация';
        $user = $_SESSION['user'] ?? [];
        $number = trim((string) ($request->getQueryParams()['wagon_no'] ?? ''));
        ob_start();
        include __DIR__ . '/../../templates/wagon.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** GET /api/wagon/movement */
    public function movement(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $number = trim((string) ($request->getQueryParams()['wagon_no'] ?? ''));
        try {
            $data = (new WagonMovementService($this->db, new OrganizationService($this->db)))->rows($number);
        } catch (\InvalidArgumentException $error) {
            $response = $response->withStatus(400);
            $data = ['error' => $error->getMessage()];
        }
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
