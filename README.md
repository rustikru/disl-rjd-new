# Дислокация парка вагонов РЖД

Веб-приложение для просмотра дислокации, подхода, отправления и погрузки вагонов на основе XLSX-выгрузки из ЛК клиента РЖД.

---

## Стек

| Слой           | Технология                                   |
| -------------- | -------------------------------------------- |
| Backend        | PHP 8.1+, Slim Framework 4, PSR-7            |
| База данных    | Oracle 23c (OCI8) / PostgreSQL (fallback)    |
| Frontend       | Vanilla JS (ES5), jQuery 3.7, CSS-переменные |
| Аутентификация | Локальный пароль или Active Directory (LDAP) |
| Веб-сервер     | Apache (mod_rewrite) / PHP built-in server   |

---

## Структура проекта

```
disl-rjd-new/
├── public/
│   ├── index.php            # Точка входа (bootstrap)
│   ├── .htaccess            # mod_rewrite → index.php
│   └── assets/
│       ├── css/app.css      # Все стили (включая шапки таблиц bisque)
│       └── js/
│           ├── jquery/jquery-3.7.1.min.js  # jQuery (локальная копия)
│           ├── detail-contexts.js           # Конфиг колонок детализации (DETAIL_CONTEXTS)
│           └── app.js                       # Вся клиентская логика (SPA)
├── src/
│   ├── routes.php           # Все маршруты Slim
│   ├── Config.php           # Чтение .env
│   ├── Auth/
│   │   ├── AuthService.php  # Проверка пароля (локальный + LDAP)
│   │   └── LdapAuth.php     # LDAP/AD адаптер
│   ├── Controllers/
│   │   ├── ApiController.php        # Все JSON API-методы
│   │   ├── AuthController.php       # Логин/логаут
│   │   ├── DashboardController.php  # Главная страница
│   │   └── ImportController.php     # Загрузка XLSX (batch + AJAX)
│   ├── Database/
│   │   ├── DbInterface.php   # Контракт: fetchAll, fetchOne, execute
│   │   ├── DbFactory.php     # Создаёт OracleDb или PostgresDb по конфигу
│   │   ├── OracleDb.php      # OCI8-адаптер
│   │   └── PostgresDb.php    # PDO PostgreSQL адаптер
│   └── Middleware/
│       ├── AuthMiddleware.php    # Редирект на /login без сессии
│       └── SessionMiddleware.php # session_start()
├── templates/
│   ├── app.php      # Основной SPA-шаблон (sidebar + панели)
│   ├── detail.php   # Страница детализации (открывается в новой вкладке)
│   ├── import.php   # Форма загрузки XLSX (AJAX, мультифайл)
│   └── login.php    # Форма входа
├── bin/
│   ├── create-user.php   # CLI: создать пользователя
│   └── set-password.php  # CLI: сменить пароль
├── sql/
│   └── oracle_xx_dislocation_rjd.sql  # DDL таблицы + индексы + комментарии
├── .env.example     # Пример конфига
├── composer.json
└── router.php       # Для PHP built-in server (php -S)
```

---

## Модель данных

### Таблица `xx_dislocation_rjd`

Центральная таблица. Одна строка = один вагон в одной справке РЖД.
126 колонок из Excel-выгрузки + 3 служебных:

| Поле               | Тип       | Описание                                              |
| ------------------ | --------- | ----------------------------------------------------- |
| `id`               | NUMBER    | Автоинкремент (SEQ + триггер)                         |
| `report_dt`        | TIMESTAMP | Дата и время справки                                  |
| `type_reference`   | VARCHAR2  | `'Подход'` / `'Отправка'`                             |
| `wagon_no`         | VARCHAR2  | Номер вагона                                          |
| `wagon_type_code`  | VARCHAR2  | Тип вагона (Цистерны, Полувагоны…)                    |
| `depart_road`      | VARCHAR2  | Дорога отправления                                    |
| `depart_station`   | VARCHAR2  | Станция отправления                                   |
| `dest_road`        | VARCHAR2  | Дорога назначения                                     |
| `dest_station`     | VARCHAR2  | Станция назначения                                    |
| `oper_road`        | VARCHAR2  | Дорога текущей операции                               |
| `oper_station`     | VARCHAR2  | Станция текущей операции                              |
| `oper_mnemonic`    | VARCHAR2  | Мнемоника операции (`ОТПР`, `ПРИБ`…)                  |
| `cargo_name`       | VARCHAR2  | Наименование груза                                    |
| `cargo_weight_kg`  | NUMBER    | Вес груза (кг)                                        |
| `dist_remain_km`   | NUMBER    | Остаток расстояния (км)                               |
| `idle_time_days`   | NUMBER    | Простой (сут.)                                        |
| `norm_delivery_dt` | DATE      | Нормативная дата доставки                             |
| `owner`            | VARCHAR2  | Собственник                                           |
| `lessee`           | VARCHAR2  | Арендатор                                             |
| `park_type`        | VARCHAR2  | Признак парка                                         |
| …                  | …         | Полный список — в `sql/oracle_xx_dislocation_rjd.sql` |

