# Пользовательские рассылки

## Основные классы

| Компонент | Назначение |
|---|---|
| `MailingController` | HTTP-действия пользователя и администратора |
| `MailingService` | Создание, изменение, выборка, активация, журналирование запусков |
| `MailingRun` | Формирование файлов выбранных отчётов |
| `OracleMail` | Отправка писем через Oracle-механизм |
| `ReportConfig` | Список отчётов, доступных для контекста `mailing` |
| `templates/admin/report-mailings.php` | Управление рассылками |
| `templates/admin/report-mailing-dashboard.php` | Панель/сводка рассылок |
| `public/assets/js/mailing-run.js` | Клиентские действия запуска |

## Настройки рассылки

`MailingService::save()` проверяет:

- непустое название;
- хотя бы одного корректного получателя;
- периодичность `daily`, `weekly` или `monthly`;
- время в формате `HH:MM`;
- выбор дней недели для weekly;
- день месяца от 1 до 31 для monthly;
- наличие хотя бы одного отчёта;
- обязательные фильтры отчётов из `ReportConfig::forContext('mailing')`.

Формат файла в текущем коде принудительно установлен как `xlsx`.

Запись и дочерние отчёты сохраняются в транзакции. Для редактирования запись ограничивается `mailing_id` и `user_id`, то есть пользователь может изменить собственную рассылку.

## Сохранение и журнал

Основные таблицы:

- `XX_RJD_USER_MAILINGS` — владелец, организация, расписание, получатели и последний запуск;
- `XX_RJD_USER_MAILING_REPORTS` — отчёты, фильтры и порядок/режим колонок;
- `XX_RJD_USER_MAILING_RUNS` — история запусков.

Методы `MailingService`:

- `userMailings()`, `findForUser()` — получить пользовательские рассылки;
- `save()` — создать/изменить;
- `setActive()`, `delete()` — управление;
- `dueMailings()` и `isDueOnDate()` — поиск задач по расписанию;
- `startRun()`, `finishRun()`, `recordRun()` — журналирование;
- `mailingsForAdmin()`, `runJournalForAdmin()` — административные списки.

## Расписание в ЭТОМ архиве

`dueMailings()` выбирает активные рассылки, где `send_time` точно совпадает с `DateTimeInterface::format('H:i')`, и где `last_run_at` отсутствует или относится к предыдущему дню. Затем `isDueOnDate()` проверяет календарный день:

- daily — любой день;
- weekly — номер дня недели ISO 1–7 должен присутствовать в `week_days`;
- monthly — выбранный день, ограниченный последним днём месяца.

**Важное ограничение:** в архиве нет CLI-скрипта запуска и класса `MailingScheduler`. По одному `MailingService` нельзя определить, кто и как часто вызывает `dueMailings()`. В этом снимке нельзя подтвердить запуск пропущенных рассылок после наступления времени. Нужны актуальные `bin/` и crontab/systemd с PROD.

## Запуск и отправка

`MailingRun::run()` получает массив рассылки, формирует файлы выбранных отчётов. `mail_mode` в `ConfigSchema` допускает `file` или `oracle`. `OracleMail` содержит методы `sendSystem()` и `send()`.

Фактическая доставка зависит от настроек `report_mail_enabled`, `mail_mode`, `report_storage_dir`, `report_mail_sender` и Oracle-пакета `XX_MTF_SEND_MAIL_PKG`. Значения конфигурации в архиве отсутствуют.

## Диагностика

1. Проверьте `is_active`, `schedule_type`, `send_time`, `week_days`, `month_day`.
2. Проверьте `last_run_at`, `last_run_status`, `last_run_message`.
3. Проверьте строки `XX_RJD_USER_MAILING_REPORTS`.
4. Проверьте журнал `XX_RJD_USER_MAILING_RUNS`.
5. Проверьте доступность отчёта в `ReportConfig::forContext('mailing')`.
6. Проверьте временный каталог и права на запись.
7. Для отправки через Oracle проверьте пакет и права EXECUTE.
8. Найдите исключение в `tmp/log/errors/`.
9. Для проверки расписания выясните реальный вызывающий CLI/cron. Его в этом архиве нет.
