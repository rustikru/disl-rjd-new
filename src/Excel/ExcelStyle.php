<?php
declare(strict_types=1);

namespace App\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ExcelStyle
{
    private const HEADER_BACKGROUND_COLOR = 'FFDDEBF7';
    private const HEADER_BORDER_COLOR = 'FF9EADBA';
    private const TABLE_BORDER_COLOR = 'FF7F8C99';
    private const HEADER_FONT_SIZE = 12;

    /** Записывает дату как значение Excel, сохраняя наличие или отсутствие времени. */
    public static function setCellValue(Worksheet $sheet, string|array $cell, mixed $value, array $column = []): void
    {
        if ($value === null || $value === '') {
            $sheet->setCellValue($cell, '');
            return;
        }

        $formatData = strtoupper((string) ($column['formatData'] ?? ''));
        $isDateColumn = str_contains($formatData, 'YYYY');
        $valueString = trim((string) $value);

        if ($isDateColumn && preg_match('/^(?:\d{4}-\d{2}-\d{2}|\d{2}\.\d{2}\.\d{4})(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?$/', $valueString)) {
            try {
                $date = new \DateTimeImmutable($valueString);
                $hasTime = preg_match('/[ T]\d{2}:\d{2}/', $valueString) === 1
                    && ($date->format('H:i:s') !== '00:00:00' || str_contains($formatData, 'HH24'));
                $sheet->setCellValueExplicit($cell, Date::PHPToExcel($date), DataType::TYPE_NUMERIC);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(
                    $hasTime ? 'dd.mm.yyyy hh:mm:ss' : 'dd.mm.yyyy'
                );
                return;
            } catch (\Exception $e) {
                // Некорректную дату оставляем исходным текстом.
            }
        }

        $sheet->setCellValueExplicit($cell, $valueString, DataType::TYPE_STRING);
    }

    public static function applyDetailTable(Worksheet $sheet, int $columnCount): void
    {
        if ($columnCount <= 0) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        self::applyHeader($sheet, "A1:{$lastColumn}1", true);
        $lastRow = max(1, $sheet->getHighestDataRow());
        self::applyTableBorders($sheet, "A1:{$lastColumn}{$lastRow}");
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        self::applyAutomaticColumnWidth($sheet, $columnCount);
    }

    public static function applyReferenceTable(Worksheet $sheet, int $columnCount): void
    {
        if ($columnCount <= 0) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = max(2, $sheet->getHighestDataRow());

        $titleStyle = $sheet->getStyle("A1:{$lastColumn}1");
        $titleStyle->getFont()->setBold(true)->setSize(12);
        $titleStyle->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        self::applyHeader($sheet, "A2:{$lastColumn}2", true);
        $sheet->getStyle("A2:{$lastColumn}2")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        self::applyTableBorders($sheet, "A2:{$lastColumn}{$lastRow}");
        $sheet->setAutoFilter("A2:{$lastColumn}{$lastRow}");
        $sheet->freezePane('A3');
        self::applyAutomaticColumnWidth($sheet, $columnCount);
    }

    public static function applySummaryTable(Worksheet $sheet, int $columnCount): void
    {
        if ($columnCount <= 0) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        self::applyHeader($sheet, "A1:{$lastColumn}1");
        $lastRow = max(1, $sheet->getHighestDataRow());
        self::applyTableBorders($sheet, "A1:{$lastColumn}{$lastRow}");
        self::applyAutomaticColumnWidth($sheet, $columnCount);
    }

    public static function applyMatrixHeader(Worksheet $sheet, int $columnCount): void
    {
        if ($columnCount <= 0) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        self::applyHeader($sheet, "A1:{$lastColumn}2");
        $headerStyle = $sheet->getStyle("A1:{$lastColumn}2");
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerStyle->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    public static function applyMatrixGroupRow(Worksheet $sheet, int $rowNumber): void
    {
        $sheet->getStyle('A' . $rowNumber)->getFont()->setBold(true);
    }

    public static function applyMatrixColumns(Worksheet $sheet, int $columnCount): void
    {
        if ($columnCount <= 0) {
            return;
        }

        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $lastRow = max(2, $sheet->getHighestDataRow());
        self::applyTableBorders($sheet, "A1:{$lastColumn}{$lastRow}");
        self::applyAutomaticColumnWidth($sheet, $columnCount);
    }

    private static function applyHeader(Worksheet $sheet, string $range, bool $withBorders = false): void
    {
        $headerStyle = $sheet->getStyle($range);
        $headerStyle->getFont()
            ->setBold(true)
            ->setSize(self::HEADER_FONT_SIZE);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB(self::HEADER_BACKGROUND_COLOR);

        if ($withBorders) {
            $headerStyle->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setARGB(self::HEADER_BORDER_COLOR);
        }
    }

    private static function applyAutomaticColumnWidth(Worksheet $sheet, int $columnCount): void
    {
        for ($columnIndex = 1; $columnIndex <= $columnCount; $columnIndex++) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
        }
    }

    /** Рисует тонкие границы с четырёх сторон у каждой ячейки таблицы. */
    private static function applyTableBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => self::TABLE_BORDER_COLOR],
                ],
            ],
        ]);
    }
}