Полные комментарии на русском ко всем 126 колонкам есть в DDL-файле (`COMMENT ON COLUMN`).

### Импорт данных

`POST /api/import/file` — AJAX-эндпоинт для постфайловой загрузки (JSON-ответ).
`ImportController::handleUploadJson` читает XLSX через PhpSpreadsheet и вставляет строки пакетами в `xx_dislocation_rjd`. Дата справки берётся из ячейки A2 файла.

Страница `/import` загружает файлы последовательно, показывая статус каждого файла в реальном времени (Ожидание → Загрузка → Обработка → OK / Предупреждение / Ошибка). Поддерживается выбор нескольких `.xlsx` за раз. URL остаётся чистым после загрузки.

---

## API

Все методы защищены `AuthMiddleware`. GET-запросы возвращают JSON, кроме `/api/import/file`.

| URL                              | Метод контроллера    | Описание                               |
| -------------------------------- | -------------------- | -------------------------------------- |
| `GET /api/dashboard`             | `dashboard`          | KPI + разбивка по типам вагонов        |
| `GET /api/dislocation/filters`   | `dislFilters`        | Список дат справок                     |
| `GET /api/dislocation/summary`   | `dislSummary`        | Сводная по разделам парка              |
| `GET /api/dislocation/detail`    | `dislDetail`         | Детальный список вагонов               |
| `GET /api/approach/filters`      | `approachFilters`    | Значения для фильтров подхода          |
| `GET /api/approach/summary`      | `approachSummary`    | Сводная подхода                        |
| `GET /api/approach/detail`       | `approachDetail`     | Список вагонов подхода                 |
| `GET /api/departure/filters`     | `departureFilters`   | Значения для фильтров отправления      |
| `GET /api/departure/summary`     | `departureSummary`   | Сводная отправления                    |
| `GET /api/departure/detail`      | `departureDetail`    | Список отправленных вагонов            |
| `GET /api/loading/summary`       | `loadingSummary`     | Сводная погрузки                       |
| `GET /api/loading/detail`        | `loadingDetail`      | Список погруженных вагонов             |
| `GET /api/downtime/filters`      | `downtimeFilters`    | Значения для фильтров простоев         |
| `GET /api/downtime/summary`      | `downtimeSummary`    | Простои по станциям                    |
| `GET /api/downtime/detail`       | `downtimeDetail`     | Список вагонов с простоем              |
| `GET /api/raw-material/summary`  | `rawSummary`         | Сводная по сырью                       |
| `GET /api/raw-material/detail`   | `rawDetail`          | Список вагонов с сырьём               |
| `GET /api/analysis/period/detail`| `analysisPeriod`     | История операций за период             |
| `POST /api/import/file`          | `handleUploadJson`   | Загрузка одного XLSX-файла (JSON)      |

### Параметры сводных API

| Параметр    | Описание                                                           |
| ----------- | ------------------------------------------------------------------ |
| `report_dt` | Фильтр по дате справки                                             |
| `cargo`     | Фильтр по наименованию груза                                       |
| `group_by`  | Поля группировки через запятую: `dest_road,dest_station`           |
| `col_by`    | Поля для колонок сводной через запятую: `wagon_type_code`          |
| `fields`    | Поля SELECT для детализации через запятую                          |
| `sort`      | Поле сортировки (детализация)                                      |
| `sort_dir`  | Направление сортировки: `asc` / `desc`                             |
| `sort_type` | Тип сортировки: `number` для числовой (иначе строковая)            |

`group_by` и `col_by` формируются автоматически из `WAGON_TABS[k].groupCols` и `WAGON_TABS[k].colDims` в `app.js` — PHP-код менять не нужно при изменении группировки.

Поля валидируются через `isSafeField()` (whitelist `[a-z0-9_.]`); список допустимых полей сверяется с `user_tab_columns` — схема БД является единственным источником правды.

### Ответ `/api/import/file`

```json
{ "status": "ok",   "type": "Отправка", "rows": 513, "report_dt": "15.06.2026 21:01" }
{ "status": "warn", "message": "Уже загружено: Отправка 15.06.2026 21:01" }
{ "status": "error","message": "Описание ошибки" }
```

