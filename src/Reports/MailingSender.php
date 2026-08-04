<?php
declare(strict_types=1);

namespace App\Reports;

use App\Database\DbInterface;

final class MailingSender
{
    private DbInterface $db;
    private MailingData $mailings;
    private ReportBuilder $reports;
    private string $directory;
    private bool $testMode;

    public function __construct(DbInterface $db, string $directory, bool $testMode = false)
    {
        $this->db = $db;
        $this->mailings = new MailingData($db);
        $this->reports = new ReportBuilder($db);
        $this->directory = $directory;
        $this->testMode = $testMode;
    }

    public function run(int $limit = 20): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($this->mailings->pending($limit) as $mailing) {
            if (!$this->mailings->startRun((int) $mailing['run_id'])) {
                continue;
            }
            $status = $this->process($mailing);
            if ($status === 'SENT') $result['sent']++;
            elseif ($status === 'SKIPPED') $result['skipped']++;
            else $result['errors']++;
        }
        return $result;
    }

    public function runAll(): array
    {
        $total = ['sent' => 0, 'skipped' => 0, 'errors' => 0];
        do {
            $result = $this->run(100);
            foreach ($total as $key => $value) {
                $total[$key] += $result[$key];
            }
            $processed = array_sum($result);
        } while ($processed === 100);

        return $total;
    }

    private function process(array $mailing): string
    {
        $filePath = null;
        $reportDt = null;
        $rowsCount = 0;
        $status = 'ERROR';
        $errorMessage = null;

        try {
            $report = $this->reports->build($mailing);
            $reportDt = $report['report_dt'];
            $rowsCount = (int) $report['row_count'];

            if (!empty($mailing['skip_empty']) && $rowsCount === 0) {
                $status = 'SKIPPED';
                $errorMessage = 'Отчёт не содержит строк';
                return $this->complete($mailing, $status, $reportDt, 0, null, $errorMessage);
            }

            $filePath = $this->reports->createFile($report, $this->directory);
            $values = [
                '{report_date}' => $this->dateLabel($reportDt),
                '{organization}' => (string) ($mailing['organization_short_name'] ?: ($mailing['organization_name'] ?? '')),
            ];
            $subject = strtr((string) ($mailing['subject_text'] ?: $mailing['name']), $values);
            $body = strtr((string) ($mailing['body_text'] ?: 'Во вложении отчёт.'), $values);
            $this->sendMail($mailing['recipients'], $subject, $body, $filePath);
            $status = 'SENT';
            return $this->complete($mailing, $status, $reportDt, $rowsCount, basename($filePath), null);
        } catch (\Throwable $error) {
            $errorMessage = mb_substr(preg_replace('/\s+/', ' ', $error->getMessage()) ?: 'Ошибка формирования отчёта', 0, 1900);
            return $this->complete($mailing, 'ERROR', $reportDt, $rowsCount, $filePath ? basename($filePath) : null, $errorMessage);
        } finally {
            if ($filePath !== null && is_file($filePath)) {
                @unlink($filePath);
            }
        }
    }

    private function complete(
        array $mailing,
        string $status,
        ?string $reportDt,
        int $rowsCount,
        ?string $fileName,
        ?string $errorMessage
    ): string {
        $mailing['is_active'] = (int) ($mailing['is_active'] ?? 0);
        $nextRunAt = MailingData::nextRun($mailing);
        $this->mailings->finishRun(
            (int) $mailing['run_id'],
            (int) $mailing['mailing_id'],
            $status,
            $reportDt,
            $rowsCount,
            $fileName,
            $errorMessage,
            $nextRunAt
        );
        return $status;
    }

    private function dateLabel(?string $value): string
    {
        if ($value === null) {
            return date('d.m.Y');
        }
        try {
            return (new \DateTime($value))->format('d.m.Y H:i');
        } catch (\Throwable $error) {
            return $value;
        }
    }

    private function sendMail(array $recipients, string $subject, string $body, string $filePath): void
    {
        if (!$recipients) {
            throw new \RuntimeException('Не указан получатель письма');
        }
        if (!is_file($filePath)) {
            throw new \RuntimeException('Не удалось прочитать файл отчёта');
        }

        if ($this->testMode) {
            $this->saveTestMail($recipients, $subject, $body, $filePath);
            return;
        }

        /*
        $this->db->execute(
            'BEGIN package_name.procedure_name(...); END;',
            []
        );
        */

        throw new \RuntimeException('Отправка через пакет Oracle пока не настроена');
    }

    private function saveTestMail(array $recipients, string $subject, string $body, string $filePath): void
    {
        $directory = rtrim($this->directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'test'
            . DIRECTORY_SEPARATOR . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(3));
        if (!mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Не удалось создать папку тестового письма');
        }

        $fileName = basename($filePath);
        if (!copy($filePath, $directory . DIRECTORY_SEPARATOR . $fileName)) {
            throw new \RuntimeException('Не удалось сохранить вложение тестового письма');
        }

        $message = [
            'recipients' => $recipients,
            'subject' => $subject,
            'body' => $body,
            'attachment' => $fileName,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $json = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($directory . DIRECTORY_SEPARATOR . 'message.json', $json) === false) {
            throw new \RuntimeException('Не удалось сохранить данные тестового письма');
        }
    }
}
