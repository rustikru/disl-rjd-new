# Альтернативный подход к импорту: MERGE с дедупликацией по строкам

## Идея

Вместо хранения полных снимков справок, дедуплицировать каждую строку по
естественному ключу. Одно и то же состояние вагона не создаёт новую строку —
только обновляется `report_dt`. Новое состояние (новая операция) → INSERT в историю.

**Ключ дедупликации:**
```
(wagon_no, type_reference, oper_dt, operation, oper_station)
```

## Почему не использовали

KPI-тренды в Oracle-пакете `xx_rjd_dislocation_new_pkg` сравнивают полные снимки
по `report_dt` (текущий = `MAX(report_dt)`, предыдущий = второй `MAX`).

При MERGE "предыдущий снимок" содержал бы только изменившиеся строки, а не все 2000 —
тренды считались бы неверно.

**Текущее решение:** удалять данные за тот же день+тип и вставлять заново
(одна полная справка на день).

## Когда применимо

Если KPI-пакет будет переписан и не будет зависеть от сравнения полных снимков
по `report_dt` — MERGE является более правильным решением с точки зрения хранения:
таблица растёт только пропорционально реальным изменениям в дислокации.

## Код: MERGE SQL (в importFile())

```php
$fields = $this->columnFieldNames();
$placeholders = array_map(fn($f) => ':' . $f, $fields);

// MERGE: совпадение по ключу → обновляем report_dt (данные не изменились),
// нет совпадения → INSERT (новое состояние вагона, идёт в историю).
$mergeSql =
    'MERGE INTO xx_dislocation_rjd t'
    . ' USING (SELECT 1 FROM dual) s'
    . ' ON ('
    .     't.wagon_no       = :wagon_no'
    .     ' AND t.type_reference = :type_reference'
    .     ' AND t.oper_dt        = :oper_dt'
    .     ' AND t.operation      = :operation'
    .     ' AND t.oper_station   = :oper_station'
    . ')'
    . ' WHEN MATCHED THEN'
    .     ' UPDATE SET t.report_dt = :report_dt'
    . ' WHEN NOT MATCHED THEN'
    .     ' INSERT (report_dt, type_reference, ' . implode(', ', $fields) . ')'
    .     ' VALUES (:report_dt, :type_reference, ' . implode(', ', $placeholders) . ')';

$processed = 0;

$this->db->beginTransaction();
try {
    for ($row = 5; $row <= $highestRow; $row++) {
        $vals = [];
        for ($col = 1; $col <= 126; $col++) {
            $coord = Coordinate::stringFromColumnIndex($col) . $row;
            $v = $sheet->getCell($coord)->getValue();
            $vals[] = ($v === null) ? null : trim((string) $v);
        }

        if (empty(array_filter($vals, fn($v) => $v !== null && $v !== ''))) {
            continue;
        }

        $destStation = $vals[11] ?? '';
        $typeRef = ($destStation === 'УГЛЕУРАЛЬСКАЯ (768207)') ? 'Подход' : 'Отправка';

        $params = ['report_dt' => $reportDt, 'type_reference' => $typeRef];
        foreach ($fields as $i => $field) {
            $params[$field] = $this->castValue($field, $vals[$i] ?? null);
        }
        $this->db->execute($mergeSql, $params);
        $processed++;
    }
    $this->db->commit();
} catch (\Exception $e) {
    $this->db->rollback();
    throw $e;
} finally {
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
}

return ['skipped' => false, 'report_dt' => $rawDt, 'type' => $fileType, 'rows' => $processed];
```

## Миграция: уникальный индекс

```sql
-- Шаг 1: удаляем дубли если уже есть (оставляем строку с наибольшим report_dt)
DELETE FROM xx_dislocation_rjd t
WHERE ROWID NOT IN (
    SELECT MAX(ROWID)
    FROM xx_dislocation_rjd
    GROUP BY wagon_no, type_reference, oper_dt, operation, oper_station
);

COMMIT;

-- Шаг 2: уникальный индекс
CREATE UNIQUE INDEX ux_disl_rjd_row_key
    ON xx_dislocation_rjd (wagon_no, type_reference, oper_dt, operation, oper_station);
```

## Примечание по OCI8

`oci_bind_by_name` привязывает значение по имени — одно и то же `:wagon_no`
в ON-условии и в INSERT VALUES использует одно значение. Дублирование имён
в SQL безопасно для Oracle/OCI8.
