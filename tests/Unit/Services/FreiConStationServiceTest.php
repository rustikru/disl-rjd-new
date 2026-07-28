<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FreiConStationService;
use PHPUnit\Framework\TestCase;

final class FreiConStationServiceTest extends TestCase
{
    public function testParsesStationResponse(): void
    {
        $station = (new FreiConStationService())->parseResponse(json_encode([
            'code' => '801405',
            'name' => 'Электростанция',
            'stationType' => 'станция',
            'railwayName' => 'Южно-Уральская',
            'regionName' => 'Челябинская область',
            'lat' => 55.192177,
            'lon' => 61.414623,
        ], JSON_UNESCAPED_UNICODE));

        $this->assertSame('801405', $station['esr_code']);
        $this->assertSame('Электростанция', $station['station_name']);
        $this->assertSame('55.192177', $station['latitude']);
        $this->assertSame('61.414623', $station['longitude']);
    }

    public function testAllowsMissingCoordinates(): void
    {
        $station = (new FreiConStationService())->parseResponse(json_encode([
            'code' => '784816',
            'name' => '29 КМ',
            'lat' => null,
            'lon' => null,
        ], JSON_UNESCAPED_UNICODE));

        $this->assertSame('', $station['latitude']);
        $this->assertSame('', $station['longitude']);
    }

    public function testReturnsNullForUnexpectedResponse(): void
    {
        $station = (new FreiConStationService())->parseResponse('{}');

        $this->assertNull($station);
    }
}
