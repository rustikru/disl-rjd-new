<?php

namespace App;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Psr\Http\Message\ResponseInterface as Response;

class ExcelExporter
{
    /**
     * 1. Универсальный экспорт плоских массивов (детализации)
     * Полностью совместим с PHP 8.1+ и PHP 8.3/8.4+ (без депрекейшнов инкремента)
     */
    public static function download(Response $response, array $cols, array $rows, string $filename = 'export'): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Детализация');

        // Шапка
        $colIdx = 1;
        foreach ($cols as $c) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->setCellValue($colLetter . '1', $c['label'] ?? $c['title'] ?? '');
            $colIdx++;
        }

        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highestColumn}1")->getFont()->setBold(true);

        // Данные
        $rowIdx = 2;
        foreach ($rows as $row) {
            $colIdx = 1;
            foreach ($cols as $c) {
                $key = $c['key'];
                $val = is_array($row) ? ($row[$key] ?? '') : ($row->$key ?? '');
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);

                // Защита номеров вагонов и станций от порчи числовым форматом Excel
                if (is_numeric($val) && strlen((string) $val) < 10) {
                    $sheet->setCellValue($colLetter . $rowIdx, (float) $val);
                } else {
                    $sheet->setCellValueExplicit($colLetter . $rowIdx, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $colIdx++;
            }
            $rowIdx++;
        }

        return self::outputStream($response, $spreadsheet, $filename);
    }

    /**
     * 2. Экспорт сложных матричных таблиц (двухуровневые шахматки дашборда)
     */
    public static function downloadMatrix(
        Response $response,
        array $colGroups,
        array $roads,
        string $filename = 'matrix',
        array $groupCols = [],
        array $flatCols = []
    ): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сводная аналитика');

        $sheet->setCellValue('A1', 'Дорога / Станция операции');
        $sheet->mergeCells('A1:A2');

        $currentColIdx = 2;
        // Одноуровневые матрицы приходят в `cols`, многоуровневые — в `col_groups`.
        // Нормализуем оба варианта в один формат для построения шапки.
        if (empty($colGroups) && !empty($flatCols)) {
            $colGroups = array_map(
                static fn($label) => ['label' => (string) $label, 'subs' => []],
                $flatCols
            );
        }

        foreach ($colGroups as $group) {
            if (!is_array($group)) {
                $group = ['label' => (string) $group, 'subs' => []];
            }
            $label = $group['label'] ?? '';
            $subs = $group['subs'] ?? [];

            if (!empty($subs)) {
                $startLetter = Coordinate::stringFromColumnIndex($currentColIdx);
                $endColIdx = $currentColIdx + count($subs) - 1;
                $endLetter = Coordinate::stringFromColumnIndex($endColIdx);

                $sheet->setCellValue($startLetter . '1', $label);
                $sheet->mergeCells("{$startLetter}1:{$endLetter}1");

                foreach ($subs as $sub) {
                    $subLetter = Coordinate::stringFromColumnIndex($currentColIdx);
                    $sheet->setCellValue($subLetter . '2', $sub);
                    $currentColIdx++;
                }
            } else {
                $colLetter = Coordinate::stringFromColumnIndex($currentColIdx);
                $sheet->setCellValue($colLetter . '1', $label);
                $sheet->mergeCells("{$colLetter}1:{$colLetter}2");
                $currentColIdx++;
            }
        }

        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highestColumn}2")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$highestColumn}2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$highestColumn}2")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Наполнение данными (Дороги + вложенные Станции)
        $rowIdx = 3;
        $groupKeys = array_values(array_filter(array_map(
            static fn($group) => is_array($group) ? ($group['key'] ?? null) : null,
            $groupCols
        )));

        foreach ($roads as $road) {
            $roadKey = $groupKeys[0] ?? null;
            $roadName = self::dimensionValue(
                $road,
                $roadKey,
                ['road', 'name'],
                ['stations', 'total', 'grand_total']
            );
            $sheet->setCellValue('A' . $rowIdx, $roadName);
            $sheet->getStyle('A' . $rowIdx)->getFont()->setBold(true);

            $totals = $road['total'] ?? [];
            $colIdx = 2;
            foreach ($totals as $totalVal) {
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                $sheet->setCellValue($colLetter . $rowIdx, (float) $totalVal);
                $colIdx++;
            }
            $rowIdx++;

            $stations = $road['stations'] ?? [];
            foreach ($stations as $station) {
                $stationNames = [];
                foreach (array_slice($groupKeys, 1) as $stationKey) {
                    $value = self::dimensionValue($station, $stationKey);
                    if ($value !== '') {
                        $stationNames[] = $value;
                    }
                }
                if (empty($stationNames)) {
                    $fallbackName = self::dimensionValue(
                        $station,
                        null,
                        ['name', 'oper_station', 'dest_station', 'depart_station'],
                        ['v']
                    );
                    if ($fallbackName !== '') {
                        $stationNames[] = $fallbackName;
                    }
                }
                $sheet->setCellValue('A' . $rowIdx, '  ' . implode(' / ', $stationNames));
                $stationValues = $station['v'] ?? [];
                $colIdx = 2;
                foreach ($stationValues as $val) {
                    $colLetter = Coordinate::stringFromColumnIndex($colIdx);
                    $sheet->setCellValue($colLetter . $rowIdx, (float) $val);
                    $colIdx++;
                }
                $rowIdx++;
            }
        }

        return self::outputStream($response, $spreadsheet, $filename);
    }

    /**
     * Возвращает значение измерения по переданному ключу, затем по совместимым
     * именам и, для старых клиентов, по первому скалярному полю строки.
     */
    private static function dimensionValue(
        array $row,
        ?string $preferredKey = null,
        array $fallbackKeys = [],
        array $ignoredKeys = []
    ): string {
        $keys = array_filter(array_merge([$preferredKey], $fallbackKeys));
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && is_scalar($row[$key]) && (string) $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        foreach ($row as $key => $value) {
            if (!in_array($key, $ignoredKeys, true) && is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * Вспомогательный метод безопасной отдачи потока по стандарту RFC 5987 (для кириллицы)
     */
    private static function outputStream(Response $response, Spreadsheet $spreadsheet, string $filename): Response
    {
        $highestColumn = $sheet = $spreadsheet->getActiveSheet()->getHighestColumn();
        foreach (range('A', $highestColumn) as $col) {
            $spreadsheet->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
        }

        $fullFilename = $filename . '_' . date('Y-m-d') . '.xlsx';
        $fallbackFilename = 'report_' . date('Y-m-d') . '.xlsx';
        $fallbackFilename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $fallbackFilename);

        $contentDisposition = sprintf(
            'attachment; filename="%s"; filename*=UTF-8\'\'%s',
            $fallbackFilename,
            rawurlencode($fullFilename)
        );

        $phpStream = fopen('php://temp', 'r+');
        $writer = new Xlsx($spreadsheet);
        $writer->save($phpStream);
        rewind($phpStream);

        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', $contentDisposition)
            ->withHeader('Cache-Control', 'max-age=0')
            ->withBody(new \Slim\Psr7\Stream($phpStream));
    }
}
