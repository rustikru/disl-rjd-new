<?php

namespace App;

use App\Excel\ExcelStyle;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Psr\Http\Message\ResponseInterface as Response;

class ExcelExport
{
    /**
     * Универсальный экспорт детализированных таблиц 
     */
    public static function download(
        Response $response,
        array $cols,
        array $rows,
        string $filename = 'export',
        string $tableType = 'detail'
    ): Response {
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

        if ($tableType === 'summary') {
            ExcelStyle::applySummaryTable($sheet, count($cols));
        } else {
            ExcelStyle::applyDetailTable($sheet, count($cols));
        }
        return self::outputStream($response, $spreadsheet, $filename);
    }

    /**
     * 2. Экспорт матричных таблиц (двухуровневые шахматки дашборда)
     */
    public static function downloadMatrix(
        Response $response,
        array $colGroups,
        array $roads,
        string $filename = 'matrix',
        array $groupCols = [],
        array $flatCols = []
    ): Response {
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

        $matrixColumnCount = max(1, $currentColIdx - 1);
        ExcelStyle::applyMatrixHeader($sheet, $matrixColumnCount);

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
            ExcelStyle::applyMatrixGroupRow($sheet, $rowIdx);

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

        ExcelStyle::applyMatrixColumns($sheet, $matrixColumnCount);
        return self::outputStream($response, $spreadsheet, $filename);
    }

    /**
     * Возвращает значение измерения по переданному ключу, затем по совместимым
     * именам, затем по первому попавшемуся значению, игнорируя пустые строки и массивы.
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
     * Выводит Excel-файл в поток 
     */
    private static function outputStream(Response $response, Spreadsheet $spreadsheet, string $filename): Response
    {
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
