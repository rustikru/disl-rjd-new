# Дислокация парка вагонов РЖД

Веб-приложение для просмотра дислокации, подхода, отправления и погрузки вагонов на основе XLSX-выгрузки из ЛК клиента РЖД.

---

## Стек

| Слой | Технология |
|---|---|
| Backend | PHP 8.1+, Slim Framework 4, PSR-7 |
| База данных | Oracle (OCI8) / PostgreSQL (fallback) |
| Frontend | Vanilla JS (ES5), jQuery 3, CSS-переменные |
| Аутентификация | Локальный пароль или Active Directory (LDAP) |
| Веб-сервер | Apache (mod_rewrite) / PHP built-in server |

---

## Структура проекта

```
disl-rjd-new/
├── public/
│   ├── index.php            # Точка входа (bootstrap)
│   ├── .htaccess            # mod_rewrite → index.php
│   └── assets/
│       ├── css/app.css      # Основные стили
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
│   │   └── ImportController.php     # Загрузка XLSX
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
│   ├── import.php   # Форма загрузки XLSX
│   └── login.php    # Форма входа
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

| Поле | Тип | Описание |
|---|---|---|
| `id` | NUMBER | Автоинкремент (SEQ + триггер) |
| `report_dt` | TIMESTAMP | Дата и время справки |
| `type_reference` | VARCHAR2 | `'Подход'` / `'Отправка'` |
| `wagon_no` | VARCHAR2 | Номер вагона |
| `wagon_type_code` | VARCHAR2 | Тип вагона (Цистерны, Полувагоны…) |
| `depart_road` | VARCHAR2 | Дорога отправления |
| `depart_station` | VARCHAR2 | Станция отправления |
| `dest_road` | VARCHAR2 | Дорога назначения |
| `dest_station` | VARCHAR2 | Станция назначения |
| `oper_road` | VARCHAR2 | Дорога текущей операции |
| `oper_station` | VARCHAR2 | Станция текущей операции |
| `oper_mnemonic` | VARCHAR2 | Мнемоника операции (`ОТПР`, `ПРИБ`…) |
| `cargo_name` | VARCHAR2 | Наименование груза |
| `cargo_weight_kg` | NUMBER | Вес груза (кг) |
| `dist_remain_km` | NUMBER | Остаток расстояния (км) |
| `idle_time_days` | NUMBER | Простой (сут.) |
| `norm_delivery_dt` | DATE | Нормативная дата доставки |
| `owner` | VARCHAR2 | Собственник |
| `lessee` | VARCHAR2 | Арендатор |
| `park_type` | VARCHAR2 | Признак парка |
| … | … | Полный список — в `sql/oracle_xx_dislocation_rjd.sql` |

Полные комментарии на русском ко всем 126 колонкам есть в DDL-файле (`COMMENT ON COLUMN`).

### Импорт данных

`POST /import` → `ImportController::handleUpload` читает XLSX через PhpSpreadsheet и вставляет строки пакетами в `xx_dislocation_rjd`. Дата справки берётся из ячейки A2 файла.

---

## API

Все методы — GET, возвращают JSON. Защищены `AuthMiddleware`.

| URL | Метод контроллера | Описание |
|---|---|---|
| `GET /api/dashboard` | `dashboard` | KPI + разбивка по типам вагонов |
| `GET /api/reports` | `reports` | Список дат справок для дропдауна |
| `GET /api/dislocation/summary` | `dislocationSummary` | Сводная по разделам парка |
| `GET /api/dislocation/extended` | `dislocationExtended` | Детальный список |
| `GET /api/approach/summary` | `approachSummary` | Сводная подхода (группировка динамическая) |
| `GET /api/approach/detail` | `approachDetail` | Список вагонов подхода |
| `GET /api/approach/filters` | `approachFilters` | Значения для фильтров |
| `GET /api/departure/summary` | `departureSummary` | Сводная отправления |
| `GET /api/departure/detail` | `departureDetail` | Список отправленных вагонов |
| `GET /api/loading/summary` | `loadingSummary` | Сводная погрузки |
| `GET /api/loading/detail` | `loadingDetail` | Список погруженных вагонов |
| `GET /api/downtime/summary` | `downtimeSummary` | Простои по станциям |
| `GET /api/downtime/detail` | `downtimeDetail` | Список вагонов с простоем |
| `GET /api/raw-material/summary` | `rawMaterialSummary` | Сводная по сырью |
| `GET /api/raw-material/detail` | `rawMaterialDetail` | Список вагонов с сырьём |

### Параметры сводных API (approach / departure / loading)

| Параметр | Описание |
|---|---|
| `report_dt` | Фильтр по дате справки |
| `cargo` | Фильтр по наименованию груза |
| `group_by` | Поля группировки через запятую: `dest_road,dest_station` |
| `fields` | Поля SELECT для детализации через запятую |

`group_by` и `fields` формируются автоматически из `WAGON_TABS[k].groupCols` и `detailCols` в `app.js` — PHP-код менять не нужно.

Поля валидируются через `user_tab_columns` (схема БД = единственный источник правды).

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
```

