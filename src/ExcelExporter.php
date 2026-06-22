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
    public static function downloadMatrix(Response $response, array $colGroups, array $roads, string $filename = 'matrix'): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сводная аналитика');

        $sheet->setCellValue('A1', 'Дорога / Станция операции');
        $sheet->mergeCells('A1:A2');

        $currentColIdx = 2;
        foreach ($colGroups as $group) {
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
        foreach ($roads as $road) {
            $sheet->setCellValue('A' . $rowIdx, $road['road'] ?? $road['name'] ?? 'Неизвестно');
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
                $sheet->setCellValue('A' . $rowIdx, '  ' . ($station['name'] ?? $station['oper_station'] ?? ''));
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