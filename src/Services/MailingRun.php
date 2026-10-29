<?php
declare(strict_types=1);

namespace App\Services;

use App\Controllers\ApiController;
use App\Database\DbInterface;
use App\Excel\ExcelStyle;
use App\Reports\ReportConfig;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class MailingRun
{
    private const DEFAULT_MESSAGE_BODY = 'Во вложении находятся сформированные отчёты.';

    public function __construct(private DbInterface $db, private array $config)
    {
    }

    public function run(array $mailing): array
    {
        $memoryLimit = trim((string) ($this->config['report_mail_memory_limit'] ?? ''));
        if ($memoryLimit !== '') {
            ini_set('memory_limit', $memoryLimit);
        }
        if (empty($this->config['report_mail_enabled'])) {
            throw new \RuntimeException('Формирование рассылок отключено в конфигурации');
        }
        $mode = (string) $this->config['mail_mode'];
        if (!in_array($mode, ['file', 'oracle'], true)) {
            throw new \RuntimeException('Режим mail_mode должен быть file или oracle');
        }

        $storageDirectory = rtrim((string) ($this->config['report_storage_dir'] ?? ''), DIRECTORY_SEPARATOR);
        if ($storageDirectory === '') {
            throw new \RuntimeException('Не указана папка report_storage_dir');
        }
        $runDirectory = $storageDirectory . DIRECTORY_SEPARATOR . 'mailing-' . (int) $mailing['id']
            . DIRECTORY_SEPARATOR . date('Y-m-d_H-i-s');
        if (!is_dir($runDirectory) && !mkdir($runDirectory, 0770, true) && !is_dir($runDirectory)) {
            throw new \RuntimeException('Не удалось создать папку результата: ' . $runDirectory);
        }

        $reportDefinitions = ReportConfig::forContext('mailing');
        $organizationService = new Organizations($this->db, (int) $mailing['organization_id']);
        $organizationService->sync();
        $apiController = new ApiController($this->db, $organizationService);
        $requestFactory = new ServerRequestFactory();
        $responseFactory = new ResponseFactory();
        $createdFiles = [];
        $reportSummaries = [];

        foreach ($mailing['items'] as $position => $mailingItem) {
            $reportCode = (string) $mailingItem['report_code'];
            $reportDefinition = $reportDefinitions[$reportCode] ?? null;
            if (!$reportDefinition || empty($reportDefinition['mailing_handler'])) {
                throw new \RuntimeException('Отчёт недоступен для рассылки: ' . $reportCode);
            }

            $columns = $reportDefinition['detail_columns'] ?? [];
            $queryParameters = (array) ($mailingItem['filters'] ?? []);
            $queryParameters['fields'] = implode(',', array_column($columns, 'key'));
            if (!empty($reportDefinition['detail_sort'])) {
                $sortDefinitions = is_array($reportDefinition['detail_sort'])
                    && isset($reportDefinition['detail_sort']['field'])
                    ? [$reportDefinition['detail_sort']]
                    : $reportDefinition['detail_sort'];
                $queryParameters['sort'] = implode(',', array_column($sortDefinitions, 'field'));
                $queryParameters['sort_dir'] = implode(',', array_map(
                    static fn(array $sortDefinition): string => (string) ($sortDefinition['dir'] ?? 'asc'),
                    $sortDefinitions
                ));
            }

            $request = $requestFactory->createServerRequest('GET', '/mailing/' . $reportCode)
                ->withQueryParams($queryParameters);
            $response = $responseFactory->createResponse();
            $method = (string) $reportDefinition['mailing_handler'];
            $reportResponse = $apiController->{$method}($request, $response);
            $payload = json_decode((string) $reportResponse->getBody(), true);
            if ($reportResponse->getStatusCode() >= 400 || !is_array($payload)) {
                throw new \RuntimeException(
                    (string) ($payload['error'] ?? ('Не удалось сформировать отчёт «' . $reportDefinition['name'] . '»'))
                );
            }
            $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
            $fileBase = sprintf('%02d_%s', $position + 1, $this->safeFileName($reportDefinition['name']));
            $filePath = $runDirectory . DIRECTORY_SEPARATOR . $fileBase . '.xlsx';
            $this->writeXlsx($filePath, $columns, $rows, $reportDefinition['name']);
            $createdFiles[] = $filePath;
            $reportSummaries[] = [
                'report_code' => $reportCode,
                'report_name' => $reportDefinition['name'],
                'filters' => $mailingItem['filters'] ?? [],
                'rows' => count($rows),
                'file' => basename($filePath),
            ];
        }

        $manifest = [
            'mailing_id' => (int) $mailing['id'],
            'mailing_name' => (string) $mailing['name'],
            'created_at' => date(DATE_ATOM),
            'mode' => $mode,
            'recipients' => preg_split('/\s*;\s*/', (string) $mailing['recipients'], -1, PREG_SPLIT_NO_EMPTY),
            'subject' => (string) ($mailing['subject'] ?? ''),
            'message_body' => (string) ($mailing['message_body'] ?? ''),
            'organization_id' => (int) $mailing['organization_id'],
            'reports' => $reportSummaries,
        ];
        $manifestPath = $runDirectory . DIRECTORY_SEPARATOR . 'mailing.json';
        if (file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ) === false) {
            throw new \RuntimeException('Не удалось сохранить описание запуска');
        }
        $createdFiles[] = $manifestPath;

        $message = 'Файлы сформированы';
        if ($mode === 'oracle') {
            $recipients = preg_split('/\s*;\s*/', (string) $mailing['recipients'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $subject = trim((string) ($mailing['subject'] ?? ''));
            $messageBody = trim((string) ($mailing['message_body'] ?? ''));
            $messageBody = $messageBody !== '' ? $messageBody : self::DEFAULT_MESSAGE_BODY;
            $messageBody = nl2br(htmlspecialchars($messageBody, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
            (new OracleMail($this->db, $this->config))->send(
                $recipients,
                $subject !== '' ? $subject : (string) $mailing['name'],
                $messageBody,
                array_values(array_filter($createdFiles, static fn(string $file): bool => str_ends_with($file, '.xlsx')))
            );
            $message = 'Письма отправлены';
        }

        $resultDirectory = $runDirectory;
        if ($mode === 'oracle') {
            if ($this->removeFiles($createdFiles, $runDirectory)) {
                $createdFiles = [];
                $resultDirectory = null;
            }
        }

        return [
            'directory' => $resultDirectory,
            'files' => $createdFiles,
            'reports' => $reportSummaries,
            'message' => $message,
        ];
    }

    private function writeXlsx(string $filePath, array $columns, array $rows, string $reportName): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheetTitle = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $reportName);
        $sheet->setTitle(mb_substr($sheetTitle !== '' ? $sheetTitle : 'Отчёт', 0, 31));
        foreach ($columns as $columnIndex => $column) {
            $sheet->setCellValue([$columnIndex + 1, 1], $column['label'] ?? $column['key']);
        }
        foreach ($rows as $rowIndex => $row) {
            foreach ($columns as $columnIndex => $column) {
                $value = $row[$column['key']] ?? '';
                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, $rowIndex + 2],
                    $value === null ? '' : (string) $value,
                    DataType::TYPE_STRING
                );
            }
        }
        ExcelStyle::applyDetailTable($sheet, count($columns));
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
    }

    private function safeFileName(string $name): string
    {
        $fileName = preg_replace('/[^\p{L}\p{N}._-]+/u', '-', trim($name));
        return trim((string) $fileName, '-_.') ?: 'report';
    }

    private function removeFiles(array $files, string $runDirectory): bool
    {
        foreach ($files as $file) {
            if (is_file($file) && !unlink($file)) {
                return false;
            }
        }
        if (is_dir($runDirectory) && !rmdir($runDirectory)) {
            return false;
        }

        $mailingDirectory = dirname($runDirectory);
        if (is_dir($mailingDirectory)) {
            $entries = scandir($mailingDirectory);
            if ($entries === ['.', '..']) {
                rmdir($mailingDirectory);
            }
        }
        return true;
    }
}
