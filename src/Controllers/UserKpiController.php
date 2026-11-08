<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use App\Reports\KpiCondition;
use App\Reports\KpiGroupingTemplates;
use App\Services\Authorization;
use App\Services\Organizations;
use App\Services\PageRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserKpiController
{
    private Organizations $organizations;

    public function __construct(private DbInterface $db, private array $config = [])
    {
        $this->organizations = new Organizations($db);
    }
    
    public function form(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $type = trim((string) ($request->getQueryParams()['kpi_type'] ?? ''));
        $id = (int) ($request->getQueryParams()['id'] ?? 0);
        $isAdminEdit = ($request->getQueryParams()['admin'] ?? '') === '1' && \App\Services\Authorization::isSuper();
        if (!$this->organizations->isMtf() || !in_array($type, ['approach_kpi', 'departue_kpi'], true)) {
            return $response->withStatus(404);
        }
        
        $edited = null;
        if ($id > 0) {
            $edited = $this->db->fetchOne(
                "SELECT id, type, label, visibility, DBMS_LOB.SUBSTR(where_config, 3500, 1) AS where_config
                   FROM XX_ETW.XX_RJD_KPI_TBL
                  WHERE id = :id AND " . ($isAdminEdit ? "1=1" : "owner_id = :owner_id AND visibility = 'PRIVATE'") . " AND is_system = 'N'",
                $isAdminEdit ? ['id' => $id] : ['id' => $id, 'owner_id' => (int) ($_SESSION['user']['id'] ?? 0)]
            );
            if (!$edited || $edited['type'] !== $type) {
                return $response->withStatus(404);
            }
        }

        $config = $edited ? json_decode((string) ($edited['where_config'] ?? ''), true) : [];
        $config = is_array($config) ? $config : [];
        $detail = is_array($config['detail'] ?? null) ? $config['detail'] : [];
        $fields = KpiCondition::fields($type);
        $allFields = KpiCondition::fields($type, true);
        $groupingTemplates = KpiGroupingTemplates::options();
        $detailChoice = strtoupper((string) ($detail['template'] ?? ''));
        if ($detailChoice === '' && strtoupper((string) ($detail['type'] ?? '')) === 'GROUPED') {
            $detailChoice = (string) (($detail['group_field'] ?? '') === 'train_no' ? 'TRAIN_NUMBER' : (($detail['group_field'] ?? '') === 'train_index' ? 'TRAIN_INDEX' : ''));
        }
        if ($detailChoice === '') $detailChoice = 'TABLE';
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? 'Дислокация';
        $csrf = $_SESSION['csrf_token'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $canSetGlobal = \App\Services\Authorization::isSuper();
        $adminEdit = $isAdminEdit;
        ob_start();
        include __DIR__ . '/../../templates/user-kpi-form.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function hidden(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        try {
            $rows = $this->db->fetchAll(
                "SELECT k.id, k.label, k.page_code
                   FROM XX_ETW.XX_RJD_KPI_HIDDEN h
                   JOIN XX_ETW.XX_RJD_KPI_TBL k ON k.id = h.kpi_id
                  WHERE h.user_id = :user_id
                    AND k.is_system = 'N' AND k.visibility = 'GLOBAL' AND k.is_active = 'Y'
                  ORDER BY k.page_code, k.order_by, k.id",
                ['user_id' => $userId]
            );
            if (!Authorization::isSuper()) {
                $allowedPages = PageRegistry::forUser(
                    (array) ($_SESSION['user']['allowed_pages'] ?? []),
                    (array) ($_SESSION['user'] ?? [])
                );
                $rows = array_values(array_filter($rows, static fn(array $row): bool =>
                    in_array((string) ($row['page_code'] ?? ''), $allowedPages, true)
                ));
            }
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'load_hidden_kpi', 'user_id' => $userId], $request);
            $rows = [];
            $flashErr = 'Не удалось загрузить скрытые KPI. Код ошибки: ' . $code;
        }
        $basePath = $this->config['base_path'] ?? '';
        $appName = $this->config['app_name'] ?? 'Дислокация';
        $csrf = $_SESSION['csrf_token'] ?? '';
        $user = $_SESSION['user'] ?? [];
        ob_start();
        include __DIR__ . '/../../templates/hidden-kpi.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
