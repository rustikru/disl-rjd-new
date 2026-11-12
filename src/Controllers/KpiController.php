<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use App\Services\Logging\AuditLog;
use App\Services\Auth\Authorization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class KpiController
{
    public function __construct(private DbInterface $db, private array $config = [])
    {
    }
    
    public function page(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!Authorization::isSuper()) return $response->withStatus(403);
        try {
            $rows = $this->db->fetchAll(
                "SELECT k.id, k.type, k.code, k.label, k.order_by, k.trend_period, k.trend_unit,
                        k.page_code, k.owner_id, k.is_system, k.visibility, k.is_active, DBMS_LOB.SUBSTR(k.where_config, 3500, 1) AS where_config,
                        NVL(u.display_name, u.username) AS owner_name
                   FROM XX_ETW.XX_RJD_KPI_TBL k
                   LEFT JOIN xx_rjd_users u ON u.id = k.owner_id
                  ORDER BY k.is_system DESC, k.page_code, k.order_by, k.id"
            );
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'load_kpi_admin']);
            $rows = [];
            $flashErr = 'Не удалось загрузить KPI. Код ошибки: ' . $code;
        }
        $appName = $this->config['app_name'] ?? 'Дислокация';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $csrf = $_SESSION['csrf_token'] ?? '';
        $userKpiManagementEnabled = (bool) ($this->config['user_kpi_management_enabled'] ?? false);
        ob_start();
        include __DIR__ . '/../../templates/admin/kpi.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        if (!Authorization::isSuper()) return $response->withStatus(403);
        if (!$this->validCsrf($body)) return $response->withStatus(403);
        $id = (int) ($body['id'] ?? 0);
        $row = $this->db->fetchOne("SELECT id, is_system FROM XX_ETW.XX_RJD_KPI_TBL WHERE id = :id", ['id' => $id]);
        if (!$row) return $this->redirect($response, '/admin/kpi');
        if ($row['is_system'] !== 'Y' && !$this->userManagementEnabled()) return $response->withStatus(403);
        $label = trim((string) ($body['label'] ?? ''));
        $active = ($body['is_active'] ?? 'N') === 'Y' ? 'Y' : 'N';
        if ($label === '' || mb_strlen($label) > 150) return $this->redirect($response, '/admin/kpi?error=validation');
        try {
            if ($row['is_system'] === 'Y') {
                $this->db->execute(
                    "UPDATE XX_ETW.XX_RJD_KPI_TBL
                        SET label = :label, order_by = :order_by, trend_period = :trend_period,
                            trend_unit = :trend_unit, is_active = :is_active
                      WHERE id = :id AND is_system = 'Y'",
                    [
                        'label' => $label,
                        'order_by' => max(0, (int) ($body['order_by'] ?? 0)),
                        'trend_period' => trim((string) ($body['trend_period'] ?? '')) ?: null,
                        'trend_unit' => trim((string) ($body['trend_unit'] ?? 'шт.')) ?: 'шт.',
                        'is_active' => $active,
                        'id' => $id,
                    ]
                );
            } else {
                $this->db->execute(
                    "UPDATE XX_ETW.XX_RJD_KPI_TBL SET label = :label, is_active = :is_active WHERE id = :id AND is_system = 'N'",
                    ['label' => $label, 'is_active' => $active, 'id' => $id]
                );
            }
            (new AuditLog($this->db))->write('KPI_ADMIN_UPDATE', 'KPI', $id, 'Изменены параметры KPI');
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'save_admin_kpi', 'kpi_id' => $id], $request);
            return $this->redirect($response, '/admin/kpi?error=' . rawurlencode($code));
        }
        return $this->redirect($response, '/admin/kpi?saved=1');
    }

    public function deleteUser(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        if (!Authorization::isSuper()) return $response->withStatus(403);
        if (!$this->userManagementEnabled()) return $response->withStatus(403);
        if (!$this->validCsrf($body)) return $response->withStatus(403);
        $id = (int) ($body['id'] ?? 0);
        try {
            $count = $this->db->execute("DELETE FROM XX_ETW.XX_RJD_KPI_TBL WHERE id = :id AND is_system = 'N'", ['id' => $id]);
            if ($count > 0) (new AuditLog($this->db))->write('KPI_ADMIN_DELETE', 'KPI', $id, 'Удалён пользовательский KPI');
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'delete_admin_user_kpi', 'kpi_id' => $id], $request);
            return $this->redirect($response, '/admin/kpi?error=' . rawurlencode($code));
        }
        return $this->redirect($response, '/admin/kpi?deleted=1');
    }

    private function validCsrf(array $body): bool
    {
        return !empty($body['csrf_token']) && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) $body['csrf_token']);
    }

    private function userManagementEnabled(): bool
    {
        return (bool) ($this->config['user_kpi_management_enabled'] ?? false);
    }

    private function redirect(ResponseInterface $response, string $path): ResponseInterface
    {
        return $response->withHeader('Location', ($this->config['base_path'] ?? '') . $path)->withStatus(302);
    }
}
