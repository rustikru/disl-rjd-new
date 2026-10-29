<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Reports\ReportConfig;
use App\Services\Organizations;
use App\Services\AuditLog;
use App\Services\MailingRun;
use App\Services\MailingService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MailingController
{
    private DbInterface $db;
    private array $config;

    public function __construct(DbInterface $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function settingsPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $mailingReports = [];
        foreach (ReportConfig::forContext('mailing') as $reportCode => $reportDefinition) {
            $mailingReports[$reportCode] = [
                'name' => $reportDefinition['name'],
                'selectable' => $reportDefinition['mailing_selectable'] ?? true,
                'filters_endpoint' => $reportDefinition['filters_endpoint'] ?? null,
                'filters' => array_map(
                    static fn(array $filterDefinition): array => [
                        'key' => $filterDefinition['key'],
                        'label' => $filterDefinition['label'],
                        'value_type' => $filterDefinition['value_type'],
                        'options_key' => $filterDefinition['options_key'],
                        'required' => $filterDefinition['required'],
                        'choices' => $filterDefinition['choices'],
                        'initial_value' => $filterDefinition['initial_value'],
                    ],
                    $reportDefinition['filters']
                ),
            ];
        }

        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $csrf = $_SESSION['csrf_token'] ?? '';
        $query = $request->getQueryParams();
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;
        $mailingFormDraft = $_SESSION['mailing_form_draft'] ?? null;
        unset($_SESSION['mailing_form_draft']);
        $currentUserEmail = trim((string) ($user['email'] ?? ''));
        $mailings = [];
        $editingMailing = null;
        try {
            $mailingService = new MailingService($this->db);
            $currentUserId = (int) ($user['id'] ?? 0);
            $mailingsPerPage = 25;
            $mailingsTotal = $mailingService->userMailingCount($currentUserId);
            $mailingsPages = max(1, (int) ceil($mailingsTotal / $mailingsPerPage));
            $mailingsPage = max(1, min((int) ($query['page'] ?? 1), $mailingsPages));
            $mailings = $mailingService->userMailings($currentUserId, $mailingsPage, $mailingsPerPage);
            $editingId = (int) ($query['edit'] ?? 0);
            if ($editingId > 0) {
                $editingMailing = $mailingService->findForUser($editingId, (int) ($user['id'] ?? 0));
                if (!$editingMailing) {
                    $flashErr = 'Рассылка для редактирования не найдена';
                }
            }
            if (is_array($mailingFormDraft)) {
                $editingMailing = $mailingFormDraft;
            }
        } catch (\Throwable $error) {
            $flashErr = 'Хранилище рассылок ещё не подготовлено. Выполните db/20261026_report_mailings.sql';
        }
        $showMailingSettings = $editingMailing !== null || (string) ($query['view'] ?? '') === 'settings';

        ob_start();
        include __DIR__ . '/../../templates/admin/report-mailings.php';
        $response->getBody()->write((string) ob_get_clean());

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function adminRun(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = (array) $request->getParsedBody();
        if (!\App\Services\Authorization::isSuper() || !$this->validCsrf($form)) {
            return $this->adminRedirect($response, 'err', 'Недостаточно прав или сессия устарела');
        }
        $mailingService = new MailingService($this->db);
        $mailing = $mailingService->find((int) ($form['id'] ?? 0));
        if (!$mailing) {
            return $this->adminRedirect($response, 'err', 'Рассылка не найдена');
        }
        $runId = $mailingService->startRun(
            (int) $mailing['id'],
            (string) $this->config['mail_mode'],
            (string) $mailing['recipients']
        );
        try {
            $run = (new MailingRun($this->db, $this->config))->run($mailing);
            $runMessage = (string) ($run['message'] ?? 'Файлы сформированы');
            $mailingService->recordRun((int) $mailing['id'], 'success', $run['directory'], $runMessage . ' администратором');
            $mailingService->finishRun($runId, 'success', $run['directory'], $runMessage . ' администратором');
            (new AuditLog($this->db))->write(
                'MAILING_RUN',
                'MAILING',
                (int) $mailing['id'],
                'Ручной запуск администратором завершён: ' . $runMessage
            );
            return $this->adminRedirect($response, 'ok', $runMessage);
        } catch (\Throwable $error) {
            $mailingService->recordRun((int) $mailing['id'], 'error', null, $error->getMessage());
            $mailingService->finishRun($runId, 'error', null, $error->getMessage());
            return $this->adminRedirect($response, 'err', 'Ошибка запуска: ' . $this->shortError($error));
        }
    }

    public function adminSetActive(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = (array) $request->getParsedBody();
        if (!\App\Services\Authorization::isSuper() || !$this->validCsrf($form)) {
            return $this->adminRedirect($response, 'err', 'Недостаточно прав или сессия устарела');
        }
        $active = (int) ($form['is_active'] ?? 0) === 1;
        try {
            $changed = (new MailingService($this->db))->setActiveByAdmin((int) ($form['id'] ?? 0), $active);
            if ($changed) {
                (new AuditLog($this->db))->write(
                    $active ? 'MAILING_ENABLE' : 'MAILING_DISABLE',
                    'MAILING',
                    (int) ($form['id'] ?? 0),
                    $active ? 'Рассылка включена администратором' : 'Рассылка отключена администратором'
                );
            }
            return $this->adminRedirect(
                $response,
                $changed ? 'ok' : 'err',
                $changed ? ($active ? 'Рассылка включена' : 'Рассылка отключена') : 'Рассылка не найдена'
            );
        } catch (\Throwable $error) {
            return $this->adminRedirect($response, 'err', 'Не удалось изменить рассылку: ' . $this->shortError($error));
        }
    }
    // Отобразить страницу управления рассылками
    public function adminPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!\App\Services\Authorization::isSuper()) {
            $response->getBody()->write('Недостаточно прав');
            return $response->withStatus(403);
        }
        $mailingService = new MailingService($this->db);
        $query = $request->getQueryParams();
        $mailingStatusFilter = (string) ($query['status'] ?? 'all');
        if (!in_array($mailingStatusFilter, ['all', 'active', 'disabled'], true)) {
            $mailingStatusFilter = 'all';
        }
        $mailingStatusCounts = $mailingService->mailingCountsForAdmin();
        $mailingsPerPage = 25;
        $mailingsTotal = $mailingStatusCounts[$mailingStatusFilter];
        $mailingsPages = max(1, (int) ceil($mailingsTotal / $mailingsPerPage));
        $mailingsPage = max(1, min((int) ($query['mailings_page'] ?? 1), $mailingsPages));
        $mailingsForAdmin = $mailingService->mailingsForAdmin($mailingStatusFilter, $mailingsPage, $mailingsPerPage);
        $runJournalPerPage = 25;
        $runJournalTotal = $mailingService->runJournalCountForAdmin();
        $runJournalPages = max(1, (int) ceil($runJournalTotal / $runJournalPerPage));
        $runJournalPage = max(1, min((int) ($query['journal_page'] ?? 1), $runJournalPages));
        $runJournal = $mailingService->runJournalForAdmin($runJournalPage, $runJournalPerPage);
        $reportNames = array_map(
            static fn(array $reportDefinition): string => $reportDefinition['name'],
            ReportConfig::all()
        );
        $appName = $this->config['app_name'] ?? 'Дислокация РЖД';
        $basePath = $this->config['base_path'] ?? '';
        $user = $_SESSION['user'] ?? [];
        $csrf = $_SESSION['csrf_token'] ?? '';
        $flashOk = $query['ok'] ?? null;
        $flashErr = $query['err'] ?? null;

        ob_start();
        include __DIR__ . '/../../templates/admin/report-mailing-dashboard.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
    // Сохранить рассылку
    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = (array) $request->getParsedBody();
        if (!$this->validCsrf($form)) {
            return $this->redirectToForm($response, $form, 'Сессия устарела. Обновите страницу');
        }
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $organizationId = (int) (new Organizations($this->db))->id();
        if ($userId <= 0 || $organizationId <= 0) {
            return $this->redirectToForm($response, $form, 'Не выбрана организация');
        }
        try {
            $editing = (int) ($form['id'] ?? 0) > 0;
            $mailingId = (new MailingService($this->db))->save($form, $userId, $organizationId);
            (new AuditLog($this->db))->write(
                $editing ? 'MAILING_UPDATE' : 'MAILING_CREATE',
                'MAILING',
                $mailingId,
                ($editing ? 'Рассылка изменена: ' : 'Рассылка создана: ') . trim((string) ($form['name'] ?? ''))
            );
            unset($_SESSION['mailing_form_draft']);
            return $this->redirect($response, 'ok', $editing ? 'Рассылка обновлена' : 'Рассылка сохранена');
        } catch (\InvalidArgumentException $error) {
            return $this->redirectToForm($response, $form, $error->getMessage());
        } catch (\Throwable $error) {
            return $this->redirectToForm($response, $form, 'Не удалось сохранить рассылку: ' . $this->shortError($error));
        }
    }
    // Включить или отключить рассылку
    public function setActive(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = (array) $request->getParsedBody();
        if (!$this->validCsrf($form)) {
            return $this->redirect($response, 'err', 'Сессия устарела. Обновите страницу');
        }
        $active = (int) ($form['is_active'] ?? 0) === 1;
        try {
            $changed = (new MailingService($this->db))->setActive(
                (int) ($form['id'] ?? 0),
                (int) ($_SESSION['user']['id'] ?? 0),
                $active
            );
            if ($changed) {
                (new AuditLog($this->db))->write(
                    $active ? 'MAILING_ENABLE' : 'MAILING_DISABLE',
                    'MAILING',
                    (int) ($form['id'] ?? 0),
                    $active ? 'Рассылка включена' : 'Рассылка отключена'
                );
            }
            return $this->redirect(
                $response,
                $changed ? 'ok' : 'err',
                $changed ? ($active ? 'Рассылка включена' : 'Рассылка отключена') : 'Рассылка не найдена'
            );
        } catch (\Throwable $error) {
            return $this->redirect($response, 'err', 'Не удалось изменить рассылку: ' . $this->shortError($error));
        }
    }
    // Удалить рассылку
    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = (array) $request->getParsedBody();
        if (!$this->validCsrf($form)) {
            return $this->redirect($response, 'err', 'Сессия устарела. Обновите страницу');
        }
        try {
            $deleted = (new MailingService($this->db))->delete(
                (int) ($form['id'] ?? 0),
                (int) ($_SESSION['user']['id'] ?? 0)
            );
            if ($deleted) {
                (new AuditLog($this->db))->write('MAILING_DELETE', 'MAILING', (int) ($form['id'] ?? 0), 'Рассылка удалена');
            }
            return $this->redirect($response, $deleted ? 'ok' : 'err', $deleted ? 'Рассылка удалена' : 'Рассылка не найдена');
        } catch (\Throwable $error) {
            return $this->redirect($response, 'err', 'Не удалось удалить рассылку: ' . $this->shortError($error));
        }
    }
    // Запустить рассылку
    public function run(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = (array) $request->getParsedBody();
        if (!$this->validCsrf($form)) {
            return $this->redirect($response, 'err', 'Сессия устарела. Обновите страницу');
        }
        $mailingService = new MailingService($this->db);
        $mailing = $mailingService->findForUser(
            (int) ($form['id'] ?? 0),
            (int) ($_SESSION['user']['id'] ?? 0)
        );
        if (!$mailing) {
            return $this->redirect($response, 'err', 'Рассылка не найдена');
        }
        $availableOrganizationIds = array_map(
            static fn(array $organization): int => (int) $organization['id'],
            (new Organizations($this->db))->organizations()
        );
        if (!in_array((int) $mailing['organization_id'], $availableOrganizationIds, true)) {
            return $this->redirect($response, 'err', 'Организация этой рассылки больше недоступна пользователю');
        }
        $runId = $mailingService->startRun(
            (int) $mailing['id'],
            (string) $this->config['mail_mode'],
            (string) $mailing['recipients']
        );
        try {
            $run = (new MailingRun($this->db, $this->config))->run($mailing);
            $runMessage = (string) ($run['message'] ?? 'Файлы сформированы');
            $mailingService->recordRun((int) $mailing['id'], 'success', $run['directory'], $runMessage);
            $mailingService->finishRun($runId, 'success', $run['directory'], $runMessage);
            (new AuditLog($this->db))->write(
                'MAILING_RUN',
                'MAILING',
                (int) $mailing['id'],
                'Ручной запуск завершён: ' . $runMessage
            );
            if ($runMessage === 'Письма отправлены') {
                $successMessage = $run['directory']
                    ? 'Письма отправлены. Копии файлов сохранены: ' . $run['directory']
                    : 'Письма отправлены. Временные файлы удалены';
            } else {
                $successMessage = 'Отчёты сохранены: ' . $run['directory'];
            }
            return $this->redirect($response, 'ok', $successMessage);
        } catch (\Throwable $error) {
            $mailingService->recordRun((int) $mailing['id'], 'error', null, $error->getMessage());
            $mailingService->finishRun($runId, 'error', null, $error->getMessage());
            return $this->redirect($response, 'err', 'Ошибка запуска: ' . $this->shortError($error));
        }
    }

    private function validCsrf(array $form): bool
    {
        $csrf = (string) ($form['csrf_token'] ?? '');
        return $csrf !== '' && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf);
    }

    private function redirect(ResponseInterface $response, string $kind, string $message): ResponseInterface
    {
        $basePath = $this->config['base_path'] ?? '';
        return $response
            ->withHeader('Location', $basePath . '/profile/report-mailings?' . $kind . '=' . urlencode($message))
            ->withStatus(302);
    }

    private function redirectToForm(ResponseInterface $response, array $form, string $message): ResponseInterface
    {
        $_SESSION['mailing_form_draft'] = $this->mailingFromForm($form);

        $basePath = $this->config['base_path'] ?? '';
        $mailingId = (int) ($form['id'] ?? 0);
        $formQuery = $mailingId > 0 ? 'edit=' . $mailingId : 'view=settings';

        return $response
            ->withHeader('Location', $basePath . '/profile/report-mailings?' . $formQuery . '&err=' . urlencode($message))
            ->withStatus(302);
    }

    private function mailingFromForm(array $form): array
    {
        $items = [];
        foreach ((array) ($form['reports'] ?? []) as $reportCode => $reportForm) {
            $items[] = [
                'report_code' => (string) $reportCode,
                'filters' => (array) ($reportForm['filters'] ?? []),
            ];
        }

        return [
            'id' => (int) ($form['id'] ?? 0),
            'name' => (string) ($form['name'] ?? ''),
            'recipients' => (string) ($form['recipients'] ?? ''),
            'subject' => (string) ($form['subject'] ?? ''),
            'message_body' => (string) ($form['message_body'] ?? ''),
            'schedule_type' => (string) ($form['schedule_type'] ?? 'daily'),
            'send_time' => (string) ($form['send_time'] ?? '08:00'),
            'week_days' => implode(',', (array) ($form['week_days'] ?? [])),
            'month_day' => (int) ($form['month_day'] ?? 1),
            'items' => $items,
        ];
    }

    private function adminRedirect(ResponseInterface $response, string $kind, string $message): ResponseInterface
    {
        $basePath = $this->config['base_path'] ?? '';
        return $response
            ->withHeader('Location', $basePath . '/admin/report-mailings?' . $kind . '=' . urlencode($message))
            ->withStatus(302);
    }

    private function shortError(\Throwable $error): string
    {
        $message = $error->getMessage();
        if (preg_match('/ORA-\d+:\s*([^\n]+)/u', $message, $match)) {
            return trim($match[1]);
        }
        return $message !== '' ? $message : 'операция не выполнена';
    }
}
