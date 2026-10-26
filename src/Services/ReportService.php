<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class ReportService
{
    public function __construct(private DbInterface $db, private OrganizationService $organizations) {}

    /** Даты последних справок по типам. */
    public function getLatestDtsByType(?string $dt = null, ?array $types = null): array
    {
        if ($types !== null && count($types) === 0) {
            return [];
        }
        $sql = 'SELECT type_reference, MAX(report_dt) AS dt FROM xx_dislocation_rjd';
        $filter = $this->organizations->filter();
        $sql .= ' WHERE ' . $filter['sql'];
        $params = $filter['params'];
        if ($types !== null) {
            $placeholders = implode(',', array_map(fn($i) => ":t$i", array_keys($types)));
            $sql .= " AND type_reference IN ($placeholders)";
            foreach ($types as $i => $t) {
                $params["t$i"] = $t;
            }
        }
        $sql .= ' GROUP BY type_reference';
        $rows = $this->db->fetchAll($sql, $params);
        $dates = [];
        foreach ($rows as $r) {
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

}
