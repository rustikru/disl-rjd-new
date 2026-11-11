<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;
use App\Reports\WagonConfig;

final class WagonMovement
{
    public function __construct(private DbInterface $db, private Organizations $organizations) {}

    public function rows(string $number, int $page = 1, int $perPage = 50): array
    {
        if (!preg_match('/^[0-9]{8}$/D', $number)) {
            throw new \InvalidArgumentException('Введите номер вагона из 8 цифр');
        }

        $reportsService = new ReportService($this->db, $this->organizations);
        $reports = $reportsService->getLatestDtsByType(null, ['Подход', 'Отправка']);
        $condition = $reportsService->latestDtCondition($reports);

        $trip = $this->db->fetchOne(
            "SELECT * FROM (SELECT trip_start_dt, trip_end_dt, waybill_no, depart_station, dest_station, report_dt
               FROM xx_dislocation_rjd WHERE {$condition['sql']} AND TRIM(wagon_no) = :wagon_no
               ORDER BY oper_dt DESC NULLS LAST, report_dt DESC,
                        CASE WHEN type_reference = 'Отправка' THEN 0 ELSE 1 END, id DESC) WHERE ROWNUM = 1",
            $condition['params'] + ['wagon_no' => $number]
        );

        if (!$trip) {
            return ['rows' => [], 'message' => 'Вагон не найден в актуальных справках выбранной организации.'];
        }

        $waybill = trim((string)($trip['waybill_no'] ?? ''));

        if (empty($trip['trip_start_dt']) && ($waybill === '' || $waybill === '0')) {
            return ['rows' => [], 'message' => 'Не удалось определить текущий рейс: отсутствуют дата начала и номер накладной.'];
        }

        $fields = implode(', ', array_filter([
            'oper_dt',
            'operation',
            'oper_station',
            'oper_road',
            'train_no',
            'waybill_no',
        ]));
        $sourceFields = implode(', ', array_unique(array_merge(
            array_diff(WagonConfig::historyFields(), ['idle_days']),
            ['oper_mnemonic', 'oper_station_esr_code', 'report_dt', 'id']
        )));

        $sources = [];

        if (!empty($trip['trip_start_dt']) && $waybill !== '' && $waybill !== '0') {
            $archiveFilter = $this->organizations->filter('a');
            $archiveWhere = $archiveFilter['sql']
                . ' AND TRIM(a.wagon_no) = :wagon_no'
                . ' AND TRIM(a.waybill_no) = :waybill'
                . ' AND a.oper_dt IS NOT NULL'
                . " AND TRUNC(a.oper_dt) >= TRUNC(TO_DATE(:trip_start, 'YYYY-MM-DD HH24:MI:SS'))";

            $params = $archiveFilter['params'] + [
                'wagon_no'   => $number,
                'waybill'    => $waybill,
                'trip_start' => $trip['trip_start_dt'],
            ];

            $sources[] = "SELECT $sourceFields FROM xx_rjd_dislocation_archive a WHERE $archiveWhere";
        }

        if (!$sources) {
            return ['rows' => [], 'trip' => $trip, 'message' => 'Недостаточно данных для определения операций текущего рейса.'];
        }

        $deduplicatedSql = "SELECT $fields, oper_station_esr_code AS page_station_code
                              FROM (SELECT $sourceFields,
                                           ROW_NUMBER() OVER (
                                               PARTITION BY oper_dt, UPPER(TRIM(NVL(operation, oper_mnemonic))),
                                                            LTRIM(TRIM(oper_station_esr_code), '0'), UPPER(TRIM(oper_station))
                                               ORDER BY report_dt DESC, id DESC
                                           ) AS operation_row
                                      FROM (" . implode(' UNION ALL ', $sources) . "))
                             WHERE operation_row = 1";

        $countRow = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM ($deduplicatedSql)", $params);
        $total = (int) ($countRow['cnt'] ?? 0);
        $perPage = max(1, min(100, $perPage));
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = $this->db->fetchAll(
            "SELECT movement_rows.*,
                    CASE WHEN next_oper_dt IS NULL THEN NULL
                         ELSE TRUNC(next_oper_dt) - TRUNC(oper_dt)
                    END AS idle_days
               FROM (SELECT movement_rows.*,
                            LEAD(oper_dt) OVER (
                                ORDER BY oper_dt, oper_station, operation, page_station_code,
                                         oper_road, train_no, waybill_no
                            ) AS next_oper_dt,
                            ROW_NUMBER() OVER (
                                ORDER BY oper_dt, oper_station, operation, page_station_code,
                                         oper_road, train_no, waybill_no
                            ) AS page_row
                       FROM ($deduplicatedSql) movement_rows
              ) movement_rows
              WHERE page_row > :page_offset AND page_row <= :page_limit
              ORDER BY page_row",
            $params + ['page_offset' => $offset, 'page_limit' => $offset + $perPage]
        );

        return [
            'rows'    => $rows,
            'trip'    => $trip,
            'page'    => $page,
            'per_page' => $perPage,
            'total'   => $total,
            'total_pages' => $totalPages,
            'from'    => $total > 0 ? $offset + 1 : 0,
            'to'      => min($total, $offset + $perPage),
            'message' => $rows ? '' : 'В сохранённых справках и архиве нет операций текущего рейса.',
        ];
    }
}
