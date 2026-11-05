# Редактор конфигурации отчётов и исходных колонок

## Цель доработки

Перенести декларативные настройки из `src/Reports/ReportConfig.php` и
`src/Reports/source-columns.php` в Oracle и предоставить пользователю `SUPER`
интерфейс для их ведения.

После перехода опубликованная версия в БД должна стать единственным источником
истины для меню, маршрутов отчётов, фильтров, таблиц, экспорта и рассылок.
PHP-файлы не должны оставаться параллельным активным источником настроек.

В коде должны остаться только:

- загрузка, кеширование и строгая валидация конфигурации из БД;
- серверные проверки прав и входных данных;
- реализация SQL и бизнес-логики отчётов;
- белые списки допустимых PHP-обработчиков и типов компонентов;
- миграция начальных данных из существующих PHP-реестров.

Произвольный PHP-код, SQL, имя класса или callback нельзя вводить в браузере.
Исполняемый обработчик выбирается по стабильному коду из белого списка в коде.

## Предлагаемая модель данных

Все таблицы используют числовые surrogate keys, уникальные стабильные коды и
поля аудита. Для Oracle первичные ключи можно заполнять identity-колонками либо
существующим в проекте механизмом sequence/trigger.

### `XX_RJD_REPORT_CONFIG_VERSIONS`

Версии конфигурации позволяют готовить черновик, атомарно публиковать изменения
и откатываться к ранее опубликованному состоянию.

| Поле | Тип Oracle | Ограничение и назначение |
|---|---|---|
| `ID` | `NUMBER(19)` | PK |
| `VERSION_NO` | `NUMBER(10)` | UNIQUE, отображаемый номер версии |
| `STATUS` | `VARCHAR2(20)` | `DRAFT`, `PUBLISHED`, `ARCHIVED` |
| `COMMENT_TEXT` | `VARCHAR2(1000)` | комментарий к публикации |
| `CREATED_BY` | `NUMBER(19)` | FK на пользователя |
| `CREATED_AT` | `TIMESTAMP` | дата создания |
| `PUBLISHED_BY` | `NUMBER(19)` | nullable, кто опубликовал |
| `PUBLISHED_AT` | `TIMESTAMP` | nullable, дата публикации |
| `BASE_VERSION_ID` | `NUMBER(19)` | nullable FK на исходную версию |

Одновременно должна существовать только одна версия со статусом `PUBLISHED` и
не более одного рабочего черновика. Это обеспечивается транзакцией публикации
и серверной блокировкой строки версии.

### `XX_RJD_REPORTS`

| Поле | Тип Oracle | Ограничение и назначение |
|---|---|---|
| `ID` | `NUMBER(19)` | PK |
| `VERSION_ID` | `NUMBER(19)` | FK на версию, `ON DELETE CASCADE` логически |
| `CODE` | `VARCHAR2(64)` | стабильный код отчёта |
| `NAME` | `VARCHAR2(255)` | отображаемое название |
| `DESCRIPTION` | `VARCHAR2(2000)` | описание для администратора |
| `HANDLER_CODE` | `VARCHAR2(64)` | код обработчика из белого списка |
| `FILTERS_ENDPOINT` | `VARCHAR2(255)` | GET endpoint фильтров |
| `SUMMARY_ENDPOINT` | `VARCHAR2(255)` | GET endpoint сводной таблицы |
| `DETAIL_ENDPOINT` | `VARCHAR2(255)` | nullable GET endpoint детализации |
| `MENU_GROUP_CODE` | `VARCHAR2(64)` | группа основного меню |
| `MENU_LABEL` | `VARCHAR2(255)` | nullable, отдельная подпись меню |
| `MENU_ORDER` | `NUMBER(10)` | порядок пункта |
| `IS_VISIBLE` | `NUMBER(1)` | `0/1`, показывать в интерфейсе |
| `IS_EXPORT_ENABLED` | `NUMBER(1)` | `0/1` |
| `IS_MAILING_ENABLED` | `NUMBER(1)` | `0/1` |
| `DEFAULT_SORT_CODE` | `VARCHAR2(64)` | nullable код колонки |
| `DEFAULT_SORT_DIR` | `VARCHAR2(4)` | `ASC` или `DESC` |
| `CREATED_AT`, `UPDATED_AT` | `TIMESTAMP` | аудит строки |

