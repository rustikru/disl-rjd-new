# План доработок проекта

В этом файле собраны идеи, которые пока не реализованы, но к которым можно вернуться позже. Перед началом работы нужно ещё раз проверить актуальный код: со временем структура проекта может измениться.

## Упростить контроллеры

Сейчас в `src/Controllers` находится 12 контроллеров. Несколько файлов очень маленькие и отвечают за близкие действия. Их можно объединить, чтобы нужный код было проще искать.

При этом нельзя переносить всю логику в контроллеры. Контроллер должен принять HTTP-запрос, вызвать нужный сервис и вернуть страницу или JSON. SQL, расчёты и основные правила должны находиться в сервисах.

### Предлагаемая структура

После доработки в папке `src/Controllers` можно оставить восемь файлов:

```text
Controllers/
├── AdminController.php
├── ApiController.php
├── AuthController.php
├── DashboardController.php
├── ImportController.php
├── MailingController.php
├── MapsController.php
└── UserController.php
```

Количество файлов изменится с 12 до 8.

### Новый UserController

Создать `UserController.php` и перенести в него действия, относящиеся к текущему пользователю:

```text
ProfileController::index()                  → UserController::profile()
PasswordResetController::showRequest()      → UserController::forgotPassword()
PasswordResetController::request()          → UserController::sendPasswordReset()
PasswordResetController::showReset()        → UserController::resetPasswordForm()
PasswordResetController::reset()            → UserController::resetPassword()
OrganizationController::select()            → UserController::selectOrganization()
```

После проверки маршрутов удалить:

```text
ProfileController.php
PasswordResetController.php
OrganizationController.php
```

Административное создание и редактирование пользователей в `UserController` переносить не нужно. Оно должно остаться в `AdminController`, потому что требует прав администратора.

Вход и выход также должны остаться в `AuthController`.

### DowntimeControlController

В `DowntimeControlController` находится один API-метод. Его можно перенести:

```text
DowntimeControlController::detail()
    → ApiController::downtimeControlDetail()
```

После переключения маршрута и проверки ответа удалить `DowntimeControlController.php`.

Сам SQL со временем лучше вынести из контроллера в подходящий сервис. В `ApiController` должен остаться только вызов сервиса и возврат JSON.

### WagonController

В этом контроллере смешаны HTML-страница и JSON API. Их можно разделить по назначению:

```text
WagonController::index()     → DashboardController::wagon()
WagonController::movement()  → ApiController::wagonMovement()
```

После переключения маршрутов и проверки страницы удалить `WagonController.php`.

Получение движения вагона должно выполняться через существующий сервис `WagonMovement`. SQL возвращать обратно в контроллер не нужно.

## Как должен выглядеть ApiController

`ApiController.php` сейчас очень большой. Создавать отдельный контроллер для каждого отчёта не нужно. Адреса API и методы контроллера можно сохранить, но получение данных перенести в существующие сервисы.

Пример будущего метода:

```php
public function claimsSummary(
    ServerRequestInterface $request,
    ResponseInterface $response
): ResponseInterface {
    $data = $this->reports->summary(
        'claims',
        $request->getQueryParams()
    );

    return $this->json($response, $data);
}
```

Общий принцип:

```text
HTTP-запрос
    ↓
ApiController
    ↓
ReportService / WagonMovement / Claims / Organizations
    ↓
Oracle
```

В `ApiController` остаются:

- получение параметров HTTP-запроса;
- вызов нужного сервиса;
- выбор HTTP-статуса;
- формирование JSON-ответа.

В сервисы переносятся:

- SQL;
- построение условий выборки;
- параметры Oracle;
- расчёты;
- подготовка сводных и подробных данных;
- правила конкретных отчётов.

Не нужно создавать отдельный файл для каждого отчёта. Для текущего проекта достаточно существующих файлов:

```text
ReportService.php
WagonMovement.php
Claims.php
Organizations.php
ReportConfig.php
```

Если один из сервисов станет слишком большим, отчёты можно разделить на несколько понятных групп, а не на десятки маленьких классов.

## Что не нужно объединять

- `AdminController` и `UserController`: разные права доступа.
- `AuthController` и `UserController`: авторизация должна оставаться отдельной.
- `ImportController` и `AdminController`: импорт является самостоятельным и потенциально опасным процессом.
- `MailingController` и `UserController`: рассылки имеют отдельное хранение, расписание и журнал запусков.
- `MapsController` и `DashboardController`: карта содержит большой объём специфической логики.
- `ApiController` и `DashboardController`: JSON API и HTML-страницы лучше не смешивать.

`AdminController` и `ApiController` уже очень большие. Добавлять в них новую бизнес-логику нельзя. При изменениях их нужно постепенно упрощать за счёт сервисов.

## Безопасный порядок выполнения

1. Зафиксировать текущие маршруты и проверить, какие контроллеры они вызывают.
2. Создать `UserController` без удаления старых файлов.
3. Перенести методы профиля, сброса пароля и выбора организации без изменения их поведения.
4. Переключить маршруты на `UserController`.
5. Проверить профиль, восстановление пароля и переключение организации.
6. Только после проверки удалить три старых контроллера.
7. Перенести API контроля простоев в `ApiController`, сохранив старый URL.
8. Разделить методы `WagonController` между `DashboardController` и `ApiController`.
9. Проверить все маршруты, права доступа, CSRF, редиректы и ответы JSON.
10. Постепенно переносить SQL из `ApiController` и `AdminController` в существующие сервисы.

## Что обязательно проверить после изменений

- вход и выход из системы;
- открытие профиля;
- восстановление пароля в режимах `file` и `oracle`;
- одноразовость и срок действия ссылки восстановления;
- переключение текущей организации;
- права доступа к административным страницам;
- поиск вагона и история его движения;
- API контроля простоев;
- обычные страницы отчётов;
- ручную выгрузку Excel;
- формирование отчётов для рассылки;
- карту;
- журнал действий.

## Статус

Первый этап выполнен 29.10.2026:

- действия профиля, восстановления пароля и выбора организации объединены в `UserController`;
- страница вагона перенесена в `DashboardController`;
- API движения вагона и контроля простоев перенесены в `ApiController`;
- старые небольшие контроллеры удалены;
- URL, шаблоны и пользовательское поведение сохранены;
- в `src/Controllers` осталось восемь контроллеров.

Следующий этап — постепенно выносить прямые SQL-запросы из `ApiController` и `AdminController` в сервисы. Его нужно выполнять небольшими группами отчётов с проверкой JSON-ответов после каждого переноса.

Первая группа SQL перенесена 29.10.2026 — отчёт «Претензии»:

- формирование источника из актуальных справок перенесено в `Claims`;
- SQL списков станций и родов вагонов перенесён в `Claims`;
- расчёт сводных строк и итогов перенесён в `Claims`;
- сортировка и выбор полей детализации перенесены в `Claims`;
- методы `claimsFilters`, `claimsSummary` и `claimsDetail` в `ApiController` теперь только принимают параметры, вызывают сервис и возвращают JSON;
- URL, HTTP-статусы и структура JSON-ответов сохранены;
- отдельно проверены ответы фильтров, сводной таблицы, детализации и ошибка HTTP 400 при неверном статусе расчёта.

Следующую группу лучше выбирать среди связанных методов одного отчёта. Не следует одновременно переносить SQL нескольких независимых страниц: так сложнее определить причину изменения результата.
