<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Services\OrganizationService;
use App\Services\DirectoryAccessService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class ProfileController
{
    public function __construct(private DbInterface $db, private array $config = []) {}

    /** GET /profile */
    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $_SESSION['user'] ?? [];
        $id = (int) ($user['id'] ?? 0);
        if (!$id) {
            return $response->withHeader('Location', ($this->config['base_path'] ?? '') . '/login')->withStatus(302);
        }
        $profile = $this->db->fetchOne(
            'SELECT id, username, display_name, email, is_active FROM xx_rjd_users WHERE id = :id', ['id' => $id]
        );
        if (!$profile) {
            $response->getBody()->write('Пользователь не найден');
            return $response->withStatus(404);
        }
        $roles = $this->db->fetchAll(
            'SELECT r.code, r.name, r.description FROM xx_rjd_roles r JOIN xx_rjd_user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :id ORDER BY r.name', ['id' => $id]
        );
        $pages = $this->db->fetchAll(
            'SELECT DISTINCT p.page FROM xx_rjd_role_pages p JOIN xx_rjd_user_roles ur ON ur.role_id = p.role_id WHERE ur.user_id = :id', ['id' => $id]
        );
        $allowed = DirectoryAccessService::expandPages(array_column($pages, 'page'));
        $isAdmin = !empty($user['is_admin']) || in_array('ADMIN', array_column($roles, 'code'), true);
        $access = $isAdmin ? array_values(AdminController::PAGES) : array_values(array_intersect_key(AdminController::PAGES, array_flip($allowed)));
        $organizationService = new OrganizationService($this->db);
        $organizations = $organizationService->organizations();
        $organizationId = $organizationService->id();
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? 'Дислокация';
        ob_start();
        include __DIR__ . '/../../templates/profile.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
