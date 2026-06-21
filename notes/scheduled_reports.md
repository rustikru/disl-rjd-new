# Расписание отчётов — план реализации

Функция: автоматическая выгрузка сводных таблиц (Дислокация, Подход, Отправление и др.)
в Excel-файл по расписанию с последующей отправкой на почту (или сохранением в папку).

---

## Архитектура

```
Расписание (xx_rjd_report_schedules)
    ↓
Триггер (cron / webhook / poor-man's-cron)
    ↓
bin/run-reports.php
    ↓
SummaryService (вырезать из ApiController)
    ├── dislFrom()        ← Дислокация
    ├── approachFrom()    ← Подход
    ├── departureFrom()   ← Отправление
    ├── loadingFrom()     ← Погрузка
    ├── downtimeFrom()    ← Простои
    ├── rawMaterialFrom() ← Сырьё
    ├── summaryReport()   ← универсальный (работает для всех)
    └── roadTable()       ← универсальный
    ↓
SummaryExcelExporter (один для всех страниц)
    ↓
.xlsx файл → папка / почта
```

Ключевой момент: `summaryReport()` и `roadTable()` — универсальные методы,
одинаково работают для любой страницы. Разница только в источнике данных (*From()).

---

## Файлы для создания / изменения

### 1. Рефакторинг ApiController → SummaryService

**Создать:** `src/Services/SummaryService.php`

Вырезать из `ApiController` все приватные методы, которые нужны и для экспорта:

```php
namespace App\Services;

class SummaryService
{
    public function __construct(private DbInterface $db) {}

    // Источники данных (каждый возвращает ['from', 'bindings', 'reportDt'])
    public function dislFrom(array $params): array { ... }
    public function approachFrom(array $params): array { ... }
    public function departureFrom(array $params): array { ... }
    public function loadingFrom(array $params): array { ... }
    public function downtimeFrom(array $params): array { ... }
    public function rawMaterialFrom(array $params): array { ... }

    // Универсальные методы (не меняются)
    public function summaryReport(array $source, array $rowDims, array $colDefs): array { ... }
    public function roadTable(array $rows, array $rowDims, array $colAliases): array { ... }
    public function getLatestDtsByType(?string $dt, array $types): array { ... }
    public function latestDtCondition(array $dtsByType): array { ... }
}
```

**Изменить:** `src/Controllers/ApiController.php`

Заменить дублирующиеся методы на вызовы сервиса:

```php
class ApiController
{
    private SummaryService $summary;

    public function __construct(DbInterface $db) {
        $this->db      = $db;
        $this->summary = new SummaryService($db);
    }

    public function dislSummary(...): ResponseInterface
    {
        $source = $this->summary->dislFrom($params);
        $data   = $this->summary->summaryReport($source, $rowDims, $colDefs);
        return $this->json($response, $data);
    }
}
```

ApiController теперь только: берёт HTTP-параметры → вызывает сервис → возвращает JSON.
Вся логика — в SummaryService.

---

### 2. Универсальный Excel-экспортёр

**Создать:** `src/Services/SummaryExcelExporter.php`

Принимает результат `summaryReport()` и пишет .xlsx.
Один класс для всех страниц — структура данных одинакова:

```php
class SummaryExcelExporter
{
    /**
     * $data — результат SummaryService::summaryReport()
     *         структура: { col_groups|cols, roads, metrics, total }
     *
     * $meta — заголовки для файла
     *         ['title' => '...', 'report_dt' => '...', 'group_labels' => [...]]
     */
    public function export(array $data, array $meta, string $outputDir): string
    {
        // Строит .xlsx аналогично таблице на странице
        // Возвращает путь к файлу
    }
}
```

Структура Excel-файла (одна для всех страниц):

```
Строка 1:  Название отчёта
Строка 2:  "Дислокация РЖД на DD.MM.YYYY  •  Сформировано: DD.MM.YYYY"
Строка 3:  (пусто)
Строка 4:  Заголовок уровень 1 (типы вагонов, colspan)
Строка 5:  Заголовок уровень 2 (гр. / пор.)
Строки 6+: Данные (страна → дорога → станция, отступ через пробелы)
Последняя: ИТОГО
```