---

## Схема взаимодействия компонентов

```
┌──────────────────────────── КОНФИГУРАЦИЯ (frontend) ──────────────────────────────┐
│                                                                                    │
│  app.js: WAGON_TABS[ctx]                  detail-contexts.js: DETAIL_CONTEXTS[ctx] │
│  ─────────────────────────                ─────────────────────────────────────── │
│  ctx          — ключ контекста            label    — заголовок страницы           │
│  summaryUrl   — URL сводного API          endpoint — путь к API                   │
│  detailUrl    — URL расширенной таблицы   cols[]   — колонки (key, label,         │
│  groupCols[]  — строки сводной (GROUP BY)           meta, right, danger,          │
│  colDims[]    — колонки сводной (GROUP BY)          fmt(v), formatData, w, type)  │
│  getParams()  — активные фильтры формы    sort     — сортировка по умолчанию      │
│  kpi(data)    — KPI-карточки из ответа API                                        │
│  csvFilename      — имя CSV сводной                                               │
│  csvDetFilename   — имя CSV расширенной                                           │
│  filtersUrl / fillFilters() / resetFilters()                                      │
│  loadedKey / loadedDetKey / applyBtnId                                            │
└────────────────────────────────────────────────────────────────────────────────────┘
         │ initTab(cfg)                                     │
         │                                                  │
         ▼                                                  │
┌────────────────────────────────────────────────────────────────────────────────────┐
│                        Главная страница  (app.php + app.js)                         │
│                                                                                     │
│  ┌── KPI-карточки ────────────────────────────────────────────────────────────┐    │
│  │  GET /api/dashboard → KPI_BOARDS.dashboard.cards(data)                     │    │
│  │  Рендерятся в #kpiGrid (вкладка Дислокация)                                │    │
│  │  Карточка: { label, value, accent?, detail? }                               │    │
│  │  detail: { ctx, params, _endpoint? } → клик открывает /detail              │    │
│  │  _endpoint — переопределить URL API, сохранив колонки DETAIL_CONTEXTS[ctx] │    │
│  └────────────────────────────────────────────────────────────────────────────┘    │
│                                                                                     │
│  ┌── Сводная таблица (вкладка «Сводная») ────────────────────────────────────┐    │
│  │  GET summaryUrl?group_by=G1,G2&col_by=C1&report_dt=...&[filters]           │    │
│  │  → drawSummary() или draw() (кастомная функция)                             │    │
│  │  Строки: groupCols[0] (дорога) → groupCols[1] (станция)                   │    │
│  │  Колонки: colDims[].key → тип вагона / состояние груза                     │    │
│  │           colDims[].synthetic:true → метка-псевдоколонка (не передаётся)   │    │
│  │  Итог: drawSummary рисует <table id=sumTableId>                             │    │
│  │  CSV: кнопка «↓ CSV» → exportTableToCSV(sumTableId, csvFilename)           │    │
│  │  Клик по строке → loadDetail(cfg, { road, station })                       │    │
│  └──────────────────────────────────────┬─────────────────────────────────────┘    │
│                                          │ клик по строке сводной                  │
│                                          ▼                                          │
│  ┌── Расширенная таблица (вкладка «Расширенная») ────────────────────────────┐    │
│  │  GET detailUrl?group_by=G1,G2&road=X&station=Y&fields=f1,f2,...            │    │
│  │  fields = DETAIL_CONTEXTS[ctx].cols[].key (автоматически)                  │    │
│  │  → showTable($container, rows, colDefs)                                     │    │
│  │  colDefs = DETAIL_CONTEXTS[ctx].cols                                        │    │
│  │  formatData → oracleMaskFmt(v, mask) → fmt(v) (авто, в showTable)          │    │
│  │  Виртуальный скролл: только видимые строки в DOM                            │    │
│  │  Колонки: key, label, meta, right, danger, fmt                              │    │
│  │  CSV: кнопка «↓ CSV» → saveCSVfromVT(detTableId, csvDetFilename)           │    │
│  │  Фильтр по колонкам: строка ввода под каждым заголовком                    │    │
│  └──────────────────────────────────────┬─────────────────────────────────────┘    │
│                                          │ клик по вагону / кнопка «Детализация»   │
└──────────────────────────────────────────┼─────────────────────────────────────────┘
                                           │ openDetail(ctx, road, station,
                                           │           col, groupBy, subs, extra)
                                           │ URL: /detail?ctx=...&road=...
                                           │             [&_endpoint=/api/custom/...]
                                           ▼
┌────────────────────────────────────────────────────────────────────────────────────┐
│                          Страница детализации  (detail.php)                         │
│                                                                                     │
│  endpoint = DETAIL_CONTEXTS[ctx].endpoint                                           │
│           или ?_endpoint=... из URL (переопределение)                               │
│  GET endpoint?[все URL-параметры кроме ctx, _bcpath, _endpoint]&fields=f1,f2,...   │
│  → showTable(rows, cols)                                                            │
│  cols = DETAIL_CONTEXTS[ctx].cols                                                   │
│  formatData → oracleMaskFmt() → fmt(v) (авто, в showTable)                        │
│  sort: ?sort=field&sort_dir=asc|desc&sort_type=number|string                       │
│  Хлебные крошки: _bcpath=["Дорога","Станция"]                                     │
│  CSV: кнопка «Скачать CSV» → saveCSV(title)                                       │
└──────────────────────────────────────────┬─────────────────────────────────────────┘
                                           │ HTTP запрос
                                           ▼
┌────────────────────────────────────────────────────────────────────────────────────┐
│                         Backend: ApiController.php                                   │
│                                                                                     │
│  summaryReport($base, $rowDims, $colDefs)                                           │
│  ├── resolveColDims(string $colBy, array $defaults)                                 │
│  │     реестр: wagon_type_code → FNC_MAPPING_WAG_TYPE(wagon_type_code)              │
│  │             cargo_w_type    → CASE WHEN cargo_weight_kg > 0 THEN …               │
│  ├── applyFormat(array $def): string                                                │
│  │     if $def['formatData'] → TO_CHAR($def['expr'], '$def[formatData]')            │
│  │     иначе → $def['expr'] без изменений                                           │
│  ├── SELECT: rowDims, applyFormat(colDef) AS alias, COUNT(*) AS cnt                │
│  └── GROUP BY: rowDims, applyFormat(colDef)  ← без алиаса                          │
│                                                                                     │
│  selectFields($fields)                                                              │
│  └── isSafeField() → whitelist [a-z0-9_.] + проверка user_tab_columns              │
└────────────────────────────────────────────────────────────────────────────────────┘
```

