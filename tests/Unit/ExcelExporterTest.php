<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\ExcelExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

class ExcelExporterTest extends TestCase
{
    public function testMatrixUsesDynamicRoadAndStationKeys(): void
    {
        $response = ExcelExporter::downloadMatrix(
            new Response(),
            [
                ['label' => 'ЦС', 'subs' => ['гр.', 'пор.']],
            ],
            [
                [
                    'oper_road' => 'СВРД',
                    'stations' => [
                        ['oper_station' => 'ПЕРМЬ-СОРТИРОВОЧНАЯ', 'v' => [3, 1]],
                    ],
                    'total' => [3, 1],
                    'grand_total' => 4,
                ],
            ],
            'test',
            [
                ['key' => 'oper_road', 'label' => 'Дорога операции'],
                ['key' => 'oper_station', 'label' => 'Станция операции'],
            ]
        );

        $path = tempnam(sys_get_temp_dir(), 'excel-export-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, (string) $response->getBody());
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            self::assertSame('СВРД', $sheet->getCell('A3')->getValue());
            self::assertSame('  ПЕРМЬ-СОРТИРОВОЧНАЯ', $sheet->getCell('A4')->getValue());
            self::assertSame(3.0, $sheet->getCell('B3')->getValue());
            self::assertSame(3.0, $sheet->getCell('B4')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function testLegacyMatrixInfersDynamicDimensionNames(): void
    {
        $response = ExcelExporter::downloadMatrix(
            new Response(),
            [['label' => 'ЦС', 'subs' => ['гр.']]],
            [[
                'dest_road' => 'ГОРЬК',
                'stations' => [['dest_station' => 'КИРОВ', 'v' => [2]]],
                'total' => [2],
                'grand_total' => 2,
            ]]
        );

        $path = tempnam(sys_get_temp_dir(), 'excel-export-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, (string) $response->getBody());
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            self::assertSame('ГОРЬК', $sheet->getCell('A3')->getValue());
            self::assertSame('  КИРОВ', $sheet->getCell('A4')->getValue());
        } finally {
            @unlink($path);
        }
    }
}
