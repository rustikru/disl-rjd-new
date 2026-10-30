<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;
use App\Reports\ReportConfig;
use DateTimeInterface;

final class MailingService
{
    public function __construct(private DbInterface $db)
    {
    }

    public function userMailingCount(int $userId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM xx_rjd_user_mailings WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function userMailings(int $userId, int $page = 1, int $perPage = 25): array
    {
        $perPage = max(1, min($perPage, 100));
        $offset = (max(1, $page) - 1) * $perPage;
        $mailings = $this->db->page(
            "SELECT m.id, m.organization_id, m.name, m.recipients, m.subject, m.message_body, m.file_format,
                    m.schedule_type, m.send_time, m.week_days, m.month_day, m.is_active,
                    TO_CHAR(m.last_run_at, 'DD.MM.YYYY HH24:MI') AS last_run_at,
                    m.last_run_status, m.last_run_path, m.last_run_message,
                    o.short_name AS organization_short_name, o.name AS organization_name
               FROM xx_rjd_user_mailings m
               JOIN xx_rjd_organizations o ON o.id = m.organization_id
              WHERE m.user_id = :user_id
              ORDER BY m.is_active DESC, m.updated_at DESC, m.id DESC",
            ['user_id' => $userId],
            $offset,
            $perPage
        );

        foreach ($mailings as &$mailing) {
            $mailing['items'] = $this->mailingItems((int) $mailing['id']);
        }
        unset($mailing);
        return $mailings;
    }

    public function findForUser(int $mailingId, int $userId): ?array
    {
        $mailing = $this->db->fetchOne(
            'SELECT * FROM xx_rjd_user_mailings WHERE id = :id AND user_id = :user_id',
            ['id' => $mailingId, 'user_id' => $userId]
        );
        if (!$mailing) {
            return null;
        }
        $mailing['items'] = $this->mailingItems($mailingId);
        return $mailing;
    }

    public function find(int $mailingId): ?array
    {
        $mailing = $this->db->fetchOne(
            'SELECT * FROM xx_rjd_user_mailings WHERE id = :id',
            ['id' => $mailingId]
        );
        if (!$mailing) {
            return null;
        }
        $mailing['items'] = $this->mailingItems($mailingId);
        return $mailing;
    }

    /**
     * Возвращает активные рассылки, которые нужно запустить в указанную минуту.
     * Если выбранного дня нет в месяце, используется последний день месяца.
     */
    public function dueMailings(DateTimeInterface $dateTime): array
    {
        $mailings = $this->db->fetchAll(
            "SELECT m.*
               FROM xx_rjd_user_mailings m
              WHERE m.is_active = 1
                AND m.send_time = :send_time
                AND (m.last_run_at IS NULL
                     OR TRUNC(m.last_run_at) < TO_DATE(:run_date, 'YYYY-MM-DD'))
              ORDER BY m.id",
            [
                'send_time' => $dateTime->format('H:i'),
                'run_date' => $dateTime->format('Y-m-d'),
            ]
        );

        $dueMailings = [];
        foreach ($mailings as $mailing) {
            if (!$this->isDueOnDate($mailing, $dateTime)) {
                continue;
            }
            $mailing['items'] = $this->mailingItems((int) $mailing['id']);
            $dueMailings[] = $mailing;
        }
        return $dueMailings;
    }

    public function isDueOnDate(array $mailing, DateTimeInterface $dateTime): bool
    {
        $scheduleType = (string) ($mailing['schedule_type'] ?? '');
        if ($scheduleType === 'daily') {
            return true;
        }
        if ($scheduleType === 'weekly') {
            $weekDays = array_map('intval', array_filter(explode(',', (string) ($mailing['week_days'] ?? ''))));
            return in_array((int) $dateTime->format('N'), $weekDays, true);
        }
        if ($scheduleType !== 'monthly') {
            return false;
        }

        $selectedDay = max(1, min(31, (int) ($mailing['month_day'] ?? 1)));
        $lastDayOfMonth = (int) $dateTime->format('t');
        $runDay = min($selectedDay, $lastDayOfMonth);
        return (int) $dateTime->format('j') === $runDay;
    }

    public function setActiveByAdmin(int $mailingId, bool $active): bool
    {
        return $this->db->execute(
            'UPDATE xx_rjd_user_mailings SET is_active = :is_active, updated_at = SYSDATE WHERE id = :id',
            ['is_active' => $active ? 1 : 0, 'id' => $mailingId]
        ) > 0;
    }

    public function save(array $form, int $userId, int $organizationId): int
    {
        $mailingId = (int) ($form['id'] ?? 0);
        $name = trim((string) ($form['name'] ?? ''));
        $recipients = $this->recipients((string) ($form['recipients'] ?? ''));
        $subject = trim((string) ($form['subject'] ?? ''));
        $messageBody = trim((string) ($form['message_body'] ?? ''));
        $fileFormat = 'xlsx';
        $scheduleType = (string) ($form['schedule_type'] ?? 'daily');
        $sendTime = (string) ($form['send_time'] ?? '08:00');
        $weekDays = array_values(array_unique(array_map('intval', (array) ($form['week_days'] ?? []))));
        sort($weekDays);
        $monthDay = (int) ($form['month_day'] ?? 1);
        $reportForms = (array) ($form['reports'] ?? []);

        if ($name === '') {
            throw new \InvalidArgumentException('Укажите название рассылки');
        }
        if (!$recipients) {
            throw new \InvalidArgumentException('Укажите хотя бы одного получателя');
        }
        if (!in_array($scheduleType, ['daily', 'weekly', 'monthly'], true)) {
            throw new \InvalidArgumentException('Выберите периодичность');
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $sendTime)) {
            throw new \InvalidArgumentException('Укажите корректное время отправки');
        }
        if ($scheduleType === 'weekly' && (!$weekDays || array_diff($weekDays, range(1, 7)))) {
            throw new \InvalidArgumentException('Выберите дни недели');
        }
        if ($scheduleType === 'monthly' && ($monthDay < 1 || $monthDay > 31)) {
            throw new \InvalidArgumentException('Укажите день месяца от 1 до 31');
        }

        $mailingDefinitions = ReportConfig::forContext('mailing');
        $mailingItems = [];
        foreach ($reportForms as $reportCode => $reportForm) {
            if (!isset($mailingDefinitions[$reportCode])) {
                continue;
            }
            $submittedFilters = (array) ($reportForm['filters'] ?? []);
            $filters = [];
            foreach ($mailingDefinitions[$reportCode]['filters'] as $filterDefinition) {
                $filterValue = trim((string) ($submittedFilters[$filterDefinition['key']] ?? ''));
                if ($filterDefinition['required'] && $filterValue === '') {
                    throw new \InvalidArgumentException(
                        'Заполните «' . $filterDefinition['label'] . '» в отчёте «'
                        . $mailingDefinitions[$reportCode]['name'] . '»'
                    );
                }
                if ($filterValue !== '') {
                    $filters[$filterDefinition['key']] = $filterValue;
                }
            }
            $mailingItems[] = ['report_code' => $reportCode, 'filters' => $filters];
        }
        if (!$mailingItems) {
            throw new \InvalidArgumentException('Добавьте отчёт в рассылку');
        }

        $this->db->beginTransaction();
        try {
            $mailingValues = [
                'organization_id' => $organizationId,
                'name' => mb_substr($name, 0, 150),
                'recipients' => implode('; ', $recipients),
                'subject' => $subject !== '' ? mb_substr($subject, 0, 250) : null,
                'message_body' => $messageBody !== '' ? mb_substr($messageBody, 0, 4000) : null,
                'file_format' => $fileFormat,
                'schedule_type' => $scheduleType,
                'send_time' => $sendTime,
                'week_days' => $scheduleType === 'weekly' ? implode(',', $weekDays) : null,
                'month_day' => $scheduleType === 'monthly' ? $monthDay : null,
            ];
            if ($mailingId > 0) {
                $updated = $this->db->execute(
                    'UPDATE xx_rjd_user_mailings
                        SET organization_id = :organization_id,
                            name = :name,
                            recipients = :recipients,
                            subject = :subject,
                            message_body = :message_body,
                            file_format = :file_format,
                            schedule_type = :schedule_type,
                            send_time = :send_time,
                            week_days = :week_days,
                            month_day = :month_day,
                            updated_at = SYSDATE
                      WHERE id = :id AND user_id = :user_id',
                    $mailingValues + ['id' => $mailingId, 'user_id' => $userId]
                );
                if ($updated === 0) {
                    throw new \InvalidArgumentException('Рассылка не найдена');
                }
                $this->db->execute(
                    'DELETE FROM xx_rjd_user_mailing_reports WHERE mailing_id = :mailing_id',
                    ['mailing_id' => $mailingId]
                );
            } else {
                $this->db->execute(
                    'INSERT INTO xx_rjd_user_mailings
                    (user_id, organization_id, name, recipients, subject, message_body, file_format,
                     schedule_type, send_time, week_days, month_day, is_active)
                 VALUES
                    (:user_id, :organization_id, :name, :recipients, :subject, :message_body, :file_format,
                     :schedule_type, :send_time, :week_days, :month_day, 1)',
                    $mailingValues + ['user_id' => $userId]
                );
                $identifier = $this->db->fetchOne('SELECT xx_rjd_user_mailings_seq.CURRVAL AS id FROM dual');
                $mailingId = (int) ($identifier['id'] ?? 0);
            }
            foreach ($mailingItems as $position => $mailingItem) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_user_mailing_reports
                        (mailing_id, report_code, position, filters_json)
                     VALUES (:mailing_id, :report_code, :position, :filters_json)',
                    [
                        'mailing_id' => $mailingId,
                        'report_code' => $mailingItem['report_code'],
                        'position' => $position,
                        'filters_json' => json_encode($mailingItem['filters'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]
                );
            }
            $this->db->commit();
            return $mailingId;
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    public function setActive(int $mailingId, int $userId, bool $active): bool
    {
        return $this->db->execute(
            'UPDATE xx_rjd_user_mailings
                SET is_active = :is_active, updated_at = SYSDATE
              WHERE id = :id AND user_id = :user_id',
            ['is_active' => $active ? 1 : 0, 'id' => $mailingId, 'user_id' => $userId]
        ) > 0;
    }

    public function delete(int $mailingId, int $userId): bool
    {
        return $this->db->execute(
            'DELETE FROM xx_rjd_user_mailings WHERE id = :id AND user_id = :user_id',
            ['id' => $mailingId, 'user_id' => $userId]
        ) > 0;
    }

    public function recordRun(int $mailingId, string $status, ?string $path, string $message): void
    {
        $this->db->execute(
            "UPDATE xx_rjd_user_mailings
                SET last_run_at = TO_DATE(:run_at, 'YYYY-MM-DD HH24:MI:SS'),
                    last_run_status = :status,
                    last_run_path = :run_path,
                    last_run_message = :run_message,
                    updated_at = SYSDATE
              WHERE id = :id",
            [
                'status' => mb_substr($status, 0, 20),
                'run_at' => date('Y-m-d H:i:s'),
                'run_path' => $path !== null ? mb_substr($path, 0, 1000) : null,
                'run_message' => mb_substr($message, 0, 1000),
                'id' => $mailingId,
            ]
        );
    }

    public function startRun(int $mailingId, string $mode, string $recipients): int
    {
        $this->db->execute(
            "INSERT INTO xx_rjd_user_mailing_runs (mailing_id, started_at, status, run_mode, recipients)
             VALUES (:mailing_id, TO_DATE(:started_at, 'YYYY-MM-DD HH24:MI:SS'), :status, :run_mode, :recipients)",
            [
                'mailing_id' => $mailingId,
                'started_at' => date('Y-m-d H:i:s'),
                'status' => 'running',
                'run_mode' => mb_substr($mode, 0, 20),
                'recipients' => mb_substr($recipients, 0, 2000),
            ]
        );
        $identifier = $this->db->fetchOne('SELECT xx_rjd_user_mailing_run_seq.CURRVAL AS id FROM dual');
        return (int) ($identifier['id'] ?? 0);
    }
    // Завершить запись о запуске рассылки
    public function finishRun(int $runId, string $status, ?string $path, string $message): void
    {
        $this->db->execute(
            "UPDATE xx_rjd_user_mailing_runs
                SET finished_at = TO_DATE(:finished_at, 'YYYY-MM-DD HH24:MI:SS'),
                    status = :status,
                    result_path = :result_path,
                    message = :message
              WHERE id = :id",
            [
                'status' => $status,
                'finished_at' => date('Y-m-d H:i:s'),
                'result_path' => $path !== null ? mb_substr($path, 0, 1000) : null,
                'message' => mb_substr($message, 0, 1000),
                'id' => $runId,
            ]
        );
    }

    public function mailingCountsForAdmin(): array
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_total,
                    SUM(CASE WHEN is_active = 1 THEN 0 ELSE 1 END) AS disabled_total
               FROM xx_rjd_user_mailings'
        ) ?? [];
        return [
            'all' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active_total'] ?? 0),
            'disabled' => (int) ($row['disabled_total'] ?? 0),
        ];
    }
    // Получить список рассылок
    public function mailingsForAdmin(string $status = 'all', int $page = 1, int $perPage = 25): array
    {
        $where = $status === 'active'
            ? 'WHERE m.is_active = 1'
            : ($status === 'disabled' ? 'WHERE m.is_active <> 1' : '');
        $perPage = max(1, min($perPage, 100));
        $offset = (max(1, $page) - 1) * $perPage;
        return $this->db->page(
            "SELECT m.id, m.name, m.recipients, m.file_format, m.schedule_type,
                    m.send_time, m.week_days, m.month_day, m.is_active,
                    TO_CHAR(m.last_run_at, 'DD.MM.YYYY HH24:MI') AS last_run_at,
                    m.last_run_status, u.username, u.display_name,
                    o.name AS organization_name, o.short_name AS organization_short_name,
                    (SELECT LISTAGG(i.report_code, ', ') WITHIN GROUP (ORDER BY i.position, i.id)
                       FROM xx_rjd_user_mailing_reports i WHERE i.mailing_id = m.id) AS report_codes
               FROM xx_rjd_user_mailings m
               JOIN xx_rjd_users u ON u.id = m.user_id
               JOIN xx_rjd_organizations o ON o.id = m.organization_id
              $where
              ORDER BY m.is_active DESC, m.send_time, u.display_name, m.name",
            [],
            $offset,
            $perPage
        );
    }

