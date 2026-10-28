<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class WagonMovement
{
    public function __construct(private DbInterface $db, private Organizations $organizations) {}

    public function rows(string $number): array
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
        if (!$trip) return ['rows' => [], 'message' => 'Вагон не найден в актуальных справках выбранной организации.'];
        $filter = $this->organizations->filter();
        $params = $filter['params'] + ['wagon_no' => $number, 'depart_station' => $trip['depart_station'], 'dest_station' => $trip['dest_station']];
        $where = $filter['sql'] . " AND TRIM(wagon_no) = :wagon_no AND oper_dt IS NOT NULL
                  AND TRIM(depart_station) = TRIM(:depart_station) AND TRIM(dest_station) = TRIM(:dest_station)";
        if (empty($trip['depart_station']) || empty($trip['dest_station'])) {
            return ['rows' => [], 'message' => 'Недостаточно данных о станциях текущего рейса.'];
        }
        if (!empty($trip['trip_start_dt'])) {
            $params['trip_start'] = $trip['trip_start_dt'];
            $where .= " AND oper_dt >= TO_DATE(:trip_start, 'YYYY-MM-DD HH24:MI:SS')
                        AND (trip_start_dt = TO_DATE(:trip_start, 'YYYY-MM-DD HH24:MI:SS') OR trip_start_dt IS NULL)";
        } elseif (empty($trip['waybill_no']) || trim($trip['waybill_no']) === '0') {
            return ['rows' => [], 'message' => 'Не удалось определить текущий рейс: отсутствуют дата начала и номер накладной.'];
        }
        if (!empty($trip['waybill_no']) && trim($trip['waybill_no']) !== '0') {
            $params['waybill'] = trim($trip['waybill_no']);
            $where .= ' AND TRIM(waybill_no) = :waybill';
        }
        $params['trip_until'] = $trip['report_dt'];
        $where .= " AND oper_dt <= TO_DATE(:trip_until, 'YYYY-MM-DD HH24:MI:SS')";
        $rows = $this->db->fetchAll(
            "SELECT oper_dt, operation, oper_mnemonic, oper_station, oper_road, train_no, train_index, waybill_no
               FROM (SELECT oper_dt, operation, oper_mnemonic, oper_station, oper_road, train_no, train_index, waybill_no,
                            ROW_NUMBER() OVER (
                                PARTITION BY oper_dt, UPPER(TRIM(NVL(operation, oper_mnemonic))),
                                             LTRIM(TRIM(oper_station_esr_code), '0'), UPPER(TRIM(oper_station))
                                ORDER BY report_dt DESC, id DESC
                            ) AS operation_row
                       FROM xx_dislocation_rjd WHERE $where)
              WHERE operation_row = 1 ORDER BY oper_dt, oper_station, operation",
            $params
        );
        return ['rows' => $rows, 'trip' => $trip, 'message' => $rows ? '' : 'В сохранённых справках нет операций текущего рейса.'];
    }
}
