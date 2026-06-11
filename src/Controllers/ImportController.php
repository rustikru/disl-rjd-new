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

    /** Поля, которые хранятся как DATE в БД (Excel: 'DD.MM.YYYY' или 'DD.MM.YYYY HH:MI') */
    private const DATE_FIELDS = [
        'trip_start_dt',
        'trip_end_dt',
        'oper_dt',
        'asoup_depart_dt',
        'asoup_arrive_dt',
        'state_assign_dt',
        'norm_delivery_dt',
        'reg_date',
        'build_date',
        'next_repair_dt',
        'last_cap_repair_dt',
        'last_dep_repair_dt',
        'exclude_date',
        'life_ext_date',
        'lease_end_date',
        'service_life',
    ];

    /** Поля, которые хранятся как NUMBER в БД */
    private const NUMBER_FIELDS = [
        'cargo_weight_kg',
        'mileage_loaded_km',
        'mileage_empty_km',
        'mileage_total_km',
        'mileage_norm_km',
        'mileage_remain_km',
        'dist_passed_km',
        'dist_remain_km',
        'dist_total_km',
        'idle_time_days',
        'days_to_repair',
        'days_no_oper',
        'days_no_move',
        'tare_weight',
        'load_capacity',
        'length_mm',
        'body_volume',
        'axles_count',
        'seals_count',
        'loaded_containers',
        'empty_containers',
        'wagon_in_train',
        'body_material_code',
        'life_ext_sign',
        'repair_by_mileage',
        'lease_sign',
        'boiler_caliber',
    ];

    public function __construct(DbInterface $db)
    {
        $this->db = $db;
    }

    /** GET /import */
    public function showForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $reports = $this->db->fetchAll(
            "SELECT to_char((report_dt),'DD.MM.YYYY HH24:MI:SS') AS report_date, type_reference, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             GROUP BY to_char((report_dt),'DD.MM.YYYY HH24:MI:SS'), (report_dt), type_reference
             ORDER BY (report_dt) DESC, type_reference
             " . $this->db->limit(20)
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
                'Справка «' . $result['type'] . '» на ' . $result['report_dt'] . ' уже была загружена ранее — импорт пропущен'
            ));
        }

        return $this->redirect($response, '/import?success=' . urlencode(
            'Загружено ' . $result['rows'] . ' строк. Справка «' . $result['type'] . '»: ' . $result['report_dt']
        ));
    }

    private function importFile(string $path): array
    {
        ini_set('memory_limit', '512M');

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        // Дата справки из ячейки A2 (формат: '10.06.2026 16:01')
        $rawDt = trim((string) $sheet->getCell('A2')->getValue());
        $reportDt = $this->parseReportDate($rawDt);

        $highestRow = $sheet->getHighestRow();

        // Определяем тип справки по dest_station (кол. 12) первой непустой строки
        $fileType = $this->detectFileType($sheet, $highestRow);

        $reportDate = substr($reportDt, 0, 10); // 'YYYY-MM-DD'
        $exists = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM xx_dislocation_rjd
             WHERE TRUNC(report_dt) = TO_DATE(:dt, 'YYYY-MM-DD') AND type_reference = :type",
            ['dt' => $reportDate, 'type' => $fileType]
        );
        if ((int) ($exists['cnt'] ?? 0) > 0) {
            return ['skipped' => true, 'report_dt' => $rawDt, 'type' => $fileType, 'rows' => 0];
        }

        $fields = $this->columnFieldNames();
        $placeholders = array_map(fn($f) => ':' . $f, $fields);
        $insertSql = 'INSERT INTO xx_dislocation_rjd (report_dt, type_reference, ' . implode(', ', $fields) . ')'
            . ' VALUES (:report_dt, :type_reference, ' . implode(', ', $placeholders) . ')';

        $inserted = 0;

        $this->db->beginTransaction();
        try {
            for ($row = 5; $row <= $highestRow; $row++) {
                $vals = [];
                for ($col = 1; $col <= 126; $col++) {
                    $coord = Coordinate::stringFromColumnIndex($col) . $row;
                    $v = $sheet->getCell($coord)->getValue();
                    $vals[] = ($v === null) ? null : trim((string) $v);
                }

                if (empty(array_filter($vals, fn($v) => $v !== null && $v !== ''))) {
                    continue;
                }

                $destStation = $vals[11] ?? '';
                $typeRef = ($destStation === 'УГЛЕУРАЛЬСКАЯ (768207)') ? 'Подход' : 'Отправка';

                $params = ['report_dt' => $reportDt, 'type_reference' => $typeRef];
                foreach ($fields as $i => $field) {
                    $params[$field] = $this->castValue($field, $vals[$i] ?? null);
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

        return ['skipped' => false, 'report_dt' => $rawDt, 'type' => $fileType, 'rows' => $inserted];
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
            'wagon_no',
            'waybill_no',
            'wagon_type_code',
            'owner_admin',
            'trip_start_dt',
            'depart_state',
            'depart_road',
            'depart_station',
            'trip_end_dt',
            'dest_state',
            'dest_road',
            'dest_station',
            'consignor_tgnl',
            'consignor',
            'consignor_okpo',
            'consignor_name',
            'consignee_tgnl',
            'consignee',
            'consignee_okpo',
            'consignee_name',
            'cargo_name',
            'cargo_gng',
            'cargo_weight_kg',
            'mileage_loaded_km',
            'mileage_empty_km',
            'mileage_total_km',
            'mileage_norm_km',
            'mileage_remain_km',
            'mileage_sign',
            'special_marks',
            'prev_cargo',
            'oper_station',
            'oper_road',
            'operation',
            'oper_mnemonic',
            'oper_dt',
            'park_type',
            'handover_road',
            'receive_road',
            'train_index',
            'train_no',
            'wagon_in_train',
            'park_no',
            'track_no',
            'seals_count',
            'loaded_containers',
            'empty_containers',
            'container_nos',
            'norm_delivery_dt',
            'dist_passed_km',
            'dist_remain_km',
            'dist_total_km',
            'idle_time_hhmmss',
            'idle_time_days',
            'extra_waybill_no',
            'extra_send_id',
            'asoup_depart_dt',
            'asoup_arrive_dt',
            'send_id',
            'waybill_id',
            'wagon_no2',
            'quality_sign',
            'state_assign_dt',
            'wagon_state',
            'state_reason',
            'state_station',
            'reg_date',
            'build_date',
            'next_repair_dt',
            'next_repair_type',
            'factory_no',
            'manufacturer',
            'wagon_type_name',
            'wagon_model',
            'tare_weight',
            'load_capacity',
            'length_mm',
            'last_cap_repair_depot',
            'last_cap_repair_dt',
            'last_dep_repair_depot',
            'last_dep_repair_dt',
            'home_road',
            'home_depot',
            'exclude_date',
            'no_transit_reason',
            'prev_wagon_no',
            'owner',
            'owner_okpo',
            'owner_local_code',
            'home_station',
            'threshold_sign',
            'lease_sign',
            'life_ext_date',
            'lessee',
            'lessee_okpo',
            'lessee_local_code',
            'lease_home_station',
            'lease_end_date',
            'service_life',
            'body_material_code',
            'body_material_name',
            'body_volume',
            'clearance',
            'air_dist_type',
            'automode',
            'auto_lever',
            'brake_type',
            'coupler_type',
            'bogie_model',
            'shock_absorber',
            'life_ext_sign',
            'boiler_caliber',
            'drain_device',
            'lever_gear',
            'wagon_model_code',
            'repair_by_mileage',
            'proxy_operator',
            'proxy_operator_okpo',
            'wagon_type_code2',
            'wagon_type_cond',
            'axles_count',
            'exclude_depot',
            'exclude_reason',
            'days_to_repair',
            'days_no_oper',
            'days_no_move',
        ];
    }

    /**
     * Определяет тип справки по первой непустой dest_station (кол. 12 = столбец L).
     * 'УГЛЕУРАЛЬСКАЯ (768207)' → 'Подход', всё остальное → 'Отправка'.
     */
    private function detectFileType(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $highestRow): string
    {
        for ($row = 5; $row <= min(35, $highestRow); $row++) {
            $val = trim((string) ($sheet->getCell('L' . $row)->getValue() ?? ''));
            if ($val !== '') {
                return ($val === 'УГЛЕУРАЛЬСКАЯ (768207)') ? 'Подход' : 'Отправка';
            }
        }
        return 'Отправка';
    }

    /** Приводит строку из Excel к нужному типу для вставки в БД. */
    private function castValue(string $field, ?string $raw): string|int|float|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return $this->parseExcelDate($raw);
        }

        if (in_array($field, self::NUMBER_FIELDS, true)) {
            // Убираем пробелы (обычные и неразрывные) — разделители тысяч из Excel
            $clean = str_replace([' ', "\u{00A0}", "\xc2\xa0"], '', $raw);
            return is_numeric($clean) ? $clean + 0 : null;
        }

        return $raw;
    }

    /**
     * Парсит дату Excel в формат 'YYYY-MM-DD HH:MI:SS' (NLS_DATE_FORMAT сессии Oracle).
     * Входные форматы: 'DD.MM.YYYY HH:MI[:SS]' или 'DD.MM.YYYY'.
     * Если передана числовая строка — трактует как серийный номер даты Excel.
     */
    private function parseExcelDate(string $raw): ?string
    {
        // 'DD.MM.YYYY HH:MI' или 'DD.MM.YYYY HH:MI:SS'
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?/', $raw, $m)) {
            $sec = isset($m[6]) && $m[6] !== '' ? $m[6] : '00';
            return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:{$sec}";
        }

        // 'DD.MM.YYYY'
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]} 00:00:00";
        }

        // Excel serial date (число дней с 01.01.1900)
        if (is_numeric($raw)) {
            $ts = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $raw);
            return date('Y-m-d H:i:s', $ts);
        }

        return null;
    }

    private function redirect(ResponseInterface $response, string $url): ResponseInterface
    {
        return $response->withHeader('Location', $url)->withStatus(302);
    }
}
