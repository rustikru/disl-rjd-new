<?php
declare(strict_types=1);

namespace Tests\Unit\Import;

use App\Controllers\ImportController;
use App\Database\DbInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Тесты парсинга даты справки из ячейки A2.
 */
class ParseReportDateTest extends TestCase
{
    private ImportController $controller;
    private ReflectionMethod $method;

    protected function setUp(): void
    {
        $db = $this->createMock(DbInterface::class);
        $this->controller = new ImportController($db, []);

        $this->method = new ReflectionMethod(ImportController::class, 'parseReportDate');
        $this->method->setAccessible(true);
    }

    private function parse(string $raw): string
    {
        return $this->method->invoke($this->controller, $raw);
    }

    public function testBasicFormat(): void
    {
        $this->assertSame('2026-06-10 16:01:00', $this->parse('10.06.2026 16:01'));
    }

    public function testWithLeadingTrailingSpaces(): void
    {
        $this->assertSame('2026-06-10 08:00:00', $this->parse('  10.06.2026 08:00  '));
    }

    public function testWithSingleQuotes(): void
    {
        // Ячейки Excel иногда содержат строку в одинарных кавычках
        $this->assertSame('2026-01-15 23:59:00', $this->parse("'15.01.2026 23:59'"));
    }

    public function testMidnightTime(): void
    {
        $this->assertSame('2025-12-31 00:00:00', $this->parse('31.12.2025 00:00'));
    }

    public function testInvalidFormatThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/разобрать дату/');
        $this->parse('не_дата');
    }

    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse('');
    }
}
