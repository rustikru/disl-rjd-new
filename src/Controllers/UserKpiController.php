<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use App\Reports\KpiCondition;
use App\Reports\KpiGroupingTemplates;
use App\Services\Organizations;
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
                "SELECT id, type, label, DBMS_LOB.SUBSTR(where_config, 3500, 1) AS where_config
                   FROM XX_ETW.XX_RJD_KPI_TBL
                  WHERE id = :id AND " . ($isAdminEdit ? "1=1" : "owner_id = :owner_id") . " AND is_system = 'N'",
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
        $adminEdit = $isAdminEdit;
        ob_start();
        include __DIR__ . '/../../templates/user-kpi-form.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
