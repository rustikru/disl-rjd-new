<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Reports\MailingStore;
use App\Reports\ReportCatalog;
use App\Services\OrganizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MailingController
{
    private MailingStore $store;
    private OrganizationService $organizations;
    private array $config;

    public function __construct(DbInterface $db, OrganizationService $organizations, array $config = [])
    {
        $this->store = new MailingStore($db);
        $this->organizations = $organizations;
        $this->config = $config;
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $this->userId();
        $mailings = $this->store->listByUser($userId);
        $runs = $this->store->recentRuns($userId);
        $catalog = ReportCatalog::all();
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;

        return $this->render($response, 'mailings/index.php', compact(
            'mailings', 'runs', 'catalog', 'flashOk', 'flashErr'
        ));
    }

    public function form(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $id = (int) ($query['id'] ?? 0);
        $mailing = $id > 0 ? $this->store->find($id, $this->userId()) : null;
        if ($id > 0 && !$mailing) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Рассылка не найдена'));
        }

        if (!$mailing) {
            $reportCode = (string) ($query['report_code'] ?? 'dislocation');
            if (!ReportCatalog::find($reportCode)) {
                $reportCode = 'dislocation';
            }
            $report = ReportCatalog::find($reportCode) ?? [];
            $rawFilters = json_decode((string) ($query['filters'] ?? '{}'), true);
            $filters = ReportCatalog::cleanFilters($reportCode, is_array($rawFilters) ? $rawFilters : []);
            $view = strtoupper((string) ($query['report_view'] ?? ($report['default_view'] ?? 'DETAIL')));
            if (!in_array($view, $report['views'] ?? [], true)) {
                $view = $report['default_view'] ?? 'DETAIL';
            }
            $mailing = [
                'id' => 0,
                'organization_id' => !empty($report['organization_required']) ? $this->organizations->id() : null,
                'name' => ($report['name'] ?? 'Отчёт') . ' — рассылка',
                'report_code' => $reportCode,
                'report_view' => $view,
                'file_format' => 'XLSX',
                'filters' => $filters,
                'subject_text' => ($report['name'] ?? 'Отчёт') . ' за {report_date}',
                'body_text' => 'Добрый день! Во вложении актуальный отчёт.',
                'schedule_type' => 'DAILY',
                'run_time' => '08:00',
                'week_days' => '1,2,3,4,5',
                'month_day' => 1,
                'skip_empty' => 1,
                'is_active' => 1,
                'recipients' => [['email' => '', 'send_type' => 'TO']],
            ];
        }

        $catalog = ReportCatalog::all();
        $availableOrganizations = $this->organizations->organizations();
        $queryError = $query['err'] ?? null;
        return $this->render($response, 'mailings/form.php', compact(
            'mailing', 'catalog', 'availableOrganizations', 'queryError'
        ));
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Ошибка запроса, попробуйте снова'));
        }

        $id = (int) ($body['id'] ?? 0);
        if ($id > 0 && !$this->store->find($id, $this->userId())) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Рассылка не найдена'));
        }

        $reportCode = trim((string) ($body['report_code'] ?? ''));
        $report = ReportCatalog::find($reportCode);
        if (!$report) {
            return $this->backToForm($response, $id, 'Выберите отчёт');
        }

        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            return $this->backToForm($response, $id, 'Укажите название рассылки');
        }

        $view = strtoupper((string) ($body['report_view'] ?? 'DETAIL'));
        if (!in_array($view, $report['views'], true)) {
            return $this->backToForm($response, $id, 'Выбранный вид отчёта недоступен');
        }

        $organizationId = null;
        if (!empty($report['organization_required'])) {
            $organizationId = (int) ($body['organization_id'] ?? 0);
            if (!$this->hasOrganization($organizationId)) {
                return $this->backToForm($response, $id, 'Нет доступа к выбранной организации');
            }
        }

        $filters = ReportCatalog::cleanFilters($reportCode, (array) ($body['filters'] ?? []));
        $missing = ReportCatalog::missingRequiredFilters($reportCode, $filters);
        if ($missing) {
            return $this->backToForm($response, $id, 'Заполните фильтры: ' . implode(', ', $missing));
        }

        $recipients = $this->recipients($body);
        if (!$recipients) {
            return $this->backToForm($response, $id, 'Добавьте хотя бы одного получателя');
        }

        $scheduleType = strtoupper((string) ($body['schedule_type'] ?? 'DAILY'));
        if (!in_array($scheduleType, ['MANUAL', 'DAILY', 'WEEKLY', 'MONTHLY'], true)) {
            $scheduleType = 'DAILY';
        }
        $runTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) ($body['run_time'] ?? ''))
            ? (string) $body['run_time']
            : '08:00';
        $weekDays = $this->weekDays((array) ($body['week_days'] ?? []));
        if ($scheduleType === 'WEEKLY' && $weekDays === '') {
            return $this->backToForm($response, $id, 'Выберите дни недели');
        }

        $mailing = [
            'id' => $id,
            'user_id' => $this->userId(),
            'organization_id' => $organizationId,
            'name' => mb_substr($name, 0, 200),
            'report_code' => $reportCode,
            'report_view' => $view,
            'file_format' => strtoupper((string) ($body['file_format'] ?? 'XLSX')) === 'CSV' ? 'CSV' : 'XLSX',
            'filters' => $filters,
            'subject_text' => $this->nullableText($body['subject_text'] ?? null, 500),
            'body_text' => $this->nullableText($body['body_text'] ?? null, 2000),
            'schedule_type' => $scheduleType,
            'run_time' => $runTime,
            'week_days' => $weekDays !== '' ? $weekDays : null,
            'month_day' => $scheduleType === 'MONTHLY' ? max(1, min(31, (int) ($body['month_day'] ?? 1))) : null,
            'skip_empty' => isset($body['skip_empty']) ? 1 : 0,
            'is_active' => isset($body['is_active']) ? 1 : 0,
        ];
        $mailing['next_run_at'] = MailingStore::nextRun($mailing);

        try {
            $this->store->save($mailing, $recipients);
        } catch (\Throwable $error) {
            return $this->backToForm($response, $id, 'Не удалось сохранить рассылку: ' . $this->shortError($error));
        }

        return $this->redirect($response, '/mailings?ok=' . urlencode('Рассылка сохранена'));
    }

    public function active(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Ошибка запроса'));
        }
        $id = (int) ($body['id'] ?? 0);
        $active = (int) ($body['is_active'] ?? 0) === 1;
        $mailing = $this->store->find($id, $this->userId());
        if (!$mailing) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Рассылка не найдена'));
        }
        $mailing['is_active'] = $active ? 1 : 0;
        $nextRunAt = MailingStore::nextRun($mailing);
        $this->store->setActive($id, $this->userId(), $active, $nextRunAt);
        return $this->redirect($response, '/mailings?ok=' . urlencode($active ? 'Рассылка включена' : 'Рассылка отключена'));
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Ошибка запроса'));
        }
        $this->store->delete((int) ($body['id'] ?? 0), $this->userId());
        return $this->redirect($response, '/mailings?ok=' . urlencode('Рассылка удалена'));
    }

    public function run(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        if (!$this->checkCsrf($body)) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Ошибка запроса'));
        }
        if (!$this->store->queue((int) ($body['id'] ?? 0), $this->userId())) {
            return $this->redirect($response, '/mailings?err=' . urlencode('Рассылка не найдена'));
        }
        return $this->redirect($response, '/mailings?ok=' . urlencode('Отчёт добавлен в очередь'));
    }

    public function catalog(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'reports' => ReportCatalog::all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function recipients(array $body): array
    {
        $emails = (array) ($body['recipient_email'] ?? []);
        $types = (array) ($body['recipient_type'] ?? []);
        $result = [];
        $seen = [];
        foreach ($emails as $index => $rawEmail) {
            $email = mb_strtolower(trim((string) $rawEmail));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $type = strtoupper((string) ($types[$index] ?? 'TO'));
            if (!in_array($type, ['TO', 'CC', 'BCC'], true)) {
                $type = 'TO';
            }
            $key = $type . ':' . $email;
            if (!isset($seen[$key])) {
                $result[] = ['email' => $email, 'send_type' => $type];
                $seen[$key] = true;
            }
        }
        return $result;
    }

    private function weekDays(array $values): string
    {
        $days = array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn(int $day): bool => $day >= 1 && $day <= 7
        )));
        sort($days);
        return implode(',', $days);
    }

    private function hasOrganization(int $organizationId): bool
    {
        foreach ($this->organizations->organizations() as $organization) {
            if ((int) $organization['id'] === $organizationId) {
                return true;
            }
        }
        return false;
    }

    private function nullableText(mixed $value, int $length): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? mb_substr($value, 0, $length) : null;
    }

    private function checkCsrf(array $body): bool
    {
        $token = (string) ($body['csrf_token'] ?? '');
        return $token !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    private function backToForm(ResponseInterface $response, int $id, string $message): ResponseInterface
    {
        $path = '/mailings/form?err=' . urlencode($message);
        if ($id > 0) {
            $path .= '&id=' . $id;
        }
        return $this->redirect($response, $path);
    }

    private function shortError(\Throwable $error): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', $error->getMessage()) ?: 'ошибка базы данных', 0, 350);
    }

    private function render(ResponseInterface $response, string $template, array $vars): ResponseInterface
    {
        extract($vars, EXTR_SKIP);
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $csrf = $_SESSION['csrf_token'] ?? '';
        ob_start();
        require __DIR__ . '/../../templates/' . $template;
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function redirect(ResponseInterface $response, string $path): ResponseInterface
    {
        return $response->withHeader('Location', ($this->config['base_path'] ?? '') . $path)->withStatus(302);
    }
}
