<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Reports\ReportConfig;
use App\Services\PageAvailability;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DashboardController
{
    private array $config;
    private ?DbInterface $db;

    public function __construct(array $config, ?DbInterface $db = null)
    {
        $this->config = $config;
        $this->db     = $db;
    }

    /** GET / */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $appName  = $this->config['app_name'];
        $basePath = $this->config['base_path'] ?? '';
        $user     = $_SESSION['user'];

        $allowedPages = $this->resolveAllowedPages($user);
        $navConfig    = require __DIR__ . '/../Navigation.php';
        $reportDefinitions = ReportConfig::forContext('page');
        $pageAvailability = new PageAvailability(
            (array) ($this->config['disabled_pages'] ?? []),
            (string) ($this->config['base_path'] ?? '')
        );
        $disabledPages = (array) ($this->config['disabled_pages'] ?? []);
        $dislocationStaleHours = (int) $this->config['dislocation_stale_hours'];
        $navConfig = $pageAvailability->filterNavigation($navConfig);
        $reportDefinitions = $pageAvailability->filterReports($reportDefinitions);

        ob_start();
        require __DIR__ . '/../../templates/app.php';
        $response->getBody()->write(ob_get_clean());

        return $response;
    }

    /** GET /wagon */
    public function wagon(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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

    /**
     * Возвращает список страниц, доступных текущему пользователю.
     * SUPER получает весь каталог, ADMIN — каталог без super_only.
     * При первичной настройке сохраняется прежний открытый режим.
     */
    private function resolveAllowedPages(array $user): array
    {
        $allPages = array_keys(\App\Services\PageRegistry::labels());

        if (\App\Services\Authorization::isSuper($user)) {
            return $allPages;
        }

        if (\App\Services\Authorization::isAdmin($user)) {
            return \App\Services\PageRegistry::adminPermissions();
        }

        if ($this->db === null) {
            return $allPages;
        }

        try {
            $rows = $this->db->fetchAll(
                'SELECT p.page
                   FROM xx_rjd_role_pages p
                   JOIN xx_rjd_user_roles ur ON ur.role_id = p.role_id
                  WHERE ur.user_id = :id',
                ['id' => (int) ($user['id'] ?? 0)]
            );
            $pages = array_unique(array_map(static fn($r) => $r['page'], $rows));

            // Режим первичной настройки: если нет ни одного администратора — все страницы открыты
            if (empty($pages)) {
                $cnt = $this->db->fetchOne(
                    "SELECT COUNT(*) AS cnt FROM xx_rjd_user_roles ur
                      JOIN xx_rjd_roles r ON r.id = ur.role_id
                     WHERE r.code = 'ADMIN'"
                );
                if ((int) ($cnt['cnt'] ?? 0) === 0) {
                    return $allPages;
                }
            }

            return \App\Services\DirectoryAccess::expandPages($pages);
        } catch (\Throwable $e) {
            // Инфраструктура ролей ещё не развёрнута
            return $allPages;
        }
    }
}
