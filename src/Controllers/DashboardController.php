<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** GET / */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $appName  = $this->config['app_name'];
        $basePath = $this->config['base_path'] ?? '';
        $user     = $_SESSION['user'];

        ob_start();
        require __DIR__ . '/../../templates/app.php';
        $response->getBody()->write(ob_get_clean());

        return $response;
    }
}