Уникальность: `(VERSION_ID, CODE)`. Endpoint должен начинаться с `/api/` и быть
уникальным внутри версии. Нельзя разрешать ввод абсолютного URL.

### `XX_RJD_REPORT_FILTERS`

| Поле | Тип Oracle | Ограничение и назначение |
|---|---|---|
| `ID` | `NUMBER(19)` | PK |
| `REPORT_ID` | `NUMBER(19)` | FK на отчёт |
| `CODE` | `VARCHAR2(64)` | имя параметра API |
| `LABEL` | `VARCHAR2(255)` | подпись поля |
| `FILTER_TYPE` | `VARCHAR2(32)` | тип из белого списка: `date`, `select`, `text`, `number`, `checkbox` |
| `SOURCE_CODE` | `VARCHAR2(64)` | nullable источник значений из белого списка |
| `DEFAULT_VALUE` | `VARCHAR2(1000)` | nullable декларативное значение |
| `IS_REQUIRED` | `NUMBER(1)` | `0/1` |
| `IS_MULTIPLE` | `NUMBER(1)` | `0/1` |
| `MIN_VALUE`, `MAX_VALUE` | `NUMBER` | nullable числовые пределы |
| `PATTERN_CODE` | `VARCHAR2(64)` | nullable именованное правило, не произвольный regex |
| `SORT_ORDER` | `NUMBER(10)` | порядок поля |

Уникальность: `(REPORT_ID, CODE)`. Серверная схема запроса строится из этих
данных, но сервер всё равно независимо валидирует каждый входящий запрос.

### `XX_RJD_SOURCE_COLUMNS`

Это замена `source-columns.php` — каталог физических колонок исходной справки.

| Поле | Тип Oracle | Ограничение и назначение |
|---|---|---|
| `ID` | `NUMBER(19)` | PK |
| `VERSION_ID` | `NUMBER(19)` | FK на версию |
| `CODE` | `VARCHAR2(64)` | стабильный машинный код |
| `SOURCE_NAME` | `VARCHAR2(255)` | точное название в исходном файле |
| `DISPLAY_NAME` | `VARCHAR2(255)` | подпись по умолчанию |
| `DATA_TYPE` | `VARCHAR2(32)` | `string`, `integer`, `decimal`, `date`, `datetime`, `boolean` |
| `FORMAT_CODE` | `VARCHAR2(32)` | nullable формат из белого списка |
| `SOURCE_ORDER` | `NUMBER(10)` | позиция в исходной справке |
| `IS_REQUIRED` | `NUMBER(1)` | обязательность при импорте |
| `IS_ACTIVE` | `NUMBER(1)` | доступность для новых настроек |

Уникальность: `(VERSION_ID, CODE)`, `(VERSION_ID, SOURCE_ORDER)`. Переименование
`CODE` после публикации запрещается; меняется только подпись. Иначе ломаются
экспорт, сохранённые фильтры и интеграции.

### `XX_RJD_REPORT_COLUMNS`

Связь отчёта с каталогом исходных колонок и индивидуальные настройки показа.

| Поле | Тип Oracle | Ограничение и назначение |
|---|---|---|
| `ID` | `NUMBER(19)` | PK |
| `REPORT_ID` | `NUMBER(19)` | FK на отчёт |
| `SOURCE_COLUMN_ID` | `NUMBER(19)` | nullable FK на исходную колонку |
| `CODE` | `VARCHAR2(64)` | код результирующей колонки |
| `LABEL_OVERRIDE` | `VARCHAR2(255)` | nullable подпись только в этом отчёте |
| `COLUMN_KIND` | `VARCHAR2(20)` | `source` или `calculated` |
| `CALCULATOR_CODE` | `VARCHAR2(64)` | nullable обработчик из белого списка |
| `DATA_TYPE` | `VARCHAR2(32)` | тип результата |
| `FORMAT_CODE` | `VARCHAR2(32)` | nullable формат отображения |
| `WIDTH_PX` | `NUMBER(5)` | nullable ширина |
| `SORT_ORDER` | `NUMBER(10)` | порядок |
| `IS_VISIBLE` | `NUMBER(1)` | отображать по умолчанию |
| `IS_EXPORTABLE` | `NUMBER(1)` | включать в экспорт |
| `IS_SORTABLE` | `NUMBER(1)` | разрешить сортировку |

