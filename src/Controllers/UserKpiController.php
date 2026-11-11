<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Logging\ErrorLogger;
use App\Reports\KpiCondition;
use App\Reports\KpiGroupingTemplates;
use App\Reports\KpiTypeRegistry;
use App\Services\Authorization;
use App\Services\Organizations;
use App\Services\PageRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserKpiController
{
    private Organizations $organizations;

    public function __construct(
        private DbInterface $db,
        private array $config = [],
        ?Organizations $organizations = null
    )
    {
        $this->organizations = $organizations ?? new Organizations($db);
    }
    
    public function form(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $response->withStatus(404);
        }
        $type = trim((string) ($request->getQueryParams()['kpi_type'] ?? ''));
        $id = (int) ($request->getQueryParams()['id'] ?? 0);
        $modalForm = ($request->getQueryParams()['modal'] ?? '') === '1';
        $isAdminEdit = ($request->getQueryParams()['admin'] ?? '') === '1' && \App\Services\Authorization::isSuper();
        if (!$this->organizations->isMtf() || !KpiTypeRegistry::isUserCreatable($type)) {
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
        $typeLabel = (string) (KpiTypeRegistry::get($type)['label'] ?? 'KPI');
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

    public function catalog(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $this->json($response->withStatus(403), ['error' => 'Управление KPI временно отключено']);
        }
        $type = trim((string) ($request->getQueryParams()['kpi_type'] ?? ''));
        if (!KpiTypeRegistry::isUserCreatable($type) || !$this->organizations->isMtf()) {
            return $this->json($response->withStatus(403), ['error' => 'Недоступно']);
        }
        return $this->json($response, [
            'fields' => KpiCondition::fields($type),
            'grouping_templates' => KpiGroupingTemplates::options(),
        ]);
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $this->json($response->withStatus(403), ['error' => 'Управление KPI временно отключено']);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->validCsrf((string) ($body['csrf_token'] ?? ''))) {
            return $this->json($response->withStatus(403), ['error' => 'Ошибка проверки запроса']);
        }
        $type = trim((string) ($body['kpi_type'] ?? ''));
        $label = trim((string) ($body['label'] ?? ''));
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $isSuper = Authorization::isSuper();
        $visibility = $isSuper && strtoupper(trim((string) ($body['visibility'] ?? 'PRIVATE'))) === 'GLOBAL'
            ? 'GLOBAL'
            : 'PRIVATE';
        if (!$this->organizations->isMtf() || $userId < 1 || !KpiTypeRegistry::isUserCreatable($type)) {
            return $this->json($response->withStatus(403), ['error' => 'Недоступно']);
        }
        if ($label === '' || mb_strlen($label) > 150) {
            return $this->json($response->withStatus(422), ['error' => 'Название должно содержать от 1 до 150 символов']);
        }
        $config = $body['config'] ?? null;
        if (!is_array($config)) {
            return $this->json($response->withStatus(422), ['error' => 'Не заданы условия KPI']);
        }
        $operator = strtoupper(trim((string) ($config['operator'] ?? '')));
        if (!in_array($operator, ['AND', 'OR'], true)) {
            return $this->json($response->withStatus(422), ['error' => 'Выберите способ объединения условий']);
        }
        $config['operator'] = $operator;
        $detail = is_array($config['detail'] ?? null) ? $config['detail'] : [];
        $detailType = strtoupper((string) ($detail['type'] ?? 'TABLE'));
        if (!in_array($detailType, ['TABLE', 'GROUPED'], true)) {
            return $this->json($response->withStatus(422), ['error' => 'Выбран недопустимый шаблон детализации']);
        }
        if ($detailType === 'GROUPED' && !KpiGroupingTemplates::fromDetail($detail)) {
            return $this->json($response->withStatus(422), ['error' => 'Выберите один из доступных шаблонов группировки']);
        }
        $groupingTemplate = $detailType === 'GROUPED' ? KpiGroupingTemplates::fromDetail($detail) : null;
        $config['detail'] = $detailType === 'TABLE'
            ? ['type' => 'TABLE']
            : ['type' => 'GROUPED', 'template' => $groupingTemplate['code']];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > 3500) {
            return $this->json($response->withStatus(422), ['error' => 'Конфигурация слишком большая']);
        }
        try {
            KpiCondition::compile($type, $json);
            $id = (int) ($body['id'] ?? 0);
            if ($id > 0) {
                $this->db->execute(
                    "UPDATE XX_ETW.XX_RJD_KPI_TBL SET label = :label, where_config = :where_config, visibility = :visibility
                      WHERE id = :id AND " . ($isSuper ? "1=1" : "owner_id = :owner_id AND visibility = 'PRIVATE'") . " AND is_system = 'N' AND type = :type",
                    $isSuper
                        ? ['label' => $label, 'where_config' => $json, 'visibility' => $visibility, 'id' => $id, 'type' => $type]
                        : ['label' => $label, 'where_config' => $json, 'visibility' => 'PRIVATE', 'id' => $id, 'owner_id' => $userId, 'type' => $type]
                );
            } else {
                $this->db->execute(
                    "INSERT INTO XX_ETW.XX_RJD_KPI_TBL
                        (type, code, label, order_by, trend_unit, page_code, owner_id, is_system, visibility, where_config)
                     VALUES (:type, :code, :label, 999, 'шт.', :page_code, :owner_id, 'N', :visibility, :where_config)",
                    [
                        'type' => $type, 'code' => 'user_' . substr(hash('sha256', $userId . microtime(true)), 0, 24),
                        'label' => $label, 'page_code' => KpiTypeRegistry::page($type),
                        'owner_id' => $userId, 'visibility' => $visibility, 'where_config' => $json,
                    ]
                );
            }
        } catch (\InvalidArgumentException $error) {
            return $this->json($response->withStatus(422), ['error' => $error->getMessage()]);
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'save_user_kpi', 'user_id' => $userId]);
            return $this->json($response->withStatus(500), ['error' => 'Не удалось сохранить KPI', 'code' => $code]);
        }
        return $this->json($response, [
            'success' => true,
            'redirect' => !empty($body['admin']) && Authorization::isSuper() ? '/admin/kpi?saved=1' : null,
        ]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $this->json($response->withStatus(403), ['error' => 'Управление KPI временно отключено']);
        }
        $body = (array) $request->getParsedBody();
        if (!$this->validCsrf((string) ($body['csrf_token'] ?? ''))) {
            return $this->json($response->withStatus(403), ['error' => 'Ошибка проверки запроса']);
        }
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $isSuper = Authorization::isSuper();
        try {
            $count = $this->db->execute(
                "DELETE FROM XX_ETW.XX_RJD_KPI_TBL WHERE id = :id AND "
                    . ($isSuper ? "1=1" : "owner_id = :owner_id AND visibility = 'PRIVATE'")
                    . " AND is_system = 'N'",
                $isSuper ? ['id' => $id] : ['id' => $id, 'owner_id' => $userId]
            );
            return $this->json($response, ['success' => $count > 0]);
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'delete_user_kpi', 'user_id' => $userId, 'kpi_id' => $id]);
            return $this->json($response->withStatus(500), ['error' => 'Не удалось удалить KPI', 'code' => $code]);
        }
    }

    public function hide(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $this->json($response->withStatus(403), ['error' => 'Управление KPI временно отключено']);
        }
        return $this->setHidden($request, $response, $id, true);
    }

    public function restore(ServerRequestInterface $request, ResponseInterface $response, int $id): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $this->json($response->withStatus(403), ['error' => 'Управление KPI временно отключено']);
        }
        return $this->setHidden($request, $response, $id, false);
    }

    public function hiddenList(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $this->json($response->withStatus(403), ['error' => 'Управление KPI временно отключено']);
        }
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        try {
            $rows = $this->hiddenRows($userId);
            return $this->json($response, ['rows' => $rows]);
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, ['operation' => 'load_hidden_kpi_api', 'user_id' => $userId], $request);
            return $this->json($response->withStatus(500), ['error' => 'Не удалось загрузить скрытые KPI', 'code' => $code]);
        }
    }

    public function hidden(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->managementEnabled()) {
            return $response->withStatus(404);
        }
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        try {
            $rows = $this->hiddenRows($userId);
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

    private function setHidden(ServerRequestInterface $request, ResponseInterface $response, int $id, bool $hidden): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        if (!$this->validCsrf((string) ($body['csrf_token'] ?? ''))) {
            return $this->json($response->withStatus(403), ['error' => 'Ошибка проверки запроса']);
        }
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId < 1) return $this->json($response->withStatus(403), ['error' => 'Недоступно']);
        try {
            $kpi = $this->db->fetchOne(
                "SELECT id, type, page_code FROM XX_ETW.XX_RJD_KPI_TBL
                  WHERE id = :id AND is_active = 'Y'
                    AND (is_system = 'Y' OR visibility = 'GLOBAL' OR owner_id = :owner_id)",
                ['id' => $id, 'owner_id' => $userId]
            );
            if (!$kpi) return $this->json($response->withStatus(404), ['error' => 'KPI-карточка не найдена']);
            $allowedPages = PageRegistry::forUser(
                (array) ($_SESSION['user']['allowed_pages'] ?? []),
                (array) ($_SESSION['user'] ?? [])
            );
            $page = trim((string) ($kpi['page_code'] ?? ''));
            if ($page === '') $page = (string) (PageRegistry::kpiSourcePage((string) ($kpi['type'] ?? '')) ?? '');
            if (!Authorization::isSuper() && !in_array($page, $allowedPages, true)) {
                return $this->json($response->withStatus(403), ['error' => 'Недоступно']);
            }
            if ($hidden) {
                $this->db->execute(
                    "INSERT INTO XX_ETW.XX_RJD_KPI_HIDDEN (user_id, kpi_id, created_at)
                     SELECT :user_id, :kpi_id, SYSDATE FROM dual
                      WHERE NOT EXISTS (SELECT 1 FROM XX_ETW.XX_RJD_KPI_HIDDEN WHERE user_id = :check_user_id AND kpi_id = :check_kpi_id)",
                    ['user_id' => $userId, 'kpi_id' => $id, 'check_user_id' => $userId, 'check_kpi_id' => $id]
                );
            } else {
                $this->db->execute(
                    "DELETE FROM XX_ETW.XX_RJD_KPI_HIDDEN WHERE user_id = :user_id AND kpi_id = :kpi_id",
                    ['user_id' => $userId, 'kpi_id' => $id]
                );
            }
            return $this->json($response, ['success' => true]);
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, [
                'operation' => $hidden ? 'hide_kpi' : 'restore_kpi',
                'user_id' => $userId,
                'kpi_id' => $id,
            ], $request);
            return $this->json($response->withStatus(500), ['error' => 'Не удалось изменить видимость KPI', 'code' => $code]);
        }
    }

    private function hiddenRows(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT k.id, k.label, k.page_code, k.type, k.is_system, k.visibility
               FROM XX_ETW.XX_RJD_KPI_HIDDEN h
               JOIN XX_ETW.XX_RJD_KPI_TBL k ON k.id = h.kpi_id
              WHERE h.user_id = :user_id
                AND k.is_active = 'Y'
                AND (k.is_system = 'Y' OR k.visibility = 'GLOBAL' OR k.owner_id = :owner_id)
              ORDER BY k.page_code, k.order_by, k.id",
            ['user_id' => $userId, 'owner_id' => $userId]
        );
        if (Authorization::isSuper()) return $rows;

        $allowedPages = PageRegistry::forUser(
            (array) ($_SESSION['user']['allowed_pages'] ?? []),
            (array) ($_SESSION['user'] ?? [])
        );
        return array_values(array_filter($rows, static function (array $row) use ($allowedPages): bool {
            $page = trim((string) ($row['page_code'] ?? ''));
            if ($page === '') $page = (string) (PageRegistry::kpiSourcePage((string) ($row['type'] ?? '')) ?? '');
            return in_array($page, $allowedPages, true);
        }));
    }

    private function validCsrf(string $csrf): bool
    {
        return $csrf !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf);
    }

    private function managementEnabled(): bool
    {
        return (bool) ($this->config['user_kpi_management_enabled'] ?? false);
    }

    private function json(ResponseInterface $response, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
