<?php
declare(strict_types=1);

namespace App\Reports;

use App\Database\DbInterface;

final class UserKpiPageSettings
{
    public static function sync(DbInterface $db): void
    {
        foreach (KpiTypeRegistry::userTypes() as $definition) {
            $page = trim((string) ($definition['page'] ?? ''));
            if ($page === '') continue;
            $db->execute(
                "INSERT INTO XX_ETW.XX_RJD_KPI_PAGE_CFG
                    (page_code, user_kpi_supported, create_enabled, updated_at)
                 SELECT :page_code, 'Y', 'N', SYSDATE FROM dual
                  WHERE NOT EXISTS (
                      SELECT 1 FROM XX_ETW.XX_RJD_KPI_PAGE_CFG WHERE page_code = :check_page
                  )",
                ['page_code' => $page, 'check_page' => $page]
            );
        }
    }

    public static function all(DbInterface $db): array
    {
        try {
            $rows = $db->fetchAll(
                'SELECT page_code, user_kpi_supported, create_enabled FROM XX_ETW.XX_RJD_KPI_PAGE_CFG ORDER BY page_code'
            );
        } catch (\Throwable) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['page_code']] = [
                'supported' => ($row['user_kpi_supported'] ?? 'N') === 'Y',
                'enabled' => ($row['create_enabled'] ?? 'N') === 'Y',
            ];
        }
        return $result;
    }

    public static function isEnabled(DbInterface $db, string $page): bool
    {
        try {
            $row = $db->fetchOne(
                'SELECT user_kpi_supported, create_enabled FROM XX_ETW.XX_RJD_KPI_PAGE_CFG WHERE page_code = :page_code',
                ['page_code' => trim($page)]
            );
        } catch (\Throwable) {
            return false;
        }
        return ($row['USER_KPI_SUPPORTED'] ?? $row['user_kpi_supported'] ?? 'N') === 'Y'
            && ($row['CREATE_ENABLED'] ?? $row['create_enabled'] ?? 'N') === 'Y';
    }
}
