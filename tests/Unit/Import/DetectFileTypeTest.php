<?php
declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Controllers\ImportController;
use App\Database\DbInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Тесты определения типа справки по значению колонки L (ст. назначения).
 */
class DetectFileTypeTest extends TestCase
{
    private ImportController $controller;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $db = $this->createMock(DbInterface::class);
        $this->controller = new ImportController($db, []);

        $this->method = new ReflectionMethod(ImportController::class, 'detectFileType');
    }

    private function detect(array $columnLValues): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($columnLValues as $row => $value) {
            $sheet->getCell('L' . $row)->setValue($value);
        }

        $highestRow = max(array_keys($columnLValues));
        return $this->method->invoke($this->controller, $sheet, $highestRow);
    }

    public function testUgleuralskayaDetectedAsApproach(): void
    {
        $this->assertSame('Подход', $this->detect([5 => 'УГЛЕУРАЛЬСКАЯ (768207)']));
    }

    public function testOtherStationDetectedAsDeparture(): void
    {
        $this->assertSame('Отправка', $this->detect([5 => 'ПЕРМЬ-СОРТИРОВОЧНАЯ (768101)']));
    }

    public function testEmptyRowsSkippedUntilValue(): void
    {
        // Первые строки пустые, тип определяется по первой непустой
        $this->assertSame('Подход', $this->detect([
            5 => '',
            6 => '',
            7 => 'УГЛЕУРАЛЬСКАЯ (768207)',
        ]));
    }

    public function testAllEmptyRowsDefaultsToDeparture(): void
    {
        // Если в первых 30 строках нет значений — возвращаем 'Отправка' по умолчанию
        $this->assertSame('Отправка', $this->detect([5 => '']));
    }

    public function testFirstNonEmptyRowDeterminesType(): void
    {
        // Первая непустая строка — не Углеуральская, хотя ниже есть Углеуральская
        $this->assertSame('Отправка', $this->detect([
            5 => 'ПЕРМЬ-СОРТИРОВОЧНАЯ (768101)',
            6 => 'УГЛЕУРАЛЬСКАЯ (768207)',
        ]));
    }
}