Цвета:
- Заголовок таблицы: `#E8E4FF`
- Страна: `#F0EDFF`
- Дорога: `#F9F8FF`
- Станция: `#FFFFFF`
- Итого: `#D8D2FF`

---

### 3. Таблица расписаний в БД

**Файл:** `db/migrations/004_report_schedules.sql`

```sql
CREATE TABLE xx_rjd_report_schedules (
  id           NUMBER        NOT NULL,
  name         VARCHAR2(200 CHAR) NOT NULL,     -- "Сводная дислокация утром"
  report_type  VARCHAR2(100) NOT NULL,           -- 'dislocation_summary', 'approach_summary', ...
  frequency    VARCHAR2(20)  NOT NULL,           -- 'daily' | 'hourly' | 'weekly'
  run_hour     NUMBER(2,0)   DEFAULT 8,          -- час запуска (0-23)
  run_minute   NUMBER(2,0)   DEFAULT 0,          -- минута запуска (0-59)
  day_of_week  NUMBER(1,0),                     -- 1=Пн..7=Вс, NULL для daily/hourly
  output_dir   VARCHAR2(500) NOT NULL,           -- куда сохранять файл
  email_to     VARCHAR2(1000),                  -- получатели через запятую (для будущей рассылки)
  is_active    NUMBER(1,0)   DEFAULT 1 NOT NULL,
  last_run_at  DATE,
  created_by   NUMBER,
  created_at   DATE DEFAULT SYSDATE NOT NULL,
  CONSTRAINT pk_xx_rjd_report_schedules PRIMARY KEY (id),
  CONSTRAINT ck_schedule_freq CHECK (frequency IN ('daily','hourly','weekly'))
);

CREATE SEQUENCE xx_rjd_schedules_seq START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;

CREATE OR REPLACE TRIGGER xx_rjd_schedules_bi
BEFORE INSERT ON xx_rjd_report_schedules
FOR EACH ROW WHEN (NEW.id IS NULL)
BEGIN
  :NEW.id := xx_rjd_schedules_seq.NEXTVAL;
END;
/
```

Поддерживаемые значения `report_type`:

| report_type | Страница | Параметры по умолчанию |
|---|---|---|
| `dislocation_summary` | Дислокация | `group_by=dest_state,dest_road,dest_station` |
| `approach_summary` | Подход вагонов | `group_by=dest_road,dest_station` |
| `departure_summary` | Отправление | `group_by=dest_road,dest_station` |
| `loading_summary` | Погрузка | `group_by=dest_road,dest_station` |
| `downtime_summary` | Простои | `group_by=dest_road,dest_station` |
| `raw_material_summary` | Сырьё | `group_by=dest_state` |

---

### 4. CLI-скрипт для cron

**Создать:** `bin/run-reports.php`

```php
// 1. Загружаем autoload + config + DB
// 2. Читаем активные расписания из xx_rjd_report_schedules
// 3. Для каждого проверяем shouldRun()
// 4. Вызываем SummaryService::*From() + summaryReport()
// 5. Передаём в SummaryExcelExporter::export()
// 6. Обновляем last_run_at
// 7. Если email_to заполнен → отправляем (PHPMailer)

function shouldRun(string $freq, int $runHour, int $runMinute, ?int $dow, ?string $lastRun): bool
{
    // daily:  прошло время запуска И сегодня ещё не запускалось
    // hourly: в текущем часу ещё не запускалось
    // weekly: как daily + совпадает день недели
}
```

---

### 5. Управление через /admin

**Создать:** `src/Controllers/ScheduleController.php`

Маршруты (возвращают JSON, находятся под auth + admin):

| Метод | URL | Действие |
|---|---|---|
| GET | `/admin/schedules/list` | Список расписаний |
| POST | `/admin/schedules/save` | Создать / обновить |
| POST | `/admin/schedules/toggle` | Вкл / выкл |
| POST | `/admin/schedules/delete` | Удалить |
| POST | `/admin/schedules/run` | Запустить вручную |

**Изменить:** `templates/admin.php`

