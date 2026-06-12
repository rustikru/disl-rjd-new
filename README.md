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
| `GET /api/dislocation/filters` | `dislFilters` | Список дат справок |
| `GET /api/dislocation/summary` | `dislSummary` | Сводная по разделам парка |
| `GET /api/dislocation/detail` | `dislDetail` | Детальный список вагонов |
| `GET /api/approach/filters` | `approachFilters` | Значения для фильтров подхода |
| `GET /api/approach/summary` | `approachSummary` | Сводная подхода |
| `GET /api/approach/detail` | `approachDetail` | Список вагонов подхода |
| `GET /api/departure/filters` | `departureFilters` | Значения для фильтров отправления |
| `GET /api/departure/summary` | `departureSummary` | Сводная отправления |
| `GET /api/departure/detail` | `departureDetail` | Список отправленных вагонов |
| `GET /api/loading/summary` | `loadingSummary` | Сводная погрузки |
| `GET /api/loading/detail` | `loadingDetail` | Список погруженных вагонов |
| `GET /api/downtime/summary` | `downtimeSummary` | Простои по станциям |
| `GET /api/downtime/detail` | `downtimeDetail` | Список вагонов с простоем |
| `GET /api/raw-material/summary` | `rawSummary` | Сводная по сырью |
| `GET /api/raw-material/detail` | `rawDetail` | Список вагонов с сырьём |

### Параметры сводных API

| Параметр | Описание |
|---|---|
| `report_dt` | Фильтр по дате справки |
| `cargo` | Фильтр по наименованию груза |
| `group_by` | Поля группировки через запятую: `dest_road,dest_station` |
| `fields` | Поля SELECT для детализации через запятую |

`group_by` формируется автоматически из `WAGON_TABS[k].groupCols` в `app.js` — PHP-код менять не нужно при изменении группировки.

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

## Управление пользователями

```bash
# Создать нового пользователя
php bin/create-user.php <username> <display_name> <email> <password>

# Сменить пароль существующему
php bin/set-password.php <username> <new_password>
```

---

## Запуск

### Требования

| | |
|---|---|
| PHP | 8.1+ |
| Расширения PHP | `oci8` (Oracle) **или** `pdo_pgsql` (PostgreSQL), `ldap` (если AD включён) |
| Oracle client | Instant Client 19+ (нужен для oci8) |
| Composer | 2.x |

---

### Вариант 1 — Docker (быстрый старт с PostgreSQL)

```bash
cp .env.example .env
```

Отредактируйте `.env` — для Docker достаточно минимума:

```dotenv
DB_DRIVER=postgres
DB_HOST=postgres        # имя сервиса из docker-compose.yml
DB_PORT=5432
DB_NAME=disl_rzd
DB_USER=postgres
DB_PASS=postgres
```

```bash
docker compose up -d
# Приложение доступно на http://localhost:8080
```

> Для Oracle в Docker замените сервис на `oracle` и укажите:
> ```dotenv
> DB_DRIVER=oracle
> DB_HOST=oracle
> DB_PORT=1521
> DB_NAME=FREE
> DB_USER=xx_etw
> DB_PASS=xx_etw123
> ```

---

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

Убедитесь что включены модули: `mod_rewrite`, `mod_headers`.

Если приложение в подпапке (например `/rjd`), задайте в `.env`:
```dotenv
APP_BASE_PATH=/rjd
```

---

### Вариант 3 — PHP built-in server (разработка)

**Linux / Windows:**
```bash
cp .env.example .env
composer install
php -S localhost:8080 -t public/
```

**macOS + Oracle Instant Client:**
```bash
DYLD_LIBRARY_PATH=/opt/oracle/instantclient_23_26 php -S localhost:8080 -t public/
```

> Путь `/opt/oracle/instantclient_23_26` — замените на вашу версию Instant Client.  
> Oracle в Docker при этом запускается отдельно:
> ```bash
> docker compose up -d oracle
> # подождать ~60 сек, затем:
> docker compose logs -f oracle | grep -m1 "DATABASE IS READY"
> ```
> В `.env` указать `DB_HOST=127.0.0.1`.

---

### Создание первого пользователя

