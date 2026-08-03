# Модуль рассылок отчётов

## Состав

- `ReportCatalog` — единый список отчётов, фильтров и колонок.
- `MailingStore` — хранение рассылок, расписание и очередь запусков.
- `ReportBuilder` — получение данных и создание XLSX или CSV.
- `MailingWorker` — формирование и отправка писем из очереди.
- `bin/run_report_mailings.php` — запуск по cron.

## Установка

1. Выполнить `db/migrations/008_xx_rjd_report_mailings.sql` в Oracle 11.
2. Добавить в `rjd_config.php`:

```php
'report_mail_enabled' => true,
'report_mail_from' => 'reports@example.ru',
'report_storage_dir' => __DIR__ . '/storage/reports',
```

3. Настроить запуск каждую минуту или каждые пять минут:

```cron
*/5 * * * * php /path/to/project/bin/run_report_mailings.php >> /path/to/project/tmp/log/report_mailings.log 2>&1
```

Если `report_mail_enabled = false`, скрипт только добавляет наступившие рассылки в очередь и не отправляет письма.

## Добавление отчёта

Новый отчёт добавляется в `src/Reports/ReportCatalog.php` и в таблицу методов
`ReportBuilder::reportMethod()`. Универсальная форма строит фильтры автоматически.

Отправка письма находится в одном методе `MailingWorker::sendMail()`. При переходе
на корпоративный почтовый сервер достаточно изменить только этот метод.
