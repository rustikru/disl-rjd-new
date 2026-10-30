<?php
declare(strict_types=1);

namespace App\Excel;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
