<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database\DbInterface;

class StationDirectoryRepository
{
    public function __construct(private DbInterface $db)
    {
    }

    public function countStations(string $searchText): int
    {
        $row = $this->db->fetchOne(
            'SELECT xx_rjd_dislocation_new_pkg.stations_count(:p_search) AS cnt FROM dual',
            ['p_search' => $searchText !== '' ? $searchText : null]
        );

        return (int) ($row['cnt'] ?? 0);
    }

    public function getStations(string $searchText, int $firstRowNumber, int $rowsLimit): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM TABLE(xx_rjd_dislocation_new_pkg.stations_pipe(:p_search, :p_offset, :p_limit))',
            [
                'p_search' => $searchText !== '' ? $searchText : null,
                'p_offset' => $firstRowNumber,
                'p_limit' => $rowsLimit,
            ]
        );
    }

    public function saveStation(
        string $esrCode,
        string $stationName,
        ?float $latitude,
        ?float $longitude
    ): void {
        $this->db->execute(
            'BEGIN xx_rjd_dislocation_new_pkg.save_station(:p_esr_code, :p_station_name, :p_latitude, :p_longitude); END;',
            [
                'p_esr_code' => $esrCode,
                'p_station_name' => $stationName,
                'p_latitude' => $latitude,
                'p_longitude' => $longitude,
            ]
        );
    }

    public function deleteStation(string $esrCode): void
    {
        $this->db->execute(
            'BEGIN xx_rjd_dislocation_new_pkg.delete_station(:p_esr_code); END;',
            ['p_esr_code' => $esrCode]
        );
    }

    public function getStationsWithoutCoordinates(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM TABLE(xx_rjd_dislocation_new_pkg.stations_without_coordinates_pipe())'
        );
    }
}
