<?php
declare(strict_types=1);

namespace App\Reports;

use App\Controllers\ApiController;
use App\Controllers\DowntimeControlController;
use App\Database\DbInterface;
use App\Services\OrganizationService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class ReportBuilder
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    public function build(array $mailing): array
    {
        $code = (string) $mailing['report_code'];
        $settings = ReportCatalog::find($code);
        if (!$settings) {
            throw new \RuntimeException('Неизвестный отчёт: ' . $code);
        }

        $view = strtoupper((string) ($mailing['report_view'] ?? 'DETAIL'));
        if (!in_array($view, $settings['views'], true)) {
            throw new \RuntimeException('Недоступный вид отчёта');
        }

        $filters = is_array($mailing['filters'] ?? null) ? $mailing['filters'] : [];
        if ($view === 'DETAIL') {
            $filters['fields'] = implode(',', array_column($settings['columns'], 'key'));
        }

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/report')
            ->withQueryParams($filters);
        $response = (new ResponseFactory())->createResponse();

        if ($code === 'downtime-control') {
            $response = (new DowntimeControlController($this->db))->detail($request, $response);
        } else {
            $organizationId = (int) ($mailing['organization_id'] ?? 0);
            if ($organizationId <= 0) {
                throw new \RuntimeException('Для отчёта не указана организация');
            }
            $organizations = new OrganizationService($this->db, $organizationId);
            $organizations->sync();
            $controller = new ApiController($this->db, $organizations);
            $method = $this->reportMethod($code, $view);
            $response = $controller->{$method}($request, $response);
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new \RuntimeException('Отчёт вернул некорректные данные');
        }

        return [
            'data' => $data,
            'columns' => $settings['columns'],
            'title' => $settings['name'],
            'view' => $view,
            'row_count' => $view === 'DETAIL' ? count($data['rows'] ?? []) : (int) ($data['total'] ?? 0),
            'report_dt' => $this->reportDate($mailing),
        ];
    }

    public function createFile(array $report, string $format, string $directory): string
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Не удалось создать папку отчётов');
        }
        $format = strtoupper($format) === 'CSV' ? 'CSV' : 'XLSX';
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . $this->fileName((string) ($report['title'] ?? 'report'))
            . '_' . date('Y-m-d_H-i-s') . '.' . strtolower($format);

        if ($format === 'CSV') {
            $this->writeCsv($report, $path);
        } else {
            $this->writeXlsx($report, $path);
        }
        return $path;
    }

    private function reportMethod(string $code, string $view): string
    {
        $methods = [
            'dislocation' => ['SUMMARY' => 'dislSummary', 'DETAIL' => 'dislDetail'],
            'approach' => ['SUMMARY' => 'approachSummary', 'DETAIL' => 'approachDetail'],
            'departure' => ['SUMMARY' => 'departureSummary', 'DETAIL' => 'departureDetail'],
            'loading' => ['SUMMARY' => 'loadingSummary', 'DETAIL' => 'loadingDetail'],
            'downtime' => ['SUMMARY' => 'downtimeSummary', 'DETAIL' => 'downtimeDetail'],
            'raw-material' => ['SUMMARY' => 'rawSummary', 'DETAIL' => 'rawDetail'],
            'analysis-period' => ['DETAIL' => 'analysisPeriod'],
        ];
        $method = $methods[$code][$view] ?? null;
        if (!$method) {
            throw new \RuntimeException('Для отчёта не настроено формирование данных');
        }
        return $method;
    }

    private function reportDate(array $mailing): ?string
    {
        $filters = $mailing['filters'] ?? [];
        if (!empty($filters['report_dt'])) {
            return (string) $filters['report_dt'];
        }
        $organizationId = (int) ($mailing['organization_id'] ?? 0);
        if ($organizationId <= 0) {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT MAX(report_dt) AS report_dt FROM xx_dislocation_rjd WHERE organization_id = :organization_id',
            ['organization_id' => $organizationId]
        );
        return !empty($row['report_dt']) ? (string) $row['report_dt'] : null;
    }

    private function writeXlsx(array $report, string $path): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Отчёт');
        $sheet->setCellValue('A1', (string) ($report['title'] ?? 'Отчёт'));
        $sheet->setCellValue('A2', 'Сформировано: ' . date('d.m.Y H:i'));
        $rows = $this->fileRows($report);
        $headers = array_shift($rows) ?: [];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1) . '4', $header);
        }
        if ($headers) {
            $last = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A4:' . $last . '4')->getFont()->setBold(true);
            $sheet->getStyle('A4:' . $last . '4')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8E4FF');
        }
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $cell = Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 5);
                if (is_numeric($value) && strlen((string) $value) < 10) {
                    $sheet->setCellValue($cell, (float) $value);
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
        }
        foreach (range(1, max(1, count($headers))) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    private function writeCsv(array $report, string $path): void
    {
        $file = fopen($path, 'wb');
        if (!$file) {
            throw new \RuntimeException('Не удалось создать CSV-файл');
        }
        fwrite($file, "\xEF\xBB\xBF");
        foreach ($this->fileRows($report) as $row) {
            fputcsv($file, $row, ';', '"', '');
        }
        fclose($file);
    }

    private function fileRows(array $report): array
    {
        if (($report['view'] ?? 'DETAIL') === 'SUMMARY') {
            return $this->summaryRows($report['data'] ?? []);
        }
        $columns = $report['columns'] ?? [];
        $result = [array_column($columns, 'label')];
        foreach (($report['data']['rows'] ?? []) as $row) {
            $result[] = array_map(static fn(array $column): mixed => $row[$column['key']] ?? '', $columns);
        }
        return $result;
    }

    private function summaryRows(array $data): array
    {
        $groups = $data['col_groups'] ?? [];
        $headers = ['Группа'];
        if ($groups) {
            foreach ($groups as $group) {
                $subs = is_array($group) ? ($group['subs'] ?? []) : [];
                if ($subs) {
                    foreach ($subs as $sub) {
                        $headers[] = ($group['label'] ?? '') . ' / ' . $sub;
                    }
                } else {
                    $headers[] = is_array($group) ? ($group['label'] ?? '') : (string) $group;
                }
            }
        } else {
            $headers = array_merge($headers, array_map('strval', $data['cols'] ?? []));
        }
        $rows = [$headers];
        foreach (($data['roads'] ?? []) as $road) {
            $rows[] = array_merge([$this->firstText($road, ['name', 'road', 'dest_road', 'oper_road', 'cargo_name'])], array_values($road['total'] ?? $road['v'] ?? []));
            foreach (($road['stations'] ?? []) as $station) {
                $rows[] = array_merge(['  ' . $this->firstText($station, ['name', 'station', 'dest_station', 'oper_station'])], array_values($station['v'] ?? []));
            }
        }
        if (isset($data['grand_total'])) {
            $rows[] = array_merge(['ИТОГО'], array_values((array) $data['grand_total']));
        }
        return $rows;
    }

    private function firstText(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) return (string) $row[$key];
        }
        foreach ($row as $key => $value) {
            if (!in_array($key, ['total', 'v', 'stations'], true) && is_scalar($value)) return (string) $value;
        }
        return '';
    }

    private function fileName(string $value): string
    {
        $value = preg_replace('/[^\pL\pN_-]+/u', '_', trim($value)) ?: 'report';
        return trim(mb_substr($value, 0, 80), '_');
    }
}