---

## Конфигурация KPI-карточек

KPI-карточки рендерятся в `#kpiGrid` (вкладка Дислокация). Данные берутся из `GET /api/dashboard`.

### Объект карточки

```js
{
  label:   'Всего вагонов',  // подпись
  value:   1234,             // значение
  accent:  true,             // синяя рамка (выделение главного показателя)
  detail: {                  // клик → открыть детализацию (опционально)
    ctx:       'dislocation',       // контекст (ключ DETAIL_CONTEXTS)
    road:      'ГОРЬКОВСКАЯ',       // фильтр по дороге (опционально)
    kpi_type:  'idle',              // произвольный параметр для бэкенда (опционально)
    _endpoint: '/api/custom/detail' // переопределить URL, сохранив колонки ctx (опционально)
  }
}
```

### Переопределение эндпоинта для KPI (`_endpoint`)

Если на стороне Oracle данные считаются в пакете и требуют отдельного API, но структура колонок совпадает с существующим контекстом — используйте `_endpoint`:

```js
// В kpi(data) функции WAGON_TABS или KPI_BOARDS:
{
  label: 'Нестандартный показатель',
  value: data.custom_value,
  detail: {
    ctx: 'dislocation',              // берём колонки из DETAIL_CONTEXTS['dislocation']
    _endpoint: '/api/custom/detail', // но данные запрашиваем отсюда
    kpi_type: 'my_type'             // произвольный доп. параметр
  }
}
```

URL страницы детализации будет:
```
/detail?ctx=dislocation&_endpoint=/api/custom/detail&kpi_type=my_type
```

---

## Справочник полей WAGON_TABS

Каждая вкладка описывается объектом в `WAGON_TABS` (`app.js`).

### Обязательные поля

