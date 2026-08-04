<?php
declare(strict_types=1);

namespace App\Reports;

use App\Controllers\ApiController;
use App\Controllers\DowntimeControlController;
use App\Database\DbInterface;
use App\ExcelExporter;
use App\Services\OrganizationService;
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
        unset($filters['report_dt']);
        if ($view === 'SUMMARY') {
            $filters['group_by'] = implode(',', array_column($settings['group_cols'], 'key'));
            $filters['col_by'] = implode(',', $settings['col_dims']);
        }
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
            'group_cols' => $settings['group_cols'],
            'title' => $settings['name'],
            'view' => $view,
            'row_count' => $view === 'DETAIL' ? count($data['rows'] ?? []) : (int) ($data['total'] ?? 0),
            'report_dt' => $this->reportDate($mailing),
        ];
    }

    public function createFile(array $report, string $directory, string $suffix = ''): string
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Не удалось создать папку отчётов');
        }
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . $this->fileName((string) ($report['title'] ?? 'report'))
            . ($suffix !== '' ? '_' . $this->fileName($suffix) : '')
            . '_' . date('Y-m-d_H-i-s') . '.xlsx';

        $this->writeXlsx($report, $path);
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
        if (($report['view'] ?? 'DETAIL') === 'SUMMARY') {
            $data = $report['data'] ?? [];
            ExcelExporter::saveMatrix(
                $data['col_groups'] ?? [],
                $data['roads'] ?? [],
                $path,
                $report['group_cols'] ?? [],
                $data['cols'] ?? []
            );
            return;
        }
        ExcelExporter::save(
            $report['columns'] ?? [],
            $report['data']['rows'] ?? [],
            $path
        );
    }

    private function fileName(string $value): string
    {
        $value = preg_replace('/[^\pL\pN_-]+/u', '_', trim($value)) ?: 'report';
        return trim(mb_substr($value, 0, 80), '_');
    }
}
