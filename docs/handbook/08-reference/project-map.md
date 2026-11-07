# Карта файлов проекта

## Точка входа и конфигурация

| Файл | Назначение |
|---|---|
| `public/index.php` | Точка входа Slim |
| `src/Config.php` | Загрузка внешнего `rjd_config.php` и нормализация |
| `src/routes.php` | WEB/API/POST маршруты |
| `src/Navigation.php` | Конфигурация навигации |

## Контроллеры

| Файл | Основная область |
|---|---|
| `DashboardController.php` | Дашборд и карточка вагона |
| `ApiController.php` | KPI, API отчётов, детализации, экспорт и персональные layouts |
| `AdminController.php` | Административные страницы и действия |
| `AuthController.php` | Вход и Kerberos |
| `UserController.php` | Профиль и сброс пароля |
| `ImportController.php` | Импорт файлов |
| `MailingController.php` | Пользовательские и административные рассылки |
| `DataScopesController.php` | Области данных |
| `SettingsController.php` | Настройки |
| `MapsController.php` | Карта |
| `SessionsController.php` | Активные сессии |

## Сервисы

| Файл | Назначение |
|---|---|
| `PageRegistry.php` | Каталог страниц, маршрутов, прав и меню |
| `PageVisibility.php`, `PageAvailability.php` | Глобальная видимость/доступность |
| `ConfigSchema.php` | Схема и проверка настроек |
| `Organizations.php` | Организации и SQL-фильтр |
| `Authorization.php` | Проверки специальных прав |
| `DataScopes.php` | Области данных |
| `ReportService.php` | Отчётные выборки, включая контроль простоев |
| `Claims.php`, `ClaimNorms.php` | Претензии и нормативы |
| `WagonMovement.php`, `WagonCargoState.php` | Движение и состояние вагона |
| `MailingService.php`, `MailingRun.php`, `OracleMail.php` | Рассылки и отправка |
| `TableLayoutService.php` | Персональная конфигурация колонок |
| `AuditLog.php`, `SessionRegistry.php` | Аудит и сессии |
| `PasswordReset.php` | Сброс пароля |
| `Maintenance.php` | Режим технических работ |
| `ProjectPath.php`, `DirectoryAccess.php` | Безопасные пути и доступ к каталогам |

## База данных и middleware

- `src/Database/DbInterface.php` — контракт доступа к данным.
- `src/Database/OracleDb.php` — реализация Oracle через OCI8.
- `src/Database/DbFactory.php` — создание DB-объекта.
- `src/Database/QueryLogger.php` — SQL debug.
- `src/Middleware/` — сессия, авторизация, организация, доступ, обслуживание, ошибки.
- `src/Logging/ErrorLogger.php` — файловый журнал ошибок.

## Отчёты

- `src/Reports/ReportConfig.php` — отчёты, фильтры, endpoints, колонки и детализации.
- `src/Reports/source-columns.php` — исходные поля и подписи.
- `src/Reports/WagonConfig.php` — конфигурация карточки вагона.

## Шаблоны

- `templates/app.php` — основная страница/оболочка приложения.
- `templates/detail.php` — детализация.
- `templates/wagon.php` — карточка вагона.
- `templates/maps.php` — карта.
- `templates/import.php` — импорт.
- `templates/admin/` — административные страницы.
- `templates/partials/` — повторно используемые части интерфейса.

## Frontend

- `public/assets/js/app.js` — основная логика UI.
- `detail-contexts.js` — контексты детализаций.
- `page-visibility.js` — видимость страниц.
- `data-scopes.js` — области данных.
- `mailing-run.js` — рассылки.
- `wagon.js` — карточка вагона.
- `excel-export.js`, `csv-export.js` — экспорт.
- `public/assets/css/app.css` — общие стили.

## Oracle

- `db/XX_RJD_DISLOCATION_NEW_PKG.pks/.pkb` — пакет дислокации.
- `db/XX_MTF_SEND_MAIL_PKG.pks/.pkb` — почтовый пакет.
- `db/data_scopes/` — таблицы, политика доступа и индексы областей данных.
- `db/rjd/` — диагностические запросы по схеме.
- `db/old/` — исторические SQL-файлы; перед использованием обязательна проверка.
