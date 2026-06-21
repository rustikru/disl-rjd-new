<?php
declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Controllers\ImportController;
use App\Database\DbInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Тесты приведения строковых значений из Excel к типам БД.
 */
class CastValueTest extends TestCase
{
    private ImportController $controller;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $db = $this->createMock(DbInterface::class);
        $this->controller = new ImportController($db, []);

        $this->method = new ReflectionMethod(ImportController::class, 'castValue');
    }

    private function cast(string $field, ?string $raw): mixed
    {
        return $this->method->invoke($this->controller, $field, $raw);
    }

    // --- NULL / пустые значения ---

    public function testNullRawReturnsNull(): void
    {
        $this->assertNull($this->cast('wagon_no', null));
        $this->assertNull($this->cast('cargo_weight_kg', null));
        $this->assertNull($this->cast('oper_dt', null));
    }

    public function testEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->cast('wagon_no', ''));
        $this->assertNull($this->cast('cargo_weight_kg', ''));
        $this->assertNull($this->cast('oper_dt', ''));
    }

    // --- Числовые поля (NUMBER_FIELDS) ---

    public function testIntegerNumber(): void
    {
        $this->assertSame(1500, $this->cast('cargo_weight_kg', '1500'));
    }

    public function testFloatNumber(): void
    {
        $this->assertSame(123.45, $this->cast('dist_remain_km', '123.45'));
    }

    public function testNumberWithRegularSpaceThousandsSeparator(): void
    {
        // Обычный пробел как разделитель тысяч
        $this->assertSame(1500, $this->cast('cargo_weight_kg', '1 500'));
    }

    public function testNumberWithNbspThousandsSeparator(): void
    {
        // Неразрывный пробел из Excel
        $this->assertSame(75000, $this->cast('cargo_weight_kg', "75\u{00A0}000"));
    }

    public function testNonNumericStringInNumberFieldReturnsNull(): void
    {
        $this->assertNull($this->cast('cargo_weight_kg', 'не_число'));
    }

    public function testZeroIsValidNumber(): void
    {
        $this->assertSame(0, $this->cast('idle_time_days', '0'));
    }

    public function testLargeNumber(): void
    {
        $this->assertSame(1000000, $this->cast('mileage_total_km', '1 000 000'));
    }

    // --- Поля дат (DATE_FIELDS) ---

    public function testDateFieldParsedToIsoFormat(): void
    {
        $this->assertSame('2026-06-10 14:30:00', $this->cast('oper_dt', '10.06.2026 14:30'));
    }

    public function testDateOnlyFieldParsedWithZeroTime(): void
    {
        $this->assertSame('2026-01-15 00:00:00', $this->cast('reg_date', '15.01.2026'));
    }

    public function testInvalidDateInDateFieldReturnsNull(): void
    {
        $this->assertNull($this->cast('oper_dt', 'не_дата'));
    }

    // --- Строковые поля (всё остальное) ---

    public function testStringFieldReturnedAsIs(): void
    {
        $this->assertSame('12345678', $this->cast('wagon_no', '12345678'));
    }

    public function testStringFieldWithSpaces(): void
    {
        $this->assertSame('УГЛЕУРАЛЬСКАЯ (768207)', $this->cast('dest_station', 'УГЛЕУРАЛЬСКАЯ (768207)'));
    }

    public function testStringFieldWithCyrillicText(): void
    {
        $this->assertSame('Уголь', $this->cast('cargo_name', 'Уголь'));
    }
}
