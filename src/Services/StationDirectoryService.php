<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\StationDirectoryRepository;
use InvalidArgumentException;

class StationDirectoryService
{
    private const ITEMS_PER_PAGE = 50;

    public function __construct(private StationDirectoryRepository $stationRepository)
    {
    }

    public function getPage(string $searchText, int $requestedPage): array
    {
        $currentPage = max(1, $requestedPage);
        $totalStations = $this->stationRepository->countStations($searchText);
        $totalPages = max(1, (int) ceil($totalStations / self::ITEMS_PER_PAGE));

        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }

        $firstRowNumber = ($currentPage - 1) * self::ITEMS_PER_PAGE;

        return [
            'stations' => $this->stationRepository->getStations($searchText, $firstRowNumber, self::ITEMS_PER_PAGE),
            'totalStations' => $totalStations,
            'totalPages' => $totalPages,
            'page' => $currentPage,
            'perPage' => self::ITEMS_PER_PAGE,
        ];
    }

    public function saveStation(
        string $esrCode,
        string $stationName,
        mixed $latitudeValue,
        mixed $longitudeValue
    ): void {
        $latitude = $this->decimalOrNull($latitudeValue, 'широта');
        $longitude = $this->decimalOrNull($longitudeValue, 'долгота');

        $this->stationRepository->saveStation($esrCode, $stationName, $latitude, $longitude);
    }

    public function deleteStation(string $esrCode): void
    {
        $this->stationRepository->deleteStation($esrCode);
    }

    public function getStationsWithoutCoordinates(): array
    {
        return $this->stationRepository->getStationsWithoutCoordinates();
    }

    private function decimalOrNull(mixed $value, string $fieldName): ?float
    {
        $text = str_replace(',', '.', trim((string) $value));
        if ($text === '') {
            return null;
        }

        if (!is_numeric($text)) {
            throw new InvalidArgumentException('Поле "' . $fieldName . '" должно быть числом');
        }

        return (float) $text;
    }
}
