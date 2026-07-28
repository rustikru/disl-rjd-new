<?php
declare(strict_types=1);

namespace App\Services;

final class FreiConStationService
{
    private const STATION_URL = 'https://online.freicon.ru/v1/stations/';

    public function find(string $esrCode): ?array
    {
        $esrCode = trim($esrCode);
        if (!preg_match('/^\d{1,6}$/D', $esrCode)) {
            throw new \InvalidArgumentException('Код ЕСР должен содержать до шести цифр');
        }
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('Расширение PHP cURL не установлено');
        }

        $esrCode = str_pad($esrCode, 6, '0', STR_PAD_LEFT);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => self::STATION_URL . rawurlencode($esrCode),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'RJD Station Lookup',
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        if ($status === 404) {
            return null;
        }
        if ($body === false || $status !== 200) {
            throw new \RuntimeException(
                $error !== '' ? $error : 'FreiCON вернул HTTP ' . $status
            );
        }

        return $this->parseResponse((string) $body);
    }

    public function parseResponse(string $body): ?array
    {
        $station = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($station) || empty($station['code']) || empty($station['name'])) {
            return null;
        }

        return [
            'esr_code' => (string) $station['code'],
            'station_name' => (string) $station['name'],
            'station_type' => (string) ($station['stationType'] ?? ''),
            'railway_name' => (string) ($station['railwayName'] ?? ''),
            'region_name' => (string) ($station['regionName'] ?? ''),
            'latitude' => isset($station['lat']) ? (string) $station['lat'] : '',
            'longitude' => isset($station['lon']) ? (string) $station['lon'] : '',
        ];
    }
}