---

## Запуск

### Docker

```bash
cp .env.example .env   # заполнить DB_* и AD_*
docker compose up -d
```

### Вручную (Apache + PHP)

```bash
cp .env.example .env
composer install
# DocumentRoot → public/
# AllowOverride All (для mod_rewrite)
```

### PHP built-in server (для разработки)

```bash
php -S 0.0.0.0:8080 router.php
```

---

## Как добавить новый раздел

Пример: добавить раздел «Прибытие вагонов».

### 1. SQL / данные

Таблица уже есть (`xx_dislocation_rjd`). Если нужна отдельная — создать по аналогии с `oracle_xx_dislocation_rjd.sql`.

### 2. Маршруты (`src/routes.php`)

```php
$group->get('/api/arrived/summary', function ($req, $res) use ($getDb) {
    return (new \App\Controllers\ApiController($getDb()))->arrivedSummary($req, $res);
});
$group->get('/api/arrived/detail', function ($req, $res) use ($getDb) {
    return (new \App\Controllers\ApiController($getDb()))->arrivedDetail($req, $res);
});
```

### 3. API-методы (`src/Controllers/ApiController.php`)

Скопируйте `approachSummary` / `approachDetail` и поменяйте:
- `buildApproachWhere(...)` → свой `buildArrivedWhere(...)`
- дефолтные поля в `parseGroupFields($params['group_by'] ?? '', ['oper_road', 'oper_station'])`

```php
public function arrivedSummary(Request $req, Response $res): Response
{
    $params  = $req->getQueryParams();
    $reportDt = $params['report_dt'] ?? null;
    if (!$reportDt) {
        return $this->json($res, ['cols'=>[], 'roads'=>[], 'metrics'=>[], 'total'=>0]);
    }
    $gf    = $this->parseGroupFields($params['group_by'] ?? '', ['oper_road', 'oper_station']);
    $gfStr = implode(', ', $gf);
    [$where, $bindings] = $this->buildArrivedWhere($reportDt, $params['cargo'] ?? null);
    $rows = $this->db->fetchAll(
        "SELECT $gfStr, wagon_type_code, COUNT(*) AS cnt
         FROM xx_dislocation_rjd WHERE $where
         GROUP BY $gfStr, wagon_type_code ORDER BY $gfStr, wagon_type_code",
        $bindings
    );
    return $this->json($res, $this->buildRoadStationTable($rows, $gf));
}
```

### 4. Навигация (`public/assets/js/app.js`)

В массиве `TAB_GROUPS` раскомментируйте или добавьте вкладку:

```js
{ id: 'arrived', label: 'Прибытие вагонов' },
```

### 5. Конфиг вкладки (`WAGON_TABS` в `app.js`)