| Поле           | Тип      | Описание                                                                                          |
| -------------- | -------- | ------------------------------------------------------------------------------------------------- |
| `ctx`          | string   | Идентификатор. Должен совпадать с ключом `DETAIL_CONTEXTS`                                        |
| `summaryUrl`   | string   | URL сводного API (`/api/<раздел>/summary`)                                                        |
| `detailUrl`    | string   | URL расширенной таблицы (`/api/<раздел>/detail`)                                                  |
| `sumTableId`   | string   | `id` элемента `<table>` сводной (нужен для CSV-экспорта)                                          |
| `sumSubId`     | string   | `id` подписи под заголовком сводной («Итого: N»)                                                  |
| `detTableId`   | string   | `id` контейнера расширенной таблицы (виртуальный скролл)                                          |
| `detPanelId`   | string   | `id` inner-панели расширенной таблицы (для ленивой загрузки)                                      |
| `loadedKey`    | string   | Глобальный флаг первой загрузки сводной (`window[loadedKey]`)                                     |
| `loadedDetKey` | string   | Глобальный флаг первой загрузки расширенной                                                       |
| `sumSubLabel`  | string   | Префикс подписи итогов («Всего в подходе»)                                                        |
| `groupCols`    | array    | Строки сводной таблицы: `[{ key, label }]`. Первый — верхний уровень (дорога), последний — нижний (станция). Передаётся как `group_by` в summary и detail. |
| `getParams()`  | function | Возвращает объект `{ param: value }` активных фильтров формы                                      |

### Опциональные поля

| Поле                | Тип      | Описание                                                                                          |
| ------------------- | -------- | ------------------------------------------------------------------------------------------------- |
| `filtersUrl`        | string   | URL для заполнения `<select>` фильтров. Если не задан — фильтры не загружаются                   |
| `fillFilters(data)` | function | Заполняет `<select>` элементы данными из `filtersUrl`                                             |
| `resetFilters()`    | function | Сбрасывает фильтры в исходное состояние                                                           |
| `applyBtnId`        | string   | `id` кнопки «Применить» (если задан — загрузка данных только по нажатию, не при открытии вкладки) |
| `metricsId`         | string   | `id` контейнера KPI-карточек. Если не задан — KPI не рендерятся на вкладке                       |
| `metricsLabel`      | string   | Подпись главной KPI-карточки (если `kpi` не задан)                                               |
| `kpi(data)`         | function | Генерирует массив `[{label, value, accent?, detail?}]` из ответа сводного API                    |
| `csvFilename`       | string   | Имя-префикс CSV сводной таблицы. Если не задан — кнопка «↓ CSV» не появляется                   |
| `csvDetFilename`    | string   | Имя-префикс CSV расширенной таблицы. Если не задан — кнопка не появляется                        |
| `colDims`           | array    | Колонки сводной. `{ key, paramName }` — реальное поле → URL-параметр фильтра. `{ key, synthetic: true }` — метка-псевдоколонка, не передаётся как фильтр |
| `detSubId`          | string   | `id` подписи под заголовком расширенной («Строк: N»)                                              |
| `draw(data, cfg)`   | function | Переопределяет стандартный рендер сводной. Нужен когда структура ответа API нестандартна. Пример: Дислокация использует `{sections, cols}` и рендерит через `drawMain` |
| `listParams()`      | function | Переопределяет параметры запроса к `detailUrl`                                                    |

### Поля элемента `colDims[]`

| Поле        | Описание                                                          |
| ----------- | ----------------------------------------------------------------- |
| `key`       | Имя поля в реестре `resolveColDims` (PHP) или поле БД            |
| `paramName` | URL-параметр, передаваемый при drill-down (клик по ячейке)       |
| `synthetic` | `true` → только метка (не передаётся в фильтрах), не поле БД     |

---

## Справочник полей DETAIL_CONTEXTS

Хранится в `public/assets/js/detail-contexts.js`. Используется и в расширенной таблице главной страницы, и на странице `/detail`.

### Структура объекта контекста

```js
DETAIL_CONTEXTS['my-ctx'] = {
  label:    'Заголовок страницы',      // строка
  endpoint: '/api/my-ctx/detail',      // путь (без BASE)
  cols:     [ /* массив колонок */ ],
  sort:     { field: 'wagon_no', type: 'number', dir: 'asc' }
             // или массив: [{ field, type, dir }, ...]
}
```

### Поля колонки `cols[]`

| Поле         | Тип      | Описание                                                                         |
| ------------ | -------- | -------------------------------------------------------------------------------- |
| `key`        | string   | Имя поля в ответе API. Передаётся в `?fields=` запроса                           |
| `label`      | string   | Заголовок столбца                                                                |
| `w`          | number   | Начальная ширина столбца в пикселях (подсказка, колонки масштабируются)           |
| `meta`       | boolean  | Серая «мета» стилизация ячейки (вспомогательные поля)                            |
| `right`      | boolean  | Выравнивание значения по правому краю (числа, км)                                |
| `danger`     | boolean  | Цветовая индикация: ≥ 3 сут. → оранжевый, ≥ 7 сут. → красный (для простоев)     |
| `type`       | string   | `'number'` → числовая сортировка при передаче `sort_type`                        |
| `fmt(v)`     | function | Функция форматирования значения ячейки. Принимает сырое значение, возвращает строку |
| `formatData` | string   | Маска формата Oracle (`'DD.MM.YYYY HH24:MI:SS'`). Автоматически конвертируется в `fmt` через `oracleMaskFmt()` |

