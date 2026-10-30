# Добавление новой страницы

Инструкция относится к самостоятельной странице приложения после перехода на
единый `PageRegistry`. Для нового отчёта используется другой путь — определение
в `ReportConfig`, описанное в `single-source-of-truth.md`.

## 1. Определить отдельное право

Сначала ответить, является ли страница самостоятельной функцией. Если её можно
открыть из нескольких мест, ей нужно отдельное право, а не право одного из
пунктов меню. Например, `wagon-detail` не зависит от `dislocation`.

Выбрать стабильный код в `kebab-case`. Код хранится в БД и не должен меняться при
обычном переименовании подписи.

## 2. Добавить страницу в PageRegistry

Единственное определение страницы добавляется в `PageRegistry::CORE`:

```php
'sample-page' => [
    'label' => 'Новая страница',
    'routes' => ['/sample', '/api/sample'],
    'visibility_group' => 'Сайт — основные разделы',
],
```

После этого страница автоматически появляется:

- в «Доступных разделах» роли;
- в настройке видимости страниц;
- в проверке прямого WEB/API URL через `PageAccessMiddleware`.

В `routes` перечисляются все URL-префиксы этой функции: HTML, POST и API. Более
точный префикс выбирается автоматически.

### Основное меню

Если нужен пункт в левом меню сайта, добавить `navigation` в ту же запись:

```php
'navigation' => [
    'group' => 'analytics',
    'order' => 30,
    'url' => '/sample',
],
```

Допустимые группы находятся в `PageRegistry::GROUPS`. Подпись берётся из `label`.
Отдельно менять `Navigation.php` не нужно.

### Административное меню

```php
'admin_only' => true,
'admin_navigation' => [
    'group' => 'Настройки системы',
    'order' => 30,
    'url' => '/admin/sample',
],
```

`admin_only` запрещает назначать страницу обычному пользователю. Если страница
доступна исключительно системному `SUPER`, вместо него используется
`'super_only' => true`.

### Меню пользователя

```php
'user_menu' => [
    'order' => 30,
    'url' => '/profile/sample',
    'label' => 'Мои настройки',
],
```

Не добавлять название, URL или право второй раз в шаблон меню.

## 3. Создать метод контроллера

Контроллер получает данные, подготавливает простые переменные и подключает
шаблон. HTML не собирается в контроллере.

```php
/** GET /sample */
public function samplePage(
    ServerRequestInterface $request,
    ResponseInterface $response
): ResponseInterface {
    $appName = $this->config['app_name'];
    $basePath = $this->config['base_path'] ?? '';
    $user = $_SESSION['user'] ?? [];
    $rows = $this->db->fetchAll('SELECT id, name FROM xx_sample ORDER BY name');

    ob_start();
    require __DIR__ . '/../../templates/sample.php';
    $response->getBody()->write((string) ob_get_clean());

    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
}
```

Имена выбирать простые: `$rows`, `$user`, `$canEdit`. Не создавать отдельный
класс для простой выборки без повторного использования.

## 4. Зарегистрировать исполняемый маршрут

Маршрут добавляется внутрь защищённой группы в `src/routes.php`:

```php
$group->get('/sample', function ($req, $res) use ($getDb, $config) {
    return (new \App\Controllers\SampleController($getDb(), $config))
        ->samplePage($req, $res);
});
```

Защищённая группа уже содержит:

- `AuthMiddleware` — пользователь должен войти;
- `OrganizationMiddleware` — выбирается организация;
- `PageAccessMiddleware` — проверяются право роли и `disabled_pages`.

Не регистрировать рабочую страницу вне этой группы. Публичные маршруты допускаются
только для входа и восстановления доступа и требуют отдельного решения.

## 5. Создать шаблон

Использовать общий header, существующий sidebar и UI-компоненты. Пример каркаса:

```php
<?php $basePath = $basePath ?? ''; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Новая страница</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Новая страница</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
$headerRight = '';
include __DIR__ . '/partials/header.php';
?>
<div class="app-body">
  <main class="main-content">
    <div class="ui-page-head">
      <div class="ui-page-title">Новая страница<small>Краткое пояснение</small></div>
    </div>
    <section class="ui-panel">
      <div class="ui-panel-head"><span class="ui-panel-title">Данные</span></div>
      <div class="ui-card-body">Содержимое страницы</div>
    </section>
  </main>
</div>
</body>
</html>
```

Для административной страницы подключить `admin-sidebar.php` и установить
`$activeAdminPage` равным коду страницы. Для формы добавить класс `ui-form-page`,
использовать `ui-page-head--form`, `ui-card`, `ui-fields` и `ui-form-actions`.

Все значения из БД и запроса выводить через `htmlspecialchars()`. Общий стиль
добавлять в `app.css`; локальный CSS оставлять только для уникальных элементов.

## 6. Добавить POST и API

URL сохранения и API включить в `routes` той же записи `PageRegistry`. Серверное
действие обязано повторно проверить:

- CSRF для POST;
- право на конкретное действие;
- формат и допустимые значения полей;
- запреты для системных объектов и `SUPER`, если они относятся к функции;
- принадлежность данных текущей организации.

Скрытая кнопка в интерфейсе не заменяет эти проверки.

Если одно API намеренно используется несколькими независимыми страницами,
добавить явное правило в `PageRegistry::accessPages()`. Не выдавать доступ через
несвязанный раздел только потому, что раньше переход выполнялся из него.

## 7. Сохранить доступ существующих ролей

Для новой установки достаточно каталога: право назначается на странице роли.
При разделении существующего права подготовить SQL-миграцию в `db/`, чтобы
текущие роли не потеряли доступ:

```sql
INSERT INTO xx_rjd_role_pages (role_id, page)
SELECT source.role_id, 'sample-page'
  FROM xx_rjd_role_pages source
 WHERE source.page = 'old-page'
   AND NOT EXISTS (
       SELECT 1
         FROM xx_rjd_role_pages target
        WHERE target.role_id = source.role_id
          AND target.page = 'sample-page'
   );
```

Миграция не должна автоматически выдавать право ролям, у которых раньше не было
эквивалентного доступа.

## 8. Проверить результат

```bash
php -l src/Controllers/SampleController.php
php -l templates/sample.php
composer check:registry
git diff --check
```

Ручная проверка:

1. Страница появилась в правильной группе «Доступных разделов».
2. С правом открываются HTML, POST и API.
3. Без права прямой URL и изменённый запрос возвращают 403.
4. `SUPER` имеет доступ, `ADMIN` следует назначенным ролям.
5. Отключение страницы в `disabled_pages` блокирует URL и убирает меню.
6. Название и порядок меню берутся из `PageRegistry`, без второго списка.
7. Страница компактна, кнопки выровнены, узкий экран и пустое состояние выглядят
   корректно.

## Если добавляется отчёт

Отчёт с фильтрами, summary/detail API, экспортом или рассылкой не добавляется
вручную в `PageRegistry::CORE`. Нужно:

1. Добавить одно определение в `ReportConfig`.
2. Указать `api_handlers`, endpoints, фильтры, колонки, `navigation` и настройки
   страницы.
3. Реализовать указанные методы `ApiController` и получение данных.
4. Добавить нестандартный `panel-{report-code}` в `templates/app.php`, если он
   требуется.
5. Выполнить `composer check:registry`.

Так меню, право, маршруты и клиентская конфигурация продолжают формироваться из
одного определения.