```js
arrived: {
  ctx: 'arrived',
  filtersUrl:   BASE + '/api/approach/filters',
  summaryUrl:   BASE + '/api/arrived/summary',
  detailUrl:    BASE + '/api/arrived/detail',
  metricsId:    'arrivedMetrics',    // опционально — без него KPI не рендерится
  metricsLabel: 'Всего прибыло',
  csvFilename:  'прибытие',          // опционально — без него кнопки CSV нет
  sumTableId:   'arrivedSumTable',
  sumSubId:     'arrivedSumSub',
  detTableId:   'arrivedDetTable',
  detSubId:     'arrivedDetSub',
  detPanelId:   'arrived-detail',
  loadedKey:    '_arrivedLoaded',
  loadedDetKey: '_arrivedDetLoaded',
  sumSubLabel:  'Всего',
  groupCols: [
    { key: 'oper_road',    label: 'Дорога операции' },
    { key: 'oper_station', label: 'Станция операции' },
  ],
  getParams:    function () { return { cargo: $('#fArrivedCargo').val() || undefined } },
  fillFilters:  function (data) { fillSelect('#fArrivedCargo', data.cargo || []) },
  resetFilters: function () { $('#fArrivedCargo').val('') },
},
```

Колонки детализации добавляются в `detail-contexts.js` (не в `WAGON_TABS`) — см. раздел ниже.

Добавление в `WAGON_TABS` — и раздел автоматически подхватывается всеми generic-функциями.

### 6. HTML-панель (`templates/app.php`)

Добавить панель по аналогии с `panel-approach`:

```html
<div id="panel-arrived" class="tab-panel">
  <div class="page-body">
    <!-- фильтры -->
    <div class="filter-bar">
      <select id="fArrivedCargo"><option value="">Все грузы</option></select>
      <button id="btnArrivedApply">Применить</button>
      <button id="btnArrivedReset">Сброс</button>
    </div>
    <!-- KPI + сводная таблица -->
    <div id="arrivedMetrics" class="kpi-grid"></div>
    <div class="inner-tabs">
      <button class="inner-tab active" data-inner="arrived-summary">Сводная</button>
      <button class="inner-tab" data-inner="arrived-detail">Детализация</button>
    </div>
    <div class="inner-panel active" id="arrived-summary">
      <table class="data-table" id="arrivedSumTable"></table>
    </div>
    <div class="inner-panel" id="arrived-detail">
      <table class="data-table" id="arrivedDetTable"></table>
    </div>
  </div>
</div>
```

### 7. Колонки детализации (`public/assets/js/detail-contexts.js`)

Единственное место для описания колонок детализации — используется и в `app.js`, и в `templates/detail.php`.

```js
arrived: {
  label: 'Прибытие вагонов',
  endpoint: '/api/arrived/detail',   // без BASE — он добавляется автоматически
  cols: [
    { key: 'wagon_no',        label: '№ вагона',     meta: true, mono: true },
    { key: 'wagon_type_code', label: 'Тип',          meta: true },
    { key: 'cargo_name',      label: 'Груз',         meta: true },
    { key: 'oper_station',    label: 'Тек. станция', meta: true },
    { key: 'idle_time_days',  label: 'Простой',      right: true, danger: true },
  ],
},
```

---

## Справочник полей WAGON_TABS

Каждая вкладка (подход / отправление / погрузка) описывается объектом в `WAGON_TABS` (`app.js`).

### Обязательные поля

| Поле | Тип | Описание |
|---|---|---|
| `ctx` | string | Идентификатор контекста. Должен совпадать с ключом в `DETAIL_CONTEXTS` (`detail-contexts.js`) |
| `summaryUrl` | string | URL сводного API (`/api/xxx/summary`) |
| `detailUrl` | string | URL детального API (`/api/xxx/detail`) |
| `filtersUrl` | string | URL API фильтров (`/api/xxx/filters`) |
| `sumTableId` | string | `id` элемента `<table>` сводной таблицы в `app.php` |
| `sumSubId` | string | `id` элемента с подписью под заголовком сводной таблицы |
| `detTableId` | string | `id` элемента `<table>` детализации |
| `detSubId` | string | `id` элемента с подписью под заголовком детализации |
| `detPanelId` | string | `id` inner-панели детализации (определяет активность вкладки) |
| `loadedKey` | string | Имя глобальной переменной-флага первой загрузки сводной (напр. `'_approachLoaded'`) |
| `loadedDetKey` | string | Имя глобальной переменной-флага первой загрузки детализации |
| `sumSubLabel` | string | Префикс подписи итогов: `'Всего в подходе'` → `'Всего в подходе: 142 ваг.'` |
| `groupCols` | array | Колонки группировки строк таблицы. Элемент: `{ key, label }` — `key` = поле БД, `label` = заголовок. Порядок = иерархия: первый — верхний уровень (дорога), последний — нижний (станция) |
| `getParams()` | function | Возвращает объект параметров фильтров для API (значения из `<select>` / `<input>`) |
| `fillFilters(data)` | function | Заполняет `<select>` фильтров значениями из ответа `filtersUrl` |
| `resetFilters()` | function | Сбрасывает `<select>` фильтров в пустое значение |