### `formatData` — форматирование дат

Задаётся в `detail-contexts.js` для колонок с датами:

```js
{
  key:        'oper_dt',
  label:      'Дата операции',
  meta:       true,
  w:          90,
  formatData: 'DD.MM.YYYY HH24:MI:SS'
}
```

Работает на двух уровнях:

**Frontend** (`app.js`, `detail.php`): в начале `showTable()` каждая колонка с `formatData` (без явного `fmt`) получает `fmt = (v) => oracleMaskFmt(v, mask)`. `oracleMaskFmt` парсит ISO (`YYYY-MM-DD HH:MM:SS`) и русский (`DD.MM.YYYY HH:MM:SS`) форматы и применяет маску.

**Backend** (`ApiController::applyFormat`): если `formatData` задан в PHP-массиве `$colDef`, выражение оборачивается в `TO_CHAR(expr, 'mask')` в SELECT и GROUP BY. Это нужно только для серверных агрегатных колонок. Для колонок из `detail-contexts.js` форматирование происходит только на фронтенде.

---

## Принцип «один источник правды»

| Что                       | Где задаётся                     | Куда попадает                                  |
| ------------------------- | -------------------------------- | ---------------------------------------------- |
| Строки сводной            | `WAGON_TABS[ctx].groupCols[].key` | `?group_by=` → SQL `GROUP BY / ORDER BY / WHERE` |
| Колонки сводной           | `WAGON_TABS[ctx].colDims[].key`  | `?col_by=` → `resolveColDims()` → SQL `GROUP BY` |
| Поля SELECT детализации   | `DETAIL_CONTEXTS[ctx].cols[].key` | `?fields=` → `selectFields()` → SQL `SELECT`   |
| Сортировка детализации    | `DETAIL_CONTEXTS[ctx].sort`      | `?sort=&sort_dir=&sort_type=`                  |
| Тип вагона (Oracle функция) | `ApiController::WAG_TYPE_EXPR` | Все summary и detail методы через константу    |
| Валидация полей           | `isSafeField()` + `user_tab_columns` | Единственная точка безопасности              |

---

## Как добавить новый раздел

Пример: раздел «Прибытие вагонов».

### 1. Маршруты (`src/routes.php`)

```php
$group->get('/api/arrived/summary', function ($req, $res) use ($getDb) {
    return (new \App\Controllers\ApiController($getDb()))->arrivedSummary($req, $res);
});
$group->get('/api/arrived/detail', function ($req, $res) use ($getDb) {
    return (new \App\Controllers\ApiController($getDb()))->arrivedDetail($req, $res);
});
```

### 2. API-методы (`src/Controllers/ApiController.php`)

Добавьте приватный метод `arrivedFrom()` по образцу существующих, затем `arrivedSummary` и `arrivedDetail`:

```php
public function arrivedSummary(Request $req, Response $res): Response
{
    $params = $req->getQueryParams();
    $base   = $this->arrivedFrom($params);
    if (!$base['reportDt']) {
        return $this->json($res, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
    }
    $rowDims = $this->resolveGroupBy($params['group_by'] ?? '', ['oper_road', 'oper_station']);
    $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
    return $this->json($res, $this->summaryReport($base, $rowDims, $colDefs));
}

public function arrivedDetail(Request $req, Response $res): Response
{
    $params   = $req->getQueryParams();
    $base     = $this->arrivedFrom($params);
    $bindings = $base['bindings'];
    $gf       = $this->resolveGroupBy($params['group_by'] ?? '', ['oper_road', 'oper_station']);
    $where    = $this->applyGfFilters($gf, $params['road'] ?? null, $params['station'] ?? null, $params, $bindings);
    $select   = $this->selectFields($params['fields'] ?? '');
    $rows     = $this->db->fetchAll(
        "SELECT $select FROM {$base['from']} WHERE 1=1 $where ORDER BY " . implode(', ', $gf),
        $bindings
    );
    return $this->json($res, ['rows' => $rows]);
}
```

### 3. Навигация (`public/assets/js/app.js`)

В `TAB_GROUPS` добавьте запись в нужную группу:

```js
{ id: 'arrived', label: 'Прибытие вагонов' }
```

