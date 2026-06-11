<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

class ImportController
{
    private DbInterface $db;

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /** GET /import */
    public function showForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $reports = $this->db->fetchAll(
            'SELECT report_dt, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             GROUP BY report_dt
             ORDER BY report_dt DESC
             LIMIT 10'
        );

        ob_start();
        include __DIR__ . '/../../templates/import.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /** POST /import */
    public function handleUpload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['xlsx_file'])) {
            return $this->redirect($response, '/import?error=' . urlencode('Файл не выбран'));
        }

        /** @var UploadedFileInterface $file */
        $file = $uploadedFiles['xlsx_file'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->redirect($response, '/import?error=' . urlencode('Ошибка загрузки файла (kod ' . $file->getError() . ')'));
        }

        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->redirect($response, '/import?error=' . urlencode('Допускаются только файлы .xlsx / .xls'));
        }

        $tmpPath = sys_get_temp_dir() . '/rzd_import_' . uniqid() . '.' . $ext;
        $file->moveTo($tmpPath);

        try {
            $result = $this->importFile($tmpPath);
        } catch (\Exception $e) {
            @unlink($tmpPath);
            return $this->redirect($response, '/import?error=' . urlencode('Ошибка разбора файла: ' . $e->getMessage()));
        }

        @unlink($tmpPath);

        if ($result['skipped']) {
            return $this->redirect($response, '/import?warn=' . urlencode(
                'Справка на ' . $result['report_dt'] . ' уже была загружена ранее — импорт пропущен'
            ));
        }

        return $this->redirect($response, '/import?success=' . urlencode(
            'Загружено ' . $result['rows'] . ' строк. Справка: ' . $result['report_dt']
        ));
    }

    private function importFile(string $path): array
    {
        ini_set('memory_limit', '512M');

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet       = $spreadsheet->getActiveSheet();

        // Дата справки из ячейки A2 (формат: '10.06.2026 16:01')
        $rawDt    = trim((string) $sheet->getCell('A2')->getValue());
        $reportDt = $this->parseReportDate($rawDt);

        // Дедупликация
        $exists = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM xx_dislocation_rjd WHERE report_dt = :dt',
            ['dt' => $reportDt]
        );
        if ((int) ($exists['cnt'] ?? 0) > 0) {
            return ['skipped' => true, 'report_dt' => $rawDt, 'rows' => 0];
        }

        $fields       = $this->columnFieldNames();
        $placeholders = array_map(fn($f) => ':' . $f, $fields);
        $insertSql    = 'INSERT INTO xx_dislocation_rjd (report_dt, ' . implode(', ', $fields) . ')'
                      . ' VALUES (:report_dt, ' . implode(', ', $placeholders) . ')';

        $highestRow = $sheet->getHighestRow();
        $inserted   = 0;

        $this->db->beginTransaction();
        try {
            for ($row = 5; $row <= $highestRow; $row++) {
                $vals = [];
                for ($col = 1; $col <= 126; $col++) {
                    $coord  = Coordinate::stringFromColumnIndex($col) . $row;
                    $v      = $sheet->getCell($coord)->getValue();
                    $vals[] = ($v === null) ? null : trim((string) $v);
                }

                if (empty(array_filter($vals, fn($v) => $v !== null && $v !== ''))) {
                    continue;
                }

                $params = ['report_dt' => $reportDt];
                foreach ($fields as $i => $field) {
                    $params[$field] = $vals[$i] ?? null;
                }
                $this->db->execute($insertSql, $params);
                $inserted++;
            }
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        return ['skipped' => false, 'report_dt' => $rawDt, 'rows' => $inserted];
    }

    private function parseReportDate(string $raw): string
    {
        $raw = trim($raw, " '\"\u{2018}\u{2019}");
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2})/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:00";
        }
        throw new \RuntimeException("Не удалось разобрать дату справки из ячейки A2: «$raw»");
    }

    private function columnFieldNames(): array
    {
        return [
            'wagon_no', 'waybill_no', 'wagon_type_code', 'owner_admin',
            'trip_start_dt', 'depart_state', 'depart_road', 'depart_station',
            'trip_end_dt', 'dest_state', 'dest_road', 'dest_station',
            'consignor_tgnl', 'consignor', 'consignor_okpo', 'consignor_name',
            'consignee_tgnl', 'consignee', 'consignee_okpo', 'consignee_name',
            'cargo_name', 'cargo_gng', 'cargo_weight_kg',
            'mileage_loaded_km', 'mileage_empty_km', 'mileage_total_km',
            'mileage_norm_km', 'mileage_remain_km', 'mileage_sign',
            'special_marks', 'prev_cargo',
            'oper_station', 'oper_road', 'operation', 'oper_mnemonic', 'oper_dt',
            'park_type', 'handover_road', 'receive_road',
            'train_index', 'train_no', 'wagon_in_train', 'park_no', 'track_no',
            'seals_count', 'loaded_containers', 'empty_containers', 'container_nos',
            'norm_delivery_dt', 'dist_passed_km', 'dist_remain_km', 'dist_total_km',
            'idle_time_hhmmss', 'idle_time_days',
            'extra_waybill_no', 'extra_send_id',
            'asoup_depart_dt', 'asoup_arrive_dt',
            'send_id', 'waybill_id', 'wagon_no2',
            'quality_sign', 'state_assign_dt', 'wagon_state', 'state_reason', 'state_station',
            'reg_date', 'build_date', 'next_repair_dt', 'next_repair_type',
            'factory_no', 'manufacturer', 'wagon_type_name', 'wagon_model',
            'tare_weight', 'load_capacity', 'length_mm',
            'last_cap_repair_depot', 'last_cap_repair_dt',
            'last_dep_repair_depot', 'last_dep_repair_dt',
            'home_road', 'home_depot', 'exclude_date', 'no_transit_reason',
            'prev_wagon_no', 'owner', 'owner_okpo', 'owner_local_code', 'home_station',
            'threshold_sign', 'lease_sign', 'life_ext_date',
            'lessee', 'lessee_okpo', 'lessee_local_code', 'lease_home_station', 'lease_end_date',
            'service_life', 'body_material_code', 'body_material_name', 'body_volume',
            'clearance', 'air_dist_type', 'automode', 'auto_lever', 'brake_type',
            'coupler_type', 'bogie_model', 'shock_absorber', 'life_ext_sign',
            'boiler_caliber', 'drain_device', 'lever_gear', 'wagon_model_code',
            'repair_by_mileage', 'proxy_operator', 'proxy_operator_okpo',
            'wagon_type_code2', 'wagon_type_cond', 'axles_count',
            'exclude_depot', 'exclude_reason',
            'days_to_repair', 'days_no_oper', 'days_no_move',
        ];
    }

    private function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
