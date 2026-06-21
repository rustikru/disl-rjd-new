<?php
declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Controllers\ImportController;
use App\Database\DbInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Тесты парсинга дат из ячеек справки (колонки дат операций, сроков ремонта и т.д.).
 */
class ParseExcelDateTest extends TestCase
{
    private ImportController $controller;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $db = $this->createMock(DbInterface::class);
        $this->controller = new ImportController($db, []);

        $this->method = new ReflectionMethod(ImportController::class, 'parseExcelDate');
        $this->method->setAccessible(true);
    }

    private function parse(string $raw): ?string
    {
        return $this->method->invoke($this->controller, $raw);
    }

    public function testDateTimeWithoutSeconds(): void
    {
        $this->assertSame('2026-06-10 14:30:00', $this->parse('10.06.2026 14:30'));
    }

    public function testDateTimeWithSeconds(): void
    {
        $this->assertSame('2026-06-10 14:30:45', $this->parse('10.06.2026 14:30:45'));
    }

    public function testDateOnly(): void
    {
        $this->assertSame('2026-06-10 00:00:00', $this->parse('10.06.2026'));
    }

    public function testDateWithZeroTime(): void
    {
        $this->assertSame('2025-01-01 00:00:00', $this->parse('01.01.2025 00:00'));
    }

    public function testExcelSerialDate(): void
    {
        // Excel serial 45000 = 2023-03-15 (приблизительно)
        $result = $this->parse('45000');
        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    public function testNonDateStringReturnsNull(): void
    {
        $this->assertNull($this->parse('не_дата'));
    }

    public function testLettersOnlyReturnsNull(): void
    {
        $this->assertNull($this->parse('ABC'));
    }
}