Уникальность: `(REPORT_ID, CODE)`, `(REPORT_ID, SORT_ORDER)`. Для `source`
обязателен `SOURCE_COLUMN_ID`; для `calculated` — зарегистрированный
`CALCULATOR_CODE`.

### `XX_RJD_REPORT_CONFIG_AUDIT`

| Поле | Тип Oracle | Назначение |
|---|---|---|
| `ID` | `NUMBER(19)` | PK |
| `VERSION_ID` | `NUMBER(19)` | версия |
| `ENTITY_TYPE` | `VARCHAR2(40)` | report/filter/source_column/report_column/version |
| `ENTITY_KEY` | `VARCHAR2(128)` | стабильный ключ сущности |
| `ACTION_CODE` | `VARCHAR2(20)` | create/update/delete/publish/rollback |
| `BEFORE_JSON` | `CLOB` | состояние до изменения |
| `AFTER_JSON` | `CLOB` | состояние после изменения |
| `USER_ID` | `NUMBER(19)` | автор |
| `CREATED_AT` | `TIMESTAMP` | время |

Аудит пишется сервером в той же транзакции, что и изменение черновика.

## Сервисный слой

Предлагаемые классы:

- `ReportConfigRepository` — загрузка опубликованной версии и CRUD черновика;
- `ReportConfigValidator` — полная проверка ссылок, кодов, endpoints и порядка;
- `ReportConfigPublisher` — блокировка, проверка и атомарная публикация;
- `ReportConfig` — совместимый read-only API для остального приложения;
- `ReportHandlerRegistry` — белый список обработчиков;
- `FilterSourceRegistry` — белый список источников значений фильтров;
- `ColumnCalculatorRegistry` — белый список вычисляемых колонок.

`ReportConfig::all()` и `ReportConfig::sourceColumns()` можно сохранить как API,
но они должны читать опубликованную версию через repository. Это уменьшит объём
изменений в потребителях и позволит выполнить переход поэтапно.

Загрузку опубликованной конфигурации следует кешировать на время процесса или в
общем кеше с ключом версии. После публикации кеш инвалидируется. Ошибка БД не
должна приводить к использованию устаревшего PHP-файла: допустим только явно
созданный снимок последней опубликованной версии с номером и контрольной суммой.

## Страница ведения настроек

Маршрут: `GET /admin/report-config`, изменение — только отдельными POST/API
маршрутами. Доступ проверяется на сервере через `Authorization::isSuper()`.

Страница состоит из:

1. верхней панели версии: опубликованная версия, черновик, автор и дата;
2. вкладок «Отчёты», «Исходные колонки», «История»;
3. левого списка отчётов с поиском, порядком и статусом ошибок;
4. формы выбранного отчёта;
5. внутренних вкладок «Основное», «Фильтры», «Колонки», «Маршруты»;
6. панели ошибок валидации;
7. действий «Сохранить черновик», «Проверить», «Предпросмотр», «Опубликовать»;
8. диалога подтверждения публикации с обязательным комментарием.

Для изменения порядка фильтров и колонок допустим drag-and-drop, но рядом нужны
кнопки перемещения и серверная нормализация порядка. Сохранение не публикует
изменения автоматически.

### Пример HTML-макета

Это демонстрация структуры страницы, а не готовый шаблон. В реализации следует
использовать общие header/sidebar, CSS-компоненты и CSRF-механизм проекта.

