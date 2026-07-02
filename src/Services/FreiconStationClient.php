<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class FreiconStationClient
{
    private const BASE_URL = 'https://online.freicon.ru/v1/stations/';

    public function getStation(string $esrCode): array
    {
        $cleanEsrCode = trim($esrCode);
        if ($cleanEsrCode === '') {
            throw new RuntimeException('Укажите код ЕСР для загрузки');
        }

        $url = self::BASE_URL . rawurlencode($cleanEsrCode);
        $jsonText = $this->load($url);
        $data = json_decode($jsonText, true);

        if (!is_array($data)) {
            throw new RuntimeException('FreiCON вернул ответ в неизвестном формате');
        }

        $stationName = trim((string) ($data['name'] ?? ''));
        $stationCode = trim((string) ($data['code'] ?? $cleanEsrCode));
        $latitude = $data['lat'] ?? null;
        $longitude = $data['lon'] ?? null;

        if ($stationName === '' || $stationCode === '' || $latitude === null || $longitude === null) {
            throw new RuntimeException('В ответе FreiCON нет названия, кода или координат станции');
        }

        return [
            'esr_code' => $stationCode,
            'station_name' => $stationName,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
            'source_url' => $url,
        ];
    }

    private function load(string $url): string
    {
        if (extension_loaded('curl')) {
            return $this->loadByCurl($url);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "Accept: application/json\r\nUser-Agent: RJD local importer\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $jsonText = @file_get_contents($url, false, $context);
        if ($jsonText === false) {
            throw new RuntimeException('Не удалось получить данные с FreiCON');
        }

        return $jsonText;
    }

    private function loadByCurl(string $url): string
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Не удалось подготовить запрос к FreiCON');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'RJD local importer',
        ]);

        $jsonText = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);

        if ($jsonText === false || $httpCode >= 400) {
            $message = $curlError !== '' ? $curlError : 'HTTP ' . $httpCode;
            throw new RuntimeException('Не удалось получить данные с FreiCON: ' . $message);
        }

        return (string) $jsonText;
    }
}