Добавить панель "Расписание отчётов" после панели ролей:
- Таблица существующих расписаний (название, тип, частота, статус, последний запуск)
- Кнопки: Запустить / Вкл-Выкл / Удалить
- Форма добавления нового расписания

---

### 6. Папка для сохранения файлов

```
storage/
└── reports/
    ├── .gitkeep
    ├── disl_summary_2026-06-21_08-00.xlsx
    ├── approach_summary_2026-06-21_08-00.xlsx
    └── ...
```

В `.gitignore` добавить:
```
storage/reports/*.xlsx
```

---

## Способы запуска (без cron тоже можно)

### A. Cron на сервере (классика)
```bash
# Каждые 30 минут — скрипт сам разберётся что запускать
*/30 * * * * php /project/bin/run-reports.php >> /project/tmp/log/cron.log 2>&1
```

### B. Webhook (не нужен доступ к серверу)
Создать защищённый эндпоинт:
```
POST /api/reports/trigger?token=СЕКРЕТНЫЙ_КЛЮЧ
```
Внешний сервис дёргает его по расписанию:
- [cron-job.org](https://cron-job.org) — бесплатно, до минуты точность
- GitHub Actions (scheduled workflow)

```php
// src/Controllers/ScheduleController.php
public function trigger(ServerRequestInterface $request, ...): ResponseInterface
{
    $token = $request->getQueryParams()['token'] ?? '';
    if (!hash_equals($_ENV['REPORT_TRIGGER_TOKEN'] ?? '', $token)) {
        return $this->json($response, ['ok' => false], 403);
    }
    // запускаем bin/run-reports.php логику
}
```

### C. Poor man's cron (самый простой)
В middleware при каждом запросе пользователя:

```php
// src/Middleware/SchedulerMiddleware.php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
{
    // Проверяем не чаще раза в минуту (кешируем в файл)
    $lockFile = sys_get_temp_dir() . '/report_scheduler.lock';
    if (!file_exists($lockFile) || time() - filemtime($lockFile) > 60) {
        touch($lockFile);
        // Запускаем в фоне чтобы не тормозить запрос пользователя
        proc_open('php ' . ROOT . '/bin/run-reports.php', [], $pipes);
    }
    return $handler->handle($request);
}
```

Плюс: не требует ничего на сервере.
Минус: не работает если никто не заходит в систему.

---

## Расширение: реальная отправка на почту

Когда понадобится — минимальные изменения:

1. Добавить PHPMailer:
   ```
   composer require phpmailer/phpmailer
   ```

2. В `.env` добавить:
   ```
   MAIL_HOST=smtp.company.ru
   MAIL_PORT=587
   MAIL_USER=reports@company.ru
   MAIL_PASS=...
   MAIL_FROM=reports@company.ru
   ```

3. В `SummaryExcelExporter` добавить метод:
   ```php
   public function exportAndSend(array $data, array $meta, string $outputDir, string $emailTo): string
   {
       $path = $this->export($data, $meta, $outputDir);
       $this->sendMail($emailTo, $path, $meta['title']);
       return $path;
   }
   ```

4. В таблице `xx_rjd_report_schedules` уже есть поле `email_to` — заполнить и всё.

---

## Что уже готово в репозитории (ветка claude/focused-mccarthy-9cc63r)

| Файл | Статус |
|---|---|
| `db/migrations/004_report_schedules.sql` | ✓ создан |
| `src/Services/DislocationExcelExporter.php` | ✓ создан (только Дислокация, без SummaryService) |
| `src/Controllers/ScheduleController.php` | ✓ создан |
| `bin/run-reports.php` | ✓ создан |
| `storage/reports/.gitkeep` | ✓ создан |

Не подключено к маршрутам и не влияет на работу приложения.

Следующий шаг при реализации:
1. Рефакторинг `ApiController` → `SummaryService` (вынести общие методы)
2. Переписать `DislocationExcelExporter` → универсальный `SummaryExcelExporter`
3. Подключить маршруты в `routes.php`
4. Добавить панель в `templates/admin.php`
5. Применить миграцию в БД
6. Настроить cron / webhook