```html
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Конфигурация отчётов</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <!-- Общие header и admin-sidebar проекта -->
  <main class="main-content report-config-editor">
    <header class="ui-page-head">
      <div>
        <h1>Конфигурация отчётов</h1>
        <p>Опубликована версия 12 · черновик 13 · изменён 29.10.2026</p>
      </div>
      <div class="ui-actions">
        <button type="button" class="btn btn-ghost">Проверить</button>
        <button type="button" class="btn btn-ghost">Предпросмотр</button>
        <button type="button" class="btn btn-primary">Опубликовать</button>
      </div>
    </header>

    <nav class="ui-tabs" aria-label="Раздел конфигурации">
      <a class="is-active" href="?tab=reports">Отчёты</a>
      <a href="?tab=source-columns">Исходные колонки</a>
      <a href="?tab=history">История</a>
    </nav>

    <div class="config-layout">
      <aside class="ui-card config-list">
        <div class="ui-card-head">
          <strong>Отчёты</strong>
          <a class="btn btn-small" href="/admin/report-config/reports/new">Добавить</a>
        </div>
        <div class="ui-card-body">
          <label class="ui-field">
            <span>Поиск</span>
            <input type="search" placeholder="Код или название">
          </label>
          <ol class="sortable-list">
            <li class="is-active"><a href="?report=dislocation">Дислокация <code>dislocation</code></a></li>
            <li><a href="?report=approach">Подход вагонов <code>approach</code></a></li>
            <li class="has-error"><a href="?report=downtime">Простои <code>downtime</code></a></li>
          </ol>
        </div>
      </aside>

      <form class="ui-card config-form" method="post" action="/admin/report-config/reports/save">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
        <input type="hidden" name="version_id" value="13">
        <input type="hidden" name="report_id" value="41">

        <div class="ui-card-head">
          <div><strong>Дислокация</strong> <span class="badge">Черновик</span></div>
          <button class="btn btn-primary" type="submit">Сохранить черновик</button>
        </div>

        <div class="ui-card-body">
          <div class="validation-summary" role="alert" hidden>
            <strong>Конфигурацию нельзя опубликовать</strong>
            <ul><li>Endpoint детализации уже используется отчётом «Подход».</li></ul>
          </div>

          <nav class="ui-tabs" aria-label="Настройки отчёта">
            <button type="button" class="is-active">Основное</button>
            <button type="button">Фильтры <span class="badge">6</span></button>
            <button type="button">Колонки <span class="badge">24</span></button>
            <button type="button">Маршруты</button>
          </nav>

          <section class="config-section" aria-labelledby="general-title">
            <h2 id="general-title">Основные настройки</h2>
            <div class="ui-fields">
              <label class="ui-field"><span>Код</span><input name="code" value="dislocation" readonly></label>
              <label class="ui-field"><span>Название</span><input name="name" value="Общая дислокация" required></label>
              <label class="ui-field"><span>Группа меню</span>
                <select name="menu_group_code"><option value="movement">Движение вагонов</option></select>
              </label>
              <label class="ui-field"><span>Порядок</span><input type="number" name="menu_order" value="10" min="0"></label>
              <label class="ui-field"><span>Обработчик</span>
                <select name="handler_code"><option value="dislocation">Dislocation handler</option></select>
              </label>
              <label><input type="checkbox" name="is_visible" value="1" checked> Показывать отчёт</label>
              <label><input type="checkbox" name="is_export_enabled" value="1" checked> Разрешить экспорт</label>
              <label><input type="checkbox" name="is_mailing_enabled" value="1" checked> Разрешить рассылку</label>
            </div>
          </section>

          <section class="config-section" aria-labelledby="filters-title">
            <div class="section-head">
              <h2 id="filters-title">Фильтры</h2>
              <button type="button" class="btn btn-small">Добавить фильтр</button>
            </div>
            <table class="ui-table">
              <thead><tr><th>Порядок</th><th>Код</th><th>Подпись</th><th>Тип</th><th>Обязательный</th><th></th></tr></thead>
              <tbody>
                <tr>
                  <td><button type="button" aria-label="Переместить">↕</button></td>
                  <td><input name="filters[0][code]" value="station_id"></td>
                  <td><input name="filters[0][label]" value="Станция"></td>
                  <td><select name="filters[0][type]"><option value="select">Список</option></select></td>
                  <td><input type="checkbox" name="filters[0][required]" value="1"></td>
                  <td><button type="button" class="btn btn-danger">Удалить</button></td>
                </tr>
              </tbody>
            </table>
          </section>

          <section class="config-section" aria-labelledby="columns-title">
            <div class="section-head">
              <h2 id="columns-title">Колонки отчёта</h2>
              <button type="button" class="btn btn-small">Добавить колонку</button>
            </div>
            <table class="ui-table">
              <thead><tr><th>Порядок</th><th>Колонка</th><th>Подпись</th><th>Формат</th><th>Показывать</th><th>Экспорт</th></tr></thead>
              <tbody>
                <tr>
                  <td><input type="number" name="columns[0][order]" value="10"></td>
                  <td><select name="columns[0][source_code]"><option value="wagon_no">Номер вагона</option></select></td>
                  <td><input name="columns[0][label]" value="№ вагона"></td>
                  <td><select name="columns[0][format]"><option value="text">Текст</option></select></td>
                  <td><input type="checkbox" name="columns[0][visible]" value="1" checked></td>
                  <td><input type="checkbox" name="columns[0][exportable]" value="1" checked></td>
                </tr>
              </tbody>
            </table>
          </section>

          <section class="config-section" aria-labelledby="routes-title">
            <h2 id="routes-title">API-маршруты</h2>
            <div class="ui-fields">
              <label class="ui-field"><span>Фильтры</span><input name="filters_endpoint" value="/api/dislocation/filters"></label>
              <label class="ui-field"><span>Сводные данные</span><input name="summary_endpoint" value="/api/dislocation"></label>
              <label class="ui-field"><span>Детализация</span><input name="detail_endpoint" value="/api/dislocation/detail"></label>
            </div>
          </section>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
```