```bash
php bin/create-user.php admin "Иван Иванов" admin@company.local secretpass
```

```bash
# Сменить пароль
php bin/set-password.php admin newpass
```

---

### Отладка SQL-запросов

Включите в `.env`:

```dotenv
APP_DEBUG=true
```

После этого каждый `SELECT` будет записываться в `tmp/log/sql_debug.log` с подставленными значениями — SQL можно сразу открыть в Oracle SQL Developer / DBeaver.

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

---

## Как добавить новый раздел

Пример: добавить раздел «Прибытие вагонов».

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

Скопируйте `approachSummary` / `approachDetail` и поменяйте WHERE-условие и дефолтные поля `groupFields`.

```php
public function arrivedSummary(Request $req, Response $res): Response
{
    $params   = $req->getQueryParams();
    $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
    $gf       = $this->groupFields($params['group_by'] ?? '', ['oper_road', 'oper_station']);
    $gfStr    = implode(', ', $gf);
    // ...
    $rows = $this->db->fetchAll(
        "SELECT $gfStr, wagon_type_code, COUNT(*) AS cnt
         FROM xx_dislocation_rjd WHERE $where
         GROUP BY $gfStr, wagon_type_code ORDER BY $gfStr",
        $bindings
    );
    return $this->json($res, $this->roadTable($rows, $gf));
}
```

### 3. Навигация (`public/assets/js/app.js`)

В массиве `TAB_GROUPS` добавьте вкладку:

```js
{ id: 'arrived', label: 'Прибытие вагонов' },
```

### 4. Конфиг вкладки (`WAGON_TABS` в `app.js`)

```js
arrived: {
  ctx: 'arrived',
  filtersUrl:   BASE + '/api/approach/filters',  // опционально
  summaryUrl:   BASE + '/api/arrived/summary',
  detailUrl:    BASE + '/api/arrived/detail',
  metricsId:    'arrivedMetrics',
  metricsLabel: 'Всего прибыло',
  csvFilename:  'прибытие',
  sumTableId:   'arrivedSumTable',
  sumSubId:     'arrivedSumSub',
  detTableId:   'arrivedDetTable',
  detSubId:     'arrivedDetSub',
  detPanelId:   'arrived-detail',
  loadedKey:    '_arrivedLoaded',
  loadedDetKey: '_arrivedDetLoaded',
  sumSubLabel:  'Всего',
  groupCols: [
    { key: 'oper_road',    label: 'Дорога' },
    { key: 'oper_station', label: 'Станция' },
  ],
  getParams:    function () { return { cargo: $('#fArrivedCargo').val() || undefined } },
  fillFilters:  function (data) { fillSelect('#fArrivedCargo', data.cargo || []) },
  resetFilters: function () { $('#fArrivedCargo').val('') },
},
```

### 5. HTML-панель (`templates/app.php`)

Добавить по аналогии с `panel-approach`.

### 6. Колонки детализации (`public/assets/js/detail-contexts.js`)

```js
arrived: {
  label: 'Прибытие вагонов',
  endpoint: '/api/arrived/detail',
  cols: [
    { key: 'wagon_no',        label: '№ вагона',     meta: true, mono: true },
    { key: 'wagon_type_code', label: 'Тип',          meta: true },
    { key: 'cargo_name',      label: 'Груз',         meta: true },
    { key: 'oper_station',    label: 'Тек. станция', meta: true },
    { key: 'idle_time_days',  label: 'Простой',      right: true },
  ],
},
```

---

## Справочник полей WAGON_TABS

Каждая вкладка описывается объектом в `WAGON_TABS` (`app.js`).

### Обязательные поля

