# Команды разработчика и диагностика

Команды ниже подходят для типичной локальной среды PHP-проекта. Некоторые команды требуют наличия Composer, Node.js, Oracle Client или утилит, отсутствующих в архиве. Проверяйте доступность в своей среде.

## PHP

```bash
php -v
php --ini
php -m | grep -Ei 'oci8|mbstring|json|openssl|fileinfo'
php -i | grep -E 'date.timezone|Default timezone'
php -l src/Controllers/ApiController.php
```

- `php -v` — версия PHP.
- `php --ini` — какой `php.ini` загружен.
- `php -m` — установленные расширения; для Oracle нужен OCI8.
- `php -i` — эффективные параметры PHP, включая часовой пояс.
- `php -l` — проверка синтаксиса без запуска приложения.

Для проверки часового пояса:

```bash
php -r 'echo date_default_timezone_get()." ".date("Y-m-d H:i:s").PHP_EOL;'
```

Если веб-сервер и CLI показывают разное время, проверьте PHP-FPM/Apache и CLI отдельно: у них могут быть разные конфигурации.

## Composer

В архиве нет `composer.json` и `composer.lock`, поэтому команды ниже приведены как стандартные команды, а не как проверенные на этом архиве сценарии:

```bash
composer install
composer validate
composer dump-autoload
composer check:registry
```

`composer check:registry` упоминается в `AGENTS.md`; наличие соответствующего script нужно подтвердить в `composer.json` актуального проекта.

## Node.js

```bash
node --version
node --check public/assets/js/app.js
```

`node --check` проверяет синтаксис JS, но не запускает браузерный код и не проверяет работу DOM/API.

## Git

```bash
git status --short
git diff --stat
git diff --check
git diff -- путь/к/файлу
git log --oneline -10
```

Перед правкой проверьте `git status`, чтобы не затереть чужие изменения. После правки проверьте diff.

## Поиск кода

```bash
grep -Rni "methodName" src public/assets/js
 grep -Rni "route-fragment" src/routes.php src/Services/PageRegistry.php
 grep -Rni "XX_RJD_TABLE" src db
find src -type f -name '*.php' | sort
```

Если установлен `rg` (ripgrep), удобнее:

```bash
rg "methodName" src public/assets/js
rg "XX_RJD_" src db
```

## Oracle

В SQL Developer:

```sql
SELECT SYSDATE FROM dual;
SELECT USER FROM dual;
SELECT object_name, object_type, status
  FROM user_objects
 WHERE object_name LIKE 'XX_RJD%'
 ORDER BY object_type, object_name;
```

Проверка ошибок компиляции пакета:

```sql
SELECT name, type, line, position, text
  FROM user_errors
 WHERE name = 'XX_RJD_DISLOCATION_NEW_PKG'
 ORDER BY sequence;
```

Для полного перечня объектов/колонок используйте скрипты `db/rjd/01_inventory.sql` и `db/rjd/02_columns.sql`.

## Логи

По коду:

- ошибки приложения: `tmp/log/errors/YYYY-MM-DD.log`;
- SQL debug: `tmp/log/sql_debug.log`, если включён `app_debug`;
- auth/LDAP debug пути задаются `auth_log_file` и `ldap_log_file`.

Команды:

```bash
tail -n 100 tmp/log/errors/$(date +%F).log
grep -Rni "ERR-" tmp/log
```

Пути могут отличаться, если настройки направляют журналы в другой каталог.

## Типовые симптомы

| Симптом | Что проверить первым |
|---|---|
| 404 | маршрут в `routes.php`, `base_path`, URL и HTTP-метод |
| 403 | `PageRegistry`, роль, `PageAccessMiddleware`, видимость страницы |
| 500 | код `ERR-...`, файл `tmp/log/errors`, PHP-синтаксис |
| 503 | Oracle доступность и `DatabaseUnavailableException` |
| Пустой отчёт | активная организация, дата справки, фильтры, тип справки, область данных |
| Пустой KPI | организация MTF, `XX_RJD_KPI_TBL_V`, пакет и дата данных |
| Не сохраняются колонки | DDL `XX_RJD_TABLE_LAYOUTS*`, права и reportCode |
| Не работает карта | API координат, таблица станций, загрузка Leaflet |
| Не запускается рассылка | расписание, run-журнал, временный каталог, реальный cron/CLI |

## Важное правило диагностики

Не делайте вывод, что проблема в PHP-пакете, пока не проверены данные и Oracle. В этом проекте часть KPI и отчётной логики выполняется непосредственно в Oracle-пакете. Сравнивайте запрос в SQL Developer с теми же параметрами, которые использует приложение.
