# Backlog / Отложенные идеи

## Итоговые строки в таблице детализации

**Статус:** не реализовано, идея согласована

**Суть:**
Добавить `<tfoot>` с итогами в таблицу детализации. Итоги настраиваются в `detail-contexts.js` через поле `total` у каждой колонки.

**Конфиг (detail-contexts.js):**
```js
{ key: 'cargo_weight_kg', label: 'Вес (кг)', right: true,  total: 'sum' },
{ key: 'idle_time_days',  label: 'Простой',  danger: true, total: 'avg' },
{ key: 'wagon_no',        label: '№ вагона', meta: true,   total: 'count' },
// без total — ячейка пустая
```
Доступные типы: `count`, `sum`, `avg`, `max`, `min`.

**Реализация:**
- Только фронтенд, бэкенд не меняется
- Итоги считаются по `data.rows` в `showTable()` — ~30 строк JS

**Важный нюанс — пагинация:**
Если в будущем таблица будет показывать первые N строк (не все), итоги по частичному набору будут некорректными.

**Решение для пагинации:**
- Бэкенд отдаёт `total_count` — общее число строк (дешёвый `COUNT(*)` без `LIMIT`)
- Фронтенд показывает итоги только когда `rows.length >= total_count` (полный набор)
- Пока таблица урезана — вместо итогов подсказка: «Показаны первые N из M. Загрузите все для подсчёта итогов»
- Кнопка «Показать все» перезапрашивает без лимита → итоги появляются

**Плюсы подхода:**
- Нет лишних агрегирующих запросов к Oracle
- Пользователь видит итоги только по реальному полному набору — нет путаницы
- Бэкенд меняется минимально: добавить `total_count` в ответ detail-методов

---

## Кеш трендов KPI через таблицу `xx_kpi_trend_cache`

**Статус:** не реализовано, идея согласована

**Суть:**
Вместо вычисления трендов на каждый запрос (`set_kpi_label` + `prv_kpi_trend`) — считать один раз при импорте данных и хранить результат в кеш-таблице. Запрос `/api/kpi/summary` становится простым JOIN — < 50 мс вместо 2–3 сек.

**Oracle — DDL:**
```sql
CREATE TABLE XX_ETW.XX_KPI_TREND_CACHE (
    kpi_id    NUMBER        PRIMARY KEY,
    x_value   VARCHAR2(200),
    trend_pct VARCHAR2(50),
    trend_dir VARCHAR2(10),
    cached_at DATE
);
```

**Oracle — процедура обновления (добавить в пакет):**
```sql
PROCEDURE refresh_kpi_cache IS
    v_pct VARCHAR2(50);
    v_dir VARCHAR2(10);
BEGIN
    DELETE FROM XX_ETW.XX_KPI_TREND_CACHE;
    FOR rec IN (SELECT id FROM XX_KPI_TABLE_V) LOOP
        prv_kpi_trend(rec.id, v_pct, v_dir);
        INSERT INTO XX_ETW.XX_KPI_TREND_CACHE (kpi_id, x_value, trend_pct, trend_dir, cached_at)
        VALUES (rec.id, set_kpi_label(rec.id), v_pct, v_dir, SYSDATE);
    END LOOP;
    COMMIT;
END refresh_kpi_cache;
```
Вызывать `refresh_kpi_cache` после каждого импорта XLSX.

**PHP — запрос в `kpiSummary` становится:**
```sql
SELECT kpi.id, kpi.label AS x_label,
       c.x_value, c.trend_pct, c.trend_dir
FROM XX_KPI_TABLE_V kpi
JOIN XX_ETW.XX_KPI_TREND_CACHE c ON c.kpi_id = kpi.id
WHERE kpi.type = :kpi_type
```

**Сравнение с текущим подходом:**

| Подход | Время запроса | Когда считает |
|---|---|---|
| 3 скалярных функции | ~2–3 сек | каждый запрос |
| `get_kpi_row` pipelined | ~1–1.5 сек | каждый запрос |
| **Таблица-кеш** | **< 50 мс** | **один раз при импорте** |

---

## KPI карточки — нестандартная детализация и поля

**Статус:** не реализовано, идея согласована

**Суть:**
Сейчас `detail` в KPI-карточке всегда использует `DETAIL_CONTEXTS[ctx]` для полей.
Нужна возможность задать поля прямо в карточке для нестандартных случаев.

**Вариант 1 — передать fields через params (работает уже сейчас):**
```js
detail: {
  ctx: 'approach',
  params: { fields: 'wagon_no,cargo_name,dist_remain_km' }
}
```
Перебивает стандартный набор полей — backend строит SELECT только из указанных.

**Вариант 2 — добавить cols в detail (не реализовано):**
```js
detail: {
  ctx: 'approach',
  cols: [
    { key: 'wagon_no', label: '№ вагона', meta: true },
    { key: 'cargo_name', label: 'Груз' },
  ]
}
```
Тогда страница детализации использует эти колонки вместо `DETAIL_CONTEXTS[ctx].cols`.

**Вариант 3 — полностью свой URL:**
```js
detail: { url: BASE + '/detail?ctx=approach&cargo=Метанол&road=СВЕРДЛОВСКАЯ' }
```
Для полностью нестандартных карточек.


**Статус:** не реализовано, идея согласована

**Суть:**
Добавить `<tfoot>` с итогами в таблицу детализации. Итоги настраиваются в `detail-contexts.js` через поле `total` у каждой колонки.

**Конфиг (detail-contexts.js):**
```js
{ key: 'cargo_weight_kg', label: 'Вес (кг)', right: true,  total: 'sum' },
{ key: 'idle_time_days',  label: 'Простой',  danger: true, total: 'avg' },
{ key: 'wagon_no',        label: '№ вагона', meta: true,   total: 'count' },
// без total — ячейка пустая
```
Доступные типы: `count`, `sum`, `avg`, `max`, `min`.

**Реализация:**
- Только фронтенд, бэкенд не меняется
- Итоги считаются по `data.rows` в `showTable()` — ~30 строк JS

**Важный нюанс — пагинация:**
Если в будущем таблица будет показывать первые N строк (не все), итоги по частичному набору будут некорректными.

**Решение для пагинации:**
- Бэкенд отдаёт `total_count` — общее число строк (дешёвый `COUNT(*)` без `LIMIT`)
- Фронтенд показывает итоги только когда `rows.length >= total_count` (полный набор)
- Пока таблица урезана — вместо итогов подсказка: «Показаны первые N из M. Загрузите все для подсчёта итогов»
- Кнопка «Показать все» перезапрашивает без лимита → итоги появляются

**Плюсы подхода:**
- Нет лишних агрегирующих запросов к Oracle
- Пользователь видит итоги только по реальному полному набору — нет путаницы
- Бэкенд меняется минимально: добавить `total_count` в ответ detail-методов