    public function runJournalCountForAdmin(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS total FROM xx_rjd_user_mailing_runs');
        return (int) ($row['total'] ?? 0);
    }
    // Получить журнал запусков рассылок 
    public function runJournalForAdmin(int $page = 1, int $perPage = 25): array
    {
        $perPage = max(1, min($perPage, 100));
        $offset = (max(1, $page) - 1) * $perPage;
        return $this->db->page(
            "SELECT r.id, r.mailing_id,
                    TO_CHAR(r.started_at, 'DD.MM.YYYY HH24:MI:SS') AS started_at,
                    TO_CHAR(r.finished_at, 'DD.MM.YYYY HH24:MI:SS') AS finished_at,
                    r.status, r.run_mode, r.recipients, r.result_path, r.message,
                    m.name AS mailing_name, u.username, u.display_name,
                    o.name AS organization_name, o.short_name AS organization_short_name
               FROM xx_rjd_user_mailing_runs r
               JOIN xx_rjd_user_mailings m ON m.id = r.mailing_id
               JOIN xx_rjd_users u ON u.id = m.user_id
               JOIN xx_rjd_organizations o ON o.id = m.organization_id
              ORDER BY r.started_at DESC, r.id DESC",
            [],
            $offset,
            $perPage
        );
    }
    // Получить список элементов рассылки
    private function mailingItems(int $mailingId): array
    {
        $items = $this->db->fetchAll(
            'SELECT id, report_code, position, filters_json
               FROM xx_rjd_user_mailing_reports
              WHERE mailing_id = :mailing_id
              ORDER BY position, id',
            ['mailing_id' => $mailingId]
        );
        foreach ($items as &$item) {
            $item['filters'] = json_decode((string) ($item['filters_json'] ?? '{}'), true) ?: [];
            unset($item['filters_json']);
        }
        unset($item);
        return $items;
    }
    // Разделяет строку получателей на массив и проверяет корректность e-mail адресов
    private function recipients(string $recipientText): array
    {
        $recipients = preg_split('/[;,\r\n]+/', $recipientText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $recipients = array_values(array_unique(array_map('trim', $recipients)));
        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Некорректный e-mail: ' . $recipient);
            }
        }
        return $recipients;
    }
}
