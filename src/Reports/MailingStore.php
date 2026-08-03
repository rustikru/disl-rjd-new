<?php
declare(strict_types=1);

namespace App\Reports;

use App\Database\DbInterface;

final class MailingStore
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    public function listByUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT m.id, m.user_id, m.organization_id, m.name, m.report_code,
                    m.report_view, m.file_format, m.schedule_type, m.run_time,
                    m.week_days, m.month_day, m.skip_empty,
                    m.is_active, m.last_run_at, m.next_run_at, m.created_at,
                    o.name AS organization_name, o.short_name AS organization_short_name,
                    (SELECT COUNT(*) FROM xx_rjd_report_recipients r WHERE r.mailing_id = m.id) AS recipient_count,
                    (SELECT MAX(x.started_at) FROM xx_rjd_report_runs x WHERE x.mailing_id = m.id) AS last_attempt_at,
                    (SELECT MAX(x.status) KEEP (DENSE_RANK LAST ORDER BY x.started_at, x.id)
                       FROM xx_rjd_report_runs x WHERE x.mailing_id = m.id) AS last_status
               FROM xx_rjd_report_mailings m
               LEFT JOIN xx_rjd_organizations o ON o.id = m.organization_id
              WHERE m.user_id = :user_id
              ORDER BY m.is_active DESC, m.created_at DESC, m.id DESC",
            ['user_id' => $userId]
        );

        foreach ($rows as &$row) {
            $row['schedule_label'] = self::scheduleLabel($row);
        }
        unset($row);
        return $rows;
    }

    public function find(int $id, int $userId): ?array
    {
        $mailing = $this->db->fetchOne(
            "SELECT m.id, m.user_id, m.organization_id, m.name, m.report_code,
                    m.report_view, m.file_format,
                    DBMS_LOB.SUBSTR(m.filters_json, 4000, 1) AS filters_json,
                    m.subject_text, m.body_text, m.schedule_type, m.run_time,
                    m.week_days, m.month_day, m.skip_empty,
                    m.is_active, m.last_run_at, m.next_run_at, m.created_at, m.updated_at
               FROM xx_rjd_report_mailings m
              WHERE m.id = :id AND m.user_id = :user_id",
            ['id' => $id, 'user_id' => $userId]
        );
        if (!$mailing) {
            return null;
        }

        $mailing['filters'] = json_decode((string) ($mailing['filters_json'] ?? '{}'), true) ?: [];
        $mailing['recipients'] = $this->db->fetchAll(
            'SELECT id, email, send_type
               FROM xx_rjd_report_recipients
              WHERE mailing_id = :mailing_id
              ORDER BY send_type, id',
            ['mailing_id' => $id]
        );
        return $mailing;
    }

    public function save(array $mailing, array $recipients): int
    {
        $id = (int) ($mailing['id'] ?? 0);
        $params = [
            'user_id' => (int) $mailing['user_id'],
            'organization_id' => $mailing['organization_id'],
            'name' => (string) $mailing['name'],
            'report_code' => (string) $mailing['report_code'],
            'report_view' => (string) $mailing['report_view'],
            'file_format' => (string) $mailing['file_format'],
            'filters_json' => json_encode($mailing['filters'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'subject_text' => $mailing['subject_text'],
            'body_text' => $mailing['body_text'],
            'schedule_type' => (string) $mailing['schedule_type'],
            'run_time' => (string) $mailing['run_time'],
            'week_days' => $mailing['week_days'],
            'month_day' => $mailing['month_day'],
            'skip_empty' => (int) $mailing['skip_empty'],
            'is_active' => (int) $mailing['is_active'],
            'next_run_at' => $mailing['next_run_at'],
        ];

        $this->db->beginTransaction();
        try {
            if ($id > 0) {
                $params['id'] = $id;
                $this->db->execute(
                    "UPDATE xx_rjd_report_mailings
                        SET organization_id = :organization_id,
                            name = :name,
                            report_code = :report_code,
                            report_view = :report_view,
                            file_format = :file_format,
                            filters_json = :filters_json,
                            subject_text = :subject_text,
                            body_text = :body_text,
                            schedule_type = :schedule_type,
                            run_time = :run_time,
                            week_days = :week_days,
                            month_day = :month_day,
                            skip_empty = :skip_empty,
                            is_active = :is_active,
                            next_run_at = TO_DATE(:next_run_at, 'YYYY-MM-DD HH24:MI:SS'),
                            updated_at = SYSDATE
                      WHERE id = :id AND user_id = :user_id",
                    $params
                );
            } else {
                $this->db->execute(
                    "INSERT INTO xx_rjd_report_mailings (
                        user_id, organization_id, name, report_code, report_view,
                        file_format, filters_json, subject_text, body_text,
                        schedule_type, run_time, week_days, month_day,
                        skip_empty, is_active, next_run_at
                    ) VALUES (
                        :user_id, :organization_id, :name, :report_code, :report_view,
                        :file_format, :filters_json, :subject_text, :body_text,
                        :schedule_type, :run_time, :week_days, :month_day,
                        :skip_empty, :is_active,
                        TO_DATE(:next_run_at, 'YYYY-MM-DD HH24:MI:SS')
                    )",
                    $params
                );
                $row = $this->db->fetchOne('SELECT xx_rjd_report_mailings_seq.CURRVAL AS id FROM dual');
                $id = (int) ($row['id'] ?? 0);
            }

            $this->db->execute(
                'DELETE FROM xx_rjd_report_recipients WHERE mailing_id = :mailing_id',
                ['mailing_id' => $id]
            );
            foreach ($recipients as $recipient) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_report_recipients (mailing_id, email, send_type)
                     VALUES (:mailing_id, :email, :send_type)',
                    [
                        'mailing_id' => $id,
                        'email' => $recipient['email'],
                        'send_type' => $recipient['send_type'],
                    ]
                );
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    public function setActive(int $id, int $userId, bool $active, ?string $nextRunAt): bool
    {
        return $this->db->execute(
            "UPDATE xx_rjd_report_mailings
                SET is_active = :is_active,
                    next_run_at = TO_DATE(:next_run_at, 'YYYY-MM-DD HH24:MI:SS'),
                    updated_at = SYSDATE
              WHERE id = :id AND user_id = :user_id",
            [
                'is_active' => $active ? 1 : 0,
                'next_run_at' => $nextRunAt,
                'id' => $id,
                'user_id' => $userId,
            ]
        ) > 0;
    }

    public function delete(int $id, int $userId): bool
    {
        return $this->db->execute(
            'DELETE FROM xx_rjd_report_mailings WHERE id = :id AND user_id = :user_id',
            ['id' => $id, 'user_id' => $userId]
        ) > 0;
    }

    public function queue(int $id, int $userId): bool
    {
        $mailing = $this->find($id, $userId);
        if (!$mailing) {
            return false;
        }
        try {
            $this->db->execute(
                "INSERT INTO xx_rjd_report_runs (mailing_id, status)
                 VALUES (:mailing_id, 'PENDING')",
                ['mailing_id' => $id]
            );
        } catch (\Throwable $error) {
            if (!str_contains($error->getMessage(), 'ORA-00001')) {
                throw $error;
            }
        }
        return true;
    }

    public function recentRuns(int $userId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM (
                SELECT r.id, r.mailing_id, m.name AS mailing_name, r.started_at,
                       r.finished_at, r.status, r.report_dt, r.rows_count,
                       r.file_name, r.error_message
                  FROM xx_rjd_report_runs r
                  JOIN xx_rjd_report_mailings m ON m.id = r.mailing_id
                 WHERE m.user_id = :user_id
                 ORDER BY r.started_at DESC, r.id DESC
            ) WHERE ROWNUM <= " . $limit,
            ['user_id' => $userId]
        );
    }

    public function queueDue(): int
    {
        $due = $this->db->fetchAll(
            "SELECT m.id
               FROM xx_rjd_report_mailings m
              WHERE m.is_active = 1
                AND m.schedule_type != 'MANUAL'
                AND m.next_run_at IS NOT NULL
                AND m.next_run_at <= SYSDATE
                AND NOT EXISTS (
                    SELECT 1 FROM xx_rjd_report_runs r
                     WHERE r.mailing_id = m.id
                       AND r.status IN ('PENDING', 'RUNNING')
                )"
        );
        foreach ($due as $row) {
            try {
                $this->db->execute(
                    "INSERT INTO xx_rjd_report_runs (mailing_id, status)
                     VALUES (:mailing_id, 'PENDING')",
                    ['mailing_id' => (int) $row['id']]
                );
            } catch (\Throwable $error) {
                if (!str_contains($error->getMessage(), 'ORA-00001')) {
                    throw $error;
                }
            }
        }
        return count($due);
    }

    public function pending(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->db->fetchAll(
            "SELECT * FROM (
                SELECT r.id AS run_id, r.mailing_id,
                       m.user_id, m.organization_id, m.name, m.report_code,
                       m.report_view, m.file_format,
                       DBMS_LOB.SUBSTR(m.filters_json, 4000, 1) AS filters_json,
                       m.subject_text, m.body_text, m.schedule_type, m.run_time,
                       m.week_days, m.month_day, m.skip_empty,
                       m.is_active, m.last_run_at, m.next_run_at,
                       o.name AS organization_name, o.short_name AS organization_short_name
                  FROM xx_rjd_report_runs r
                  JOIN xx_rjd_report_mailings m ON m.id = r.mailing_id
                  LEFT JOIN xx_rjd_organizations o ON o.id = m.organization_id
                 WHERE r.status = 'PENDING'
                 ORDER BY r.started_at, r.id
            ) WHERE ROWNUM <= " . $limit
        );
        foreach ($rows as &$row) {
            $row['filters'] = json_decode((string) ($row['filters_json'] ?? '{}'), true) ?: [];
            $row['recipients'] = $this->db->fetchAll(
                'SELECT email, send_type FROM xx_rjd_report_recipients
                  WHERE mailing_id = :mailing_id ORDER BY send_type, id',
                ['mailing_id' => (int) $row['mailing_id']]
            );
        }
        unset($row);
        return $rows;
    }

    public function startRun(int $runId): bool
    {
        return $this->db->execute(
            "UPDATE xx_rjd_report_runs
                SET status = 'RUNNING', started_at = SYSDATE
              WHERE id = :id AND status = 'PENDING'",
            ['id' => $runId]
        ) === 1;
    }

    public function finishRun(
        int $runId,
        int $mailingId,
        string $status,
        ?string $reportDt,
        int $rowsCount,
        ?string $fileName,
        ?string $errorMessage,
        ?string $nextRunAt
    ): void {
        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE xx_rjd_report_runs
                    SET status = :status,
                        finished_at = SYSDATE,
                        report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS'),
                        rows_count = :rows_count,
                        file_name = :file_name,
                        error_message = :error_message
                  WHERE id = :id",
                [
                    'status' => $status,
                    'report_dt' => $this->dateValue($reportDt),
                    'rows_count' => $rowsCount,
                    'file_name' => $fileName,
                    'error_message' => $errorMessage,
                    'id' => $runId,
                ]
            );
            $this->db->execute(
                "UPDATE xx_rjd_report_mailings
                    SET last_run_at = SYSDATE,
                        next_run_at = TO_DATE(:next_run_at, 'YYYY-MM-DD HH24:MI:SS'),
                        updated_at = SYSDATE
                  WHERE id = :id",
                ['next_run_at' => $nextRunAt, 'id' => $mailingId]
            );
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function dateValue(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return (new \DateTime($value))->format('Y-m-d H:i:s');
        } catch (\Throwable $error) {
            return null;
        }
    }

    public static function nextRun(array $mailing, ?\DateTimeImmutable $now = null): ?string
    {
        if (empty($mailing['is_active'])) {
            return null;
        }

        $type = strtoupper((string) ($mailing['schedule_type'] ?? 'MANUAL'));
        if ($type === 'MANUAL') {
            return null;
        }

        $now = $now ?? new \DateTimeImmutable('now');
        [$hour, $minute] = self::timeParts((string) ($mailing['run_time'] ?? '08:00'));
        $candidate = $now->setTime($hour, $minute, 0);

        if ($type === 'DAILY') {
            if ($candidate <= $now) {
                $candidate = $candidate->modify('+1 day');
            }
            return $candidate->format('Y-m-d H:i:s');
        }

        if ($type === 'WEEKLY') {
            $days = self::weekDayNumbers((string) ($mailing['week_days'] ?? ''));
            if (!$days) {
                return null;
            }
            for ($offset = 0; $offset <= 7; $offset++) {
                $date = $candidate->modify('+' . $offset . ' day');
                if (in_array((int) $date->format('N'), $days, true) && $date > $now) {
                    return $date->format('Y-m-d H:i:s');
                }
            }
            return null;
        }

        if ($type === 'MONTHLY') {
            $day = max(1, min(31, (int) ($mailing['month_day'] ?? 1)));
            for ($monthOffset = 0; $monthOffset <= 2; $monthOffset++) {
                $month = $now->modify('first day of +' . $monthOffset . ' month');
                $date = $month->setDate(
                    (int) $month->format('Y'),
                    (int) $month->format('m'),
                    min($day, (int) $month->format('t'))
                )->setTime($hour, $minute, 0);
                if ($date > $now) {
                    return $date->format('Y-m-d H:i:s');
                }
            }
        }

        return null;
    }

    public static function scheduleLabel(array $mailing): string
    {
        $type = strtoupper((string) ($mailing['schedule_type'] ?? 'MANUAL'));
        $time = (string) ($mailing['run_time'] ?? '08:00');
        if ($type === 'DAILY') {
            return 'Ежедневно в ' . $time;
        }
        if ($type === 'WEEKLY') {
            $names = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
            $days = array_map(
                static fn(int $day): string => $names[$day] ?? '',
                self::weekDayNumbers((string) ($mailing['week_days'] ?? ''))
            );
            return implode(', ', array_filter($days)) . ' в ' . $time;
        }
        if ($type === 'MONTHLY') {
            return (int) ($mailing['month_day'] ?? 1) . '-го числа в ' . $time;
        }
        return 'Только вручную';
    }

    private static function timeParts(string $time): array
    {
        if (!preg_match('/^(\d{2}):(\d{2})$/', $time, $match)) {
            return [8, 0];
        }
        return [max(0, min(23, (int) $match[1])), max(0, min(59, (int) $match[2]))];
    }

    private static function weekDayNumbers(string $value): array
    {
        $days = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $value)),
            static fn(int $day): bool => $day >= 1 && $day <= 7
        )));
        sort($days);
        return $days;
    }
}
