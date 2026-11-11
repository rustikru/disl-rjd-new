# Объекты Oracle и SQL-файлы

## Главный пакет дислокации

В архиве присутствуют:

- `db/XX_RJD_DISLOCATION_NEW_PKG.pks` — спецификация пакета;
- `db/XX_RJD_DISLOCATION_NEW_PKG.pkb` — тело пакета.

PHP вызывает функции этого пакета из `ApiController`, в том числе `get_kpi_row`, `set_kpi_label`, `fnc_get_kpi_trend_pct`, `fnc_get_kpi_trend_dir` и функцию сопоставления типа вагона `fnc_mapping_wag_type`.

## Почтовый пакет

`db/XX_MTF_SEND_MAIL_PKG.pks` и `.pkb` содержат спецификацию и тело почтового пакета Oracle. PHP-обёртка находится в `src/Services/OracleMail.php`. Реальное использование зависит от `mail_mode` и настроек, которых нет в архиве.

## Основные группы таблиц

Подтверждённый перечень и статус DDL подробно описаны в `docs/логика/rjd-tables.md` исходного набора документации. Ключевые группы:

| Группа | Примеры объектов |
|---|---|
| Пользователи и права | `XX_RJD_USERS`, `XX_RJD_ROLES`, `XX_RJD_USER_ROLES`, `XX_RJD_ROLE_PAGES` |
| Организации | `XX_RJD_ORGANIZATIONS`, `XX_RJD_USER_ORGANIZATIONS` |
| Области данных | `XX_RJD_DATA_SCOPES`, `XX_RJD_DATA_SCOPE_RULES`, `XX_RJD_USER_DATA_SCOPES` |
| KPI | `XX_RJD_KPI_TBL`, `XX_RJD_KPI_ROW`, `XX_RJD_KPI_TAB`, `XX_RJD_KPI_TBL_V` |
| Рассылки | `XX_RJD_USER_MAILINGS`, `XX_RJD_USER_MAILING_REPORTS`, `XX_RJD_USER_MAILING_RUNS` |
| Персональные таблицы | `XX_RJD_TABLE_LAYOUTS`, `XX_RJD_TABLE_LAYOUT_COLUMNS` |
| Дислокация | `XX_DISLOCATION_RJD` и связанные представления/пакеты |

Для многих объектов baseline DDL в репозитории отсутствует. Наличие имени в PHP не даёт полного определения структуры таблицы.

## Новые SQL-файлы в db/

В переданном архиве есть:

- `2026-11-02-approach-kpi.sql`;
- `2026-11-02-idle-control.sql`;
- `2026-11-02-page-visibility.sql`;
- `2026-11-04-table-layouts.sql`;
- `data_scopes/01_tables.sql`;
- `data_scopes/02_access_policy.sql`;
- `data_scopes/03_indexes.sql`.

Перед запуском читайте комментарии и README соответствующего каталога. Скрипты `db/old/` являются историческими/старыми; нельзя считать их безопасными для повторного выполнения.

## Диагностика состава схемы

`db/rjd/01_inventory.sql` и `db/rjd/02_columns.sql` предназначены для чтения словаря Oracle. Запускать под владельцем схемы. Они не создают и не удаляют объекты.

```sql
@01_inventory.sql
@02_columns.sql
```

Сверьте результат с документацией и кодом. Для пакетов проверьте статус компиляции в `USER_OBJECTS`/`ALL_OBJECTS`, а ошибки — в `USER_ERRORS`/`ALL_ERRORS`.

## Не путать наличие скрипта и установленное состояние

Git показывает, что SQL-файл входит в репозиторий. Он не показывает, был ли файл выполнен в TEST или PROD, под каким пользователем и завершился ли успешно. В журнале релиза фиксируйте дату, среду, исполнителя, результат и проверку после применения.