### 4. Конфиг вкладки (`WAGON_TABS` в `app.js`)

```js
arrived: {
  ctx:           'arrived',
  filtersUrl:    BASE + '/api/approach/filters',
  summaryUrl:    BASE + '/api/arrived/summary',
  detailUrl:     BASE + '/api/arrived/detail',
  metricsId:     'arrivedMetrics',
  kpi: function (data) {
    return [{ label: 'Всего прибыло', value: data.total, accent: true, detail: { ctx: 'arrived' } }];
  },
  csvFilename:    'прибытие',
  csvDetFilename: 'прибытие-расширенная',
  sumTableId:     'arrivedSumTable',
  sumSubId:       'arrivedSumSub',
  detTableId:     'arrivedDetTable',
  detSubId:       'arrivedDetSub',
  detPanelId:     'arrived-detail',
  loadedKey:      '_arrivedLoaded',
  loadedDetKey:   '_arrivedDetLoaded',
  sumSubLabel:    'Всего',
  groupCols: [
    { key: 'oper_road',    label: 'Дорога операции' },
    { key: 'oper_station', label: 'Станция операции' },
  ],
  colDims: [
    { key: 'wagon_type_code', paramName: 'wagon_type' },
  ],
  getParams:    function () { return { cargo: $('#fArrivedCargo').val() || undefined }; },
  fillFilters:  function (data) { fillSelect('#fArrivedCargo', data.cargo || []); },
  resetFilters: function () { $('#fArrivedCargo').val(''); },
},
```

### 5. HTML-панель (`templates/app.php`)

Добавьте по образцу `panel-approach`:

```html
<div class="tab-panel" id="panel-arrived">
  <div id="arrivedMetrics" class="kpi-grid" style="margin-bottom:16px"></div>
  <!-- фильтры, inner-tabs, таблица -->
  <div class="inner-panel active" id="arrived-summary">
    <div class="table-toolbar">
      <div class="table-info">
        <span class="table-title">Прибытие вагонов</span>
        <span class="table-sub" id="arrivedSumSub"></span>
      </div>
      <div class="table-acts"></div>
    </div>
    <div class="table-scroll"><table class="data-table" id="arrivedSumTable"></table></div>
  </div>
  <div class="inner-panel" id="arrived-detail">
    <div class="table-toolbar">
      <div class="table-info"><span class="table-sub" id="arrivedDetSub"></span></div>
      <div class="table-acts"></div>
    </div>
    <div id="arrivedDetTable"></div>
  </div>
</div>
```

### 6. Колонки детализации (`public/assets/js/detail-contexts.js`)

```js
arrived: {
  label:    'Прибытие вагонов',
  endpoint: '/api/arrived/detail',
  sort:     { field: 'wagon_no', type: 'number', dir: 'asc' },
  cols: [
    { key: 'wagon_no',        label: '№ вагона',     meta: true, type: 'number', w: 110 },
    { key: 'wagon_type_code', label: 'Тип',           meta: true, w: 90 },
    { key: 'cargo_name',      label: 'Груз',          meta: true, w: 150 },
    { key: 'oper_station',    label: 'Тек. станция',  meta: true, w: 150 },
    { key: 'idle_time_days',  label: 'Простой (дн.)', danger: true, w: 105 },
    {
      key:        'oper_dt',
      label:      'Дата операции',
      meta:       true,
      w:          130,
      formatData: 'DD.MM.YYYY HH24:MI:SS'   // авто-форматирование даты
    },
  ],
},
```

---

## Вспомогательные функции (`app.js`)

| Функция                                                      | Описание                                                        |
| ------------------------------------------------------------ | --------------------------------------------------------------- |
| `esc(str)`                                                   | HTML-экранирование                                              |
| `ajaxErr(jqXHR)`                                             | Читаемое сообщение об ошибке из JSON или статус HTTP            |
| `idleStyle(days)`                                            | Inline-стиль для простоя: красный ≥7 сут., оранжевый ≥3 сут.   |
| `kpiCard(item)`                                              | HTML одной KPI-карточки                                         |
| `showDashKpi(data)`                                          | Рендер блока KPI из ответа `/api/dashboard`                     |
| `oracleMaskFmt(v, mask)`                                     | Форматирование даты по маске Oracle (`DD.MM.YYYY HH24:MI:SS`)   |
| `showTable($container, rows, colDefs)`                       | Виртуальный скролл-рендер (расширенная таблица на главной)      |
| `drawSummary(selector, roads, data, ctx, groupCols)`         | Стандартная сводная таблица дорога → станция                    |
| `drawMain(sections, cols)`                                   | Сводная таблица Дислокации (раздел → тип парка, особая структура) |
| `exportTableToCSV(tableId, filename)`                        | Скачать `<table>` как CSV (UTF-8 BOM)                           |
| `saveCSVfromVT(vtId, filename)`                              | Скачать виртуальную таблицу как CSV                             |
| `addColumnSearch($table)`                                    | Строка быстрого поиска под заголовком таблицы                   |
| `openDetail(ctx, road, station, col, groupBy, subs, extra)`  | Открыть страницу `/detail` в новой вкладке                      |
| `fillSelect(selector, values)`                               | Заполнить `<select>` списком строк                              |
| `initTab(cfg)`                                               | Загрузить данные вкладки (фильтры + сводная)                    |
| `loadDetail(cfg, extra)`                                     | Загрузить расширенную таблицу (ленивая загрузка)                |

