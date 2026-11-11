<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class ReportService
{
    public function __construct(private DbInterface $db, private Organizations $organizations) {}

    /** Даты последних справок по типам. */
    public function getLatestDtsByType(?string $dt = null, ?array $types = null): array
    {
        $types ??= ['Подход', 'Отправка'];
        if (count($types) === 0) {
            return [];
        }
        $parts = [];
        $params = [];
        $types = array_values(array_unique($types));
        for ($i = 0; $i < count($types); $i += 2) {
            $second = $i + 1;
            $parts[] = "SELECT type_reference, report_dt AS dt
                          FROM TABLE(XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.GET_LATEST_REPORTS(
                              :t$i, :t$second, NULL
                          ))";
            $params["t$i"] = $types[$i];
            $params["t$second"] = $types[$second] ?? null;
        }
        $sql = implode(' UNION ALL ', $parts);
        $rows = $this->db->fetchAll($sql, $params);
        $dates = [];
        foreach ($rows as $r) {
            if (empty($r['dt'])) {
                continue;
            }
            $dates[(string) $r['type_reference']] = $dt ?? (string) $r['dt'];
        }
        return $dates;
    }

    /**
     * Условие отбора справок по типу, дате и организации.
     * $alias — псевдоним таблицы, например 'xdr' → 'xdr.type_reference'.
     */
    public function latestDtCondition(array $dtsByType, string $alias = ''): array
    {
        if (empty($dtsByType)) {
            return ['sql' => '1=0', 'params' => []];
        }
        $col = fn(string $c) => $alias !== '' ? "$alias.$c" : $c;
        $parts = [];
        $params = [];
        $i = 0;
        foreach ($dtsByType as $type => $dt) {
            $parts[] = "({$col('type_reference')} = :ldt_type_{$i} AND {$col('report_dt')} = TO_DATE(:ldt_dt_{$i}, 'YYYY-MM-DD HH24:MI:SS'))";
            $params["ldt_type_{$i}"] = $type;
            $params["ldt_dt_{$i}"] = $dt;
            $i++;
        }
        $sql = '(' . implode(' OR ', $parts) . ')';
        $this->organizations->addFilter($sql, $params, $alias);
        return ['sql' => $sql, 'params' => $params];
    }

    /** Детальные записи контроля простоев с фильтрами страницы. */
    public function downtimeControlDetail(array $filters): array
    {
        $bindings = [];
        $whereCond = '1=1';

        $wagonNo = trim((string) ($filters['wagon_no'] ?? ''));
        if ($wagonNo !== '') {
            $wagons = array_values(array_filter(
                preg_split('/[;,|\s]+/u', $wagonNo, -1, PREG_SPLIT_NO_EMPTY),
                static fn(string $wagon): bool => preg_match('/^\d{8}$/', $wagon) === 1
            ));
            if ($wagons) {
                $placeholders = [];
                foreach ($wagons as $index => $wagon) {
                    $key = 'wagon_no_' . $index;
                    $placeholders[] = ':' . $key;
                    $bindings[$key] = $wagon;
                }
                $whereCond .= ' AND TRIM(car_number) IN (' . implode(', ', $placeholders) . ')';
            }
        }

        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $whereCond .= " AND (end_date IS NULL OR TRUNC(end_date) >= TO_DATE(:date_from, 'YYYY-MM-DD'))";
            $bindings['date_from'] = $dateFrom;
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $whereCond .= " AND (start_date IS NULL OR TRUNC(start_date) <= TO_DATE(:date_to, 'YYYY-MM-DD'))";
            $bindings['date_to'] = $dateTo;
        }

        return $this->db->fetchAll(
            "SELECT * FROM xx_disl_idle_control_v WHERE $whereCond ORDER BY id_control DESC",
            $bindings
        );
    }

}