### Опциональные поля

| Поле | Тип | Описание |
|---|---|---|
| `metricsId` | string | `id` контейнера KPI-карточек. Если не задан — карточки не рендерятся |
| `metricsLabel` | string | Подпись главной KPI-карточки. Используется когда `buildMetrics` не задан |
| `buildMetrics(data)` | function | Переопределяет набор KPI-карточек. Получает полный ответ API, возвращает `[{label, value, accent?}]`. По умолчанию — одна общая карточка + карточки по дорогам из `data.metrics` |
| `csvFilename` | string | Префикс имени CSV-файла (`'подход'` → `подход_2026-06-11.csv`). Если не задан — кнопка «↓ CSV» не появляется |

### Поля элемента groupCols

| Поле | Описание |
|---|---|
| `key` | Имя поля в БД (lowercase). Передаётся в `group_by` параметр API |
| `label` | Заголовок колонки в таблице |

### Колонки детализации — не в WAGON_TABS

Вынесены в `public/assets/js/detail-contexts.js` (объект `DETAIL_CONTEXTS`). Один файл используется и на главной странице (drill-down), и на странице `/detail`.

| Поле колонки | Описание |
|---|---|
| `key` | Имя поля в БД |
| `label` | Заголовок |
| `meta` | `true` → серая «мета» стилизация ячейки |
| `mono` | `true` → моноширинный шрифт (для номеров вагонов) |
| `right` | `true` → выравнивание по правому краю |
| `danger` | `true` → красный цвет ≥7 сут., оранжевый ≥3 сут. (для простоев) |
| `fmt(v)` | Функция форматирования значения (только в JS-файле, не в JSON) |

---

## Принцип «один источник правды»

- `groupCols[].key` → `group_by` param → SQL `GROUP BY / ORDER BY / WHERE`
- `DETAIL_CONTEXTS[ctx].cols[].key` → `fields` param → SQL `SELECT`
- Валидация полей — через `user_tab_columns` (схема Oracle), не хардкод в PHP

При добавлении/удалении колонок достаточно обновить `detail-contexts.js`. PHP трогать не нужно.

---

## Вспомогательные функции (`app.js`)

| Функция | Описание |
|---|---|
| `esc(str)` | HTML-экранирование |
| `ajaxErr(jqXHR)` | Читаемое сообщение об ошибке (HTTP статус + JSON detail) |
| `idleStyle(days)` | Inline-стиль для простоя: красный ≥7 сут., оранжевый ≥3 сут. |
| `kpiCard(item)` | HTML одной KPI-карточки (`{label, value, accent?}`) |
| `showKpi(selector, metrics, total, label)` | Рендер блока KPI-карточек |
| `showTable($table, rows, cols)` | Универсальный рендер таблицы по конфигу колонок (`detail-contexts.js`) |
| `drawSummary(selector, roads, data, ctx, groupCols)` | Сводная таблица дорога→станция; автоматически рендерит 1- или N-уровневую шапку по `data.cols` / `data.col_groups` |
| `exportTableToCSV(tableId, filename)` | Скачать таблицу как CSV (UTF-8 BOM) |
| `addColumnSearch($table)` | Строка быстрого поиска под заголовком таблицы |
| `openDetail(ctx, road, station, col, groupBy, subs)` | Открыть страницу детализации в новой вкладке. `subs` — массив значений под-уровней шапки (ГР/ПОР и т.д.), передаются как `cargo_state`, … через `SUB_PARAM_NAMES` |