Вкладка «Исходные колонки» должна показывать таблицу с поиском, кодом,
названием исходного поля, подписью, типом, позицией и признаком обязательности.
Удаление опубликованной или используемой колонки запрещается; её можно только
деактивировать. Вкладка «История» показывает версии и позволяет `SUPER` создать
новый черновик на основе выбранной опубликованной версии.

## Серверные маршруты редактора

Рекомендуемый минимальный набор:

- `GET /admin/report-config` — интерфейс и данные текущего черновика;
- `POST /admin/report-config/draft` — создать черновик;
- `POST /admin/report-config/reports/save` — сохранить отчёт;
- `POST /admin/report-config/reports/delete` — удалить новый/неиспользуемый отчёт;
- `POST /admin/report-config/source-columns/save` — сохранить каталог колонок;
- `POST /admin/report-config/validate` — выполнить полную проверку;
- `POST /admin/report-config/publish` — опубликовать валидный черновик;
- `POST /admin/report-config/rollback` — создать черновик из старой версии.

Для всех изменяющих запросов обязательны `SUPER`, CSRF, транзакция, проверка
`VERSION_ID`/optimistic lock и запись аудита. Клиентские ограничения нужны для
удобства, но не заменяют серверную авторизацию и валидацию.

## Правила валидации перед публикацией

- коды соответствуют `^[a-z][a-z0-9_-]{1,63}$` и уникальны;
- ссылки на группы, колонки, handlers, calculators и sources существуют;
- endpoints относительные, уникальные и не конфликтуют с системными маршрутами;
- порядок элементов уникален либо нормализуется сервером;
- default sort ссылается на сортируемую колонку;
- обязательные фильтры имеют поддерживаемый тип;
- calculated-колонки используют только зарегистрированный calculator;
- рассылка включается только при наличии зарегистрированного mailing handler;
- нельзя удалить/переименовать код, используемый опубликованными интеграциями;
- итоговая конфигурация проходит существующую consistency-проверку.

## План перехода без изменения текущего поведения

1. Создать таблицы, ограничения, индексы, repository и валидатор.
2. Добавить одноразовую CLI-миграцию, читающую текущие PHP-реестры и создающую
   версию 1 со статусом `PUBLISHED`.
3. Сравнить нормализованный результат БД с `ReportConfig::all()` и
   `ReportConfig::sourceColumns()`; публикацию считать успешной только при полном
   совпадении контрактов.
4. Переключить `ReportConfig` на чтение из БД за feature flag и прогнать
   страницы, API, экспорт, импорт и рассылки.
5. Добавить read-only страницу `SUPER`, затем создание и сохранение черновика.
6. Добавить полную проверку, предпросмотр, публикацию, аудит и откат.
7. Удалить runtime-чтение `source-columns.php` и декларативный массив из
   `ReportConfig.php`. Оставить миграцию отдельно, чтобы файлы не воспринимались
   как второй источник истины.
8. Обновить `docs/single-source-of-truth.md`: единственный источник отчётных
   настроек — опубликованная версия Oracle; код хранит только исполняемые
   реализации и белые списки.

На период перехода нельзя разрешать редактирование одновременно в PHP и БД.
Feature flag выбирает ровно один источник; после приёмки режим PHP удаляется.

## Критерии готовности

- изменение подписи, порядка, фильтра или колонки выполняется в одном черновике;
- до публикации пользователи продолжают видеть предыдущую версию;
- публикация атомарна и не создаёт частично обновлённую конфигурацию;
- `ADMIN` и прямой URL не дают доступа к редактору или его POST/API;
- неверная конфигурация не может быть опубликована;
- все изменения имеют автора, время, before/after и комментарий;
- доступен безопасный откат через создание новой версии;
- меню, API, формы, таблицы, экспорт и рассылки читают одну опубликованную версию.
