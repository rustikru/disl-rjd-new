<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
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

        ob_start();
        require __DIR__ . '/../../templates/app.php';
        $response->getBody()->write(ob_get_clean());

        return $response;
    }

    /**
     * Возвращает список страниц, доступных текущему пользователю.
     * Администратор и режим первичной настройки — все страницы.
     */
    private function resolveAllowedPages(array $user): array
    {
        $allPages = array_keys(AdminController::PAGES);

        // Администратор видит всё
        if ($user['is_admin'] ?? false) {
            return $allPages;
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

            return $pages;
        } catch (\Throwable $e) {
            // Инфраструктура ролей ещё не развёрнута
            return $allPages;
        }
    }
}