---

## Конфигурация (.env)

```dotenv
APP_NAME=ПАО «Метафракс»
DB_DRIVER=oracle          # oracle | postgres
DB_HOST=127.0.0.1
DB_PORT=1521
DB_NAME=ORCL
DB_USER=user
DB_PASS=pass
AD_ENABLED=false          # true → проверяет AD, при неудаче — локальный пароль
AD_HOST=ldap://ad.company.local
AD_DOMAIN=company.local
AD_BASE_DN=DC=company,DC=local
SESSION_NAME=disl_session
APP_BASE_PATH=            # пусто для корня; /subdir для подпапки
APP_DEBUG=false           # true → SQL-лог в tmp/log/sql_debug.log
```

---

## Управление пользователями

```bash
# Создать нового пользователя
php bin/create-user.php admin "Иван Иванов" admin@company.local secretpass

# Сменить пароль существующему
php bin/set-password.php admin newpass
```

---

## Запуск

### Требования

| Компонент      | Версия / Примечание                                                        |
| -------------- | -------------------------------------------------------------------------- |
| PHP            | 8.1+                                                                       |
| Расширения PHP | `oci8` (Oracle) **или** `pdo_pgsql` (PostgreSQL), `ldap` (если AD включён) |
| Oracle client  | Instant Client 19+ (нужен для oci8)                                        |
| Composer       | 2.x                                                                        |

### Вариант 1 — Docker (быстрый старт с PostgreSQL)

```bash
cp .env.example .env
# Отредактируйте .env:
# DB_DRIVER=postgres
# DB_HOST=postgres
# DB_PORT=5432
# DB_NAME=disl_rzd
# DB_USER=postgres
# DB_PASS=postgres

docker compose up -d
# Приложение доступно на http://localhost:8080
```

> Для Oracle в Docker:
> ```dotenv
> DB_DRIVER=oracle
> DB_HOST=oracle
> DB_PORT=1521
> DB_NAME=FREE
> DB_USER=xx_etw
> DB_PASS=xx_etw123
> ```

### Вариант 2 — Apache + PHP (продакшн)

```bash
cp .env.example .env      # заполнить DB_* и при необходимости AD_*
composer install --no-dev --optimize-autoloader
```

Конфиг виртуального хоста Apache:

```apacheconf
<VirtualHost *:80>
    ServerName disl.company.local
    DocumentRoot /var/www/disl-rjd-new/public

    <Directory /var/www/disl-rjd-new/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Включите модули: `mod_rewrite`, `mod_headers`. Для подпапки задайте в `.env`:

```dotenv
APP_BASE_PATH=/rjd
```

### Вариант 3 — PHP built-in server (разработка)

```bash
cp .env.example .env
composer install
php -S localhost:8080 -t public/
```

macOS + Oracle Instant Client:

```bash
DYLD_LIBRARY_PATH=/opt/oracle/instantclient_23_26 php -S localhost:8080 -t public/
```

---

## Отладка SQL-запросов

Включите в `.env`:

```dotenv
APP_DEBUG=true
```

Каждый `SELECT` будет записываться в `tmp/log/sql_debug.log` с подставленными значениями.

```bash
tail -f tmp/log/sql_debug.log
```

Пример записи:

```sql
[2026-06-12 18:40:00]
SELECT wagon_no, train_no, oper_station FROM xx_dislocation_rjd
WHERE report_dt BETWEEN '2026-06-12' AND '2026-06-12'
  AND dest_road = 'ГОРЬКОВСКАЯ'
  AND FNC_MAPPING_WAG_TYPE(wagon_type_code) = 'ПЛ'
ORDER BY dest_road, dest_station
```

> Не забудьте вернуть `APP_DEBUG=false` после отладки.
