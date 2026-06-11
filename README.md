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
│       └── js/app.js        # Вся клиентская логика (SPA)
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
  filtersUrl:  BASE + '/api/approach/filters',
  summaryUrl:  BASE + '/api/arrived/summary',
  detailUrl:   BASE + '/api/arrived/detail',
  metricsId:   'arrivedMetrics',
  sumTableId:  'arrivedSumTable',
  sumSubId:    'arrivedSumSub',
  detTableId:  'arrivedDetTable',
  detSubId:    'arrivedDetSub',
  detPanelId:  'arrived-detail',
  loadedKey:   '_arrivedLoaded',
  loadedDetKey:'_arrivedDetLoaded',
  metricsLabel:'Всего прибыло',
  sumSubLabel: 'Всего',
  groupCols: [
    { key: 'oper_road',    label: 'Дорога операции' },
    { key: 'oper_station', label: 'Станция операции' },
  ],
  getParams:    function () { return { cargo: $('#fArrivedCargo').val() || undefined } },
  fillFilters:  function (data) { fillSelect('#fArrivedCargo', data.cargo || []) },
  resetFilters: function () { $('#fArrivedCargo').val('') },
  detailCols: [
    { key: 'wagon_no',        label: '№ вагона',       meta: true, mono: true },
    { key: 'wagon_type_code', label: 'Тип',            meta: true },
    { key: 'cargo_name',      label: 'Груз',           meta: true },
    { key: 'oper_station',    label: 'Тек. станция',   meta: true },
    { key: 'oper_road',       label: 'Дорога',         meta: true },
    { key: 'idle_time_days',  label: 'Простой (сут.)', danger: true },
  ],
},
```

Добавление в `WAGON_TABS` + регистрация в `switchTab` и `initInnerTabs` — и раздел автоматически подхватывается всеми generic-функциями (`loadWagonTabInit`, `loadWagonTabSummary`, `loadWagonTabDetail`, `renderRoadStationTable`).

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

### 7. Страница детализации (`templates/detail.php`)

Добавить контекст в объект `CONTEXTS`:

```js
arrived: {
  label: 'Прибытие вагонов', endpoint: BASE + '/api/arrived/detail',
  cols: [
    { key: 'wagon_no',        label: '№ вагона',     meta: true, mono: true },
    { key: 'wagon_type_code', label: 'Тип',          meta: true },
    { key: 'cargo_name',      label: 'Груз',         meta: true },
    { key: 'oper_station',    label: 'Тек. станция', meta: true },
    { key: 'idle_time_days',  label: 'Простой',      right: true, danger: true },
  ]
},
```

---

## Принцип «один источник правды»

Список отображаемых полей определяется **только** в `groupCols` / `detailCols` конфига вкладки в `app.js`.

- `groupCols` → `group_by` param → SQL `GROUP BY / ORDER BY / WHERE`
- `detailCols` → `fields` param → SQL `SELECT`
- Валидация полей — через `user_tab_columns` (схема Oracle), а не хардкод в PHP

При добавлении/удалении колонок достаточно обновить конфиг в `app.js`. PHP трогать не нужно.

---

## Вспомогательные функции (`app.js`)

| Функция | Описание |
|---|---|
| `esc(str)` | HTML-экранирование |
| `ajaxErr(jqXHR)` | Читаемое сообщение об ошибке (HTTP статус + JSON detail) |
| `getDangerStyle(days)` | Стиль для простоя: красный ≥7 сут., оранжевый ≥3 сут. |
| `buildKpiCard(item)` | HTML одной KPI-карточки |
| `renderGenericDetailTable($table, rows, colDefs)` | Универсальный рендер таблицы по конфигу колонок; поддерживает `meta`, `mono`, `right`, `danger`, `fmt` |
| `renderRoadStationTable(...)` | Двухуровневая таблица дорога → станция с раскрытием |
| `renderRoadStationMetrics(...)` | KPI-карточки для сводных вкладок |
| `addColumnSearch($table)` | Строка быстрого поиска под заголовком таблицы |
| `openDetail(ctx, road, station, col, groupBy)` | Открыть детализацию в новой вкладке |
