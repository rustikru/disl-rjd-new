<?php
declare(strict_types=1);

namespace App\Reports;

use App\Database\DbInterface;

final class MailingWorker
{
    private MailingStore $store;
    private ReportBuilder $reports;
    private string $from;
    private string $directory;

    public function __construct(DbInterface $db, string $from, string $directory)
    {
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Не настроен адрес отправителя отчётов');
        }
        $this->store = new MailingStore($db);
        $this->reports = new ReportBuilder($db);
        $this->from = $from;
        $this->directory = $directory;
    }

    public function run(int $limit = 20): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'errors' => 0];
        foreach ($this->store->pending($limit) as $mailing) {
            if (!$this->store->startRun((int) $mailing['run_id'])) {
                continue;
            }
            $status = $this->process($mailing);
            if ($status === 'SENT') $result['sent']++;
            elseif ($status === 'SKIPPED') $result['skipped']++;
            else $result['errors']++;
        }
        return $result;
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

            $filePath = $this->reports->createFile($report, (string) $mailing['file_format'], $this->directory);
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
        $nextRunAt = MailingStore::nextRun($mailing);
        $this->store->finishRun(
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
        $to = $this->emails($recipients, 'TO');
        if (!$to) {
            throw new \RuntimeException('Не указан получатель письма');
        }
        $boundary = 'rjd-' . bin2hex(random_bytes(12));
        $headers = [
            'From: ' . $this->from,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];
        $cc = $this->emails($recipients, 'CC');
        $bcc = $this->emails($recipients, 'BCC');
        if ($cc) $headers[] = 'Cc: ' . implode(', ', $cc);
        if ($bcc) $headers[] = 'Bcc: ' . implode(', ', $bcc);

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException('Не удалось прочитать файл отчёта');
        }
        $message = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $body . "\r\n"
            . '--' . $boundary . "\r\n"
            . 'Content-Type: application/octet-stream; name="' . basename($filePath) . "\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . 'Content-Disposition: attachment; filename="' . basename($filePath) . "\"\r\n\r\n"
            . chunk_split(base64_encode($contents))
            . '--' . $boundary . "--\r\n";
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        if (!mail(implode(', ', $to), $encodedSubject, $message, implode("\r\n", $headers))) {
            throw new \RuntimeException('Почтовая система не приняла письмо');
        }
    }

    private function emails(array $recipients, string $type): array
    {
        return array_values(array_filter(array_map(
            static fn(array $recipient): ?string => strtoupper((string) ($recipient['send_type'] ?? 'TO')) === $type
                ? (string) ($recipient['email'] ?? '')
                : null,
            $recipients
        )));
    }
}