| Поле | Тип | Описание |
|---|---|---|
| `ctx` | string | Идентификатор контекста. Должен совпадать с ключом в `DETAIL_CONTEXTS` |
| `summaryUrl` | string | URL сводного API |
| `detailUrl` | string | URL детального API |
| `sumTableId` | string | `id` элемента `<table>` сводной таблицы |
| `sumSubId` | string | `id` подписи под заголовком сводной |
| `detTableId` | string | `id` элемента `<table>` детализации |
| `detSubId` | string | `id` подписи под заголовком детализации |
| `detPanelId` | string | `id` inner-панели детализации |
| `loadedKey` | string | Имя глобального флага первой загрузки сводной |
| `loadedDetKey` | string | Имя глобального флага первой загрузки детализации |
| `sumSubLabel` | string | Префикс подписи итогов |
| `groupCols` | array | Колонки группировки. `{ key, label }` — `key` = поле БД, `label` = заголовок. Порядок = иерархия: первый — верхний уровень (дорога), последний — нижний (станция). Передаётся как `group_by` в оба API (summary и detail). |
| `getParams()` | function | Возвращает объект параметров активных фильтров |

### Опциональные поля

| Поле | Тип | Описание |
|---|---|---|
| `filtersUrl` | string | URL API фильтров. Если не задан — `fillFilters` не вызывается |
| `fillFilters(data)` | function | Заполняет `<select>` фильтров данными из `filtersUrl` |
| `resetFilters()` | function | Сбрасывает фильтры. Вызывается при клике на кнопку Сброс |
| `metricsId` | string | `id` контейнера KPI-карточек. Если не задан — карточки не рендерятся |
| `metricsLabel` | string | Подпись главной KPI-карточки (используется когда `kpi` не задан) |
| `kpi(data)` | function | Переопределяет набор KPI-карточек. Получает ответ API, возвращает `[{label, value, accent?}]` |
| `csvFilename` | string | Префикс имени CSV-файла. Если не задан — кнопка «↓ CSV» не появляется |
| `draw(data, cfg)` | function | Переопределяет стандартный рендер сводной таблицы (`drawSummary`). Нужен когда структура ответа API отличается от стандартной `{roads, cols}`. Пример: Дислокация использует `{sections, cols}` и рендерится через `drawMain` |
| `listParams()` | function | Переопределяет параметры запроса к `detailUrl`. По умолчанию — `getParams()` + `fields` + `group_by` из `groupCols` |

### Поля элемента groupCols

| Поле | Описание |
|---|---|
| `key` | Имя поля в БД (lowercase). Передаётся в `group_by` |
| `label` | Заголовок колонки в таблице |

### Колонки детализации — не в WAGON_TABS

Вынесены в `public/assets/js/detail-contexts.js` (объект `DETAIL_CONTEXTS`). Используется и на главной (drill-down), и на странице `/detail`.

| Поле колонки | Описание |
|---|---|
| `key` | Имя поля в БД |
| `label` | Заголовок |
| `meta` | `true` → серая «мета» стилизация ячейки |
| `mono` | `true` → моноширинный шрифт (для номеров вагонов) |
| `right` | `true` → выравнивание по правому краю |
| `fmt(v)` | Функция форматирования значения |

---

## Принцип «один источник правды»

- `groupCols[].key` → `group_by` → SQL `GROUP BY / ORDER BY / WHERE` (в summary и detail)
- `DETAIL_CONTEXTS[ctx].cols[].key` → `fields` → SQL `SELECT`
- Валидация полей — через `user_tab_columns` (схема Oracle), не хардкод в PHP

---

## Вспомогательные функции (`app.js`)

| Функция | Описание |
|---|---|
| `esc(str)` | HTML-экранирование |
| `ajaxErr(jqXHR)` | Читаемое сообщение об ошибке |
| `idleStyle(days)` | Inline-стиль для простоя: красный ≥7 сут., оранжевый ≥3 сут. |
| `kpiCard(item)` | HTML одной KPI-карточки |
| `showKpi(selector, metrics, total, label)` | Рендер блока KPI-карточек |
| `showTable($table, rows, cols)` | Универсальный рендер таблицы по конфигу колонок |
| `drawSummary(selector, roads, data, ctx, groupCols)` | Стандартная сводная таблица дорога→станция |
| `drawMain(sections, cols)` | Сводная таблица Дислокации (раздел→тип парка, особая структура) |
| `exportTableToCSV(tableId, filename)` | Скачать таблицу как CSV (UTF-8 BOM) |
| `addColumnSearch($table)` | Строка быстрого поиска под заголовком таблицы |
| `openDetail(ctx, road, station, col, groupBy, subs, extra)` | Открыть страницу детализации в новой вкладке |
