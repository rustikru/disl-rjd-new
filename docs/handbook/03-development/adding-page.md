# Как добавить новую страницу

В проекте есть два разных типа страниц. Сначала определите, какой нужен:

1. **Самостоятельная страница** — отдельный WEB-маршрут, контроллер/метод и шаблон. Например, `/wagon`.
2. **Отчётная страница** — раздел, который описывается через `ReportConfig` и получает фильтры, summary/detail API, колонки и навигацию. Это основной путь для аналитических отчётов.

Не создавайте отчёт как самостоятельную страницу, если он укладывается в существующую систему отчётов.

## Вариант A. Самостоятельная WEB-страница

### Шаг 1. Найдите похожую реализацию

Посмотрите `DashboardController::wagon()` и `templates/wagon.php` либо ближайшую административную страницу. Сверьте:

- как контроллер получает конфигурацию и БД;
- как собирает данные;
- как подключает шаблон;
- как формируется заголовок и меню;
- какие общие CSS-классы используются.

### Шаг 2. Добавьте маршрут

В `src/routes.php` внутри защищённой группы добавьте маршрут. В текущем проекте часто используется анонимный callback, который создаёт контроллер и вызывает метод:

```php
$group->get('/sample', function ($req, $res) use ($getDb, $config) {
    return (new \App\Controllers\SampleController($getDb(), $config))
        ->index($req, $res);
});
```

Это пример формы регистрации, а не готовый класс: адаптируйте конструктор и имя метода под фактическую задачу.

### Шаг 3. Добавьте контроллер

Создайте `src/Controllers/SampleController.php`. Контроллер должен:

- принимать зависимости в конструкторе;
- читать и проверять параметры запроса;
- вызывать сервис/DB-слой;
- собирать данные для шаблона;
- возвращать PSR-7 Response.

Пример минимального каркаса:

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SampleController
{
    public function __construct(private DbInterface $db) {}

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $rows = $this->db->fetchAll(
            'SELECT id, name FROM xx_rjd_example ORDER BY name'
        );

        $basePath = '';
        $user = $_SESSION['user'] ?? [];
        ob_start();
        require __DIR__ . '/../../templates/sample.php';
        $html = (string) ob_get_clean();
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
```

`xx_rjd_example` — условное имя таблицы, его нельзя копировать без реальной таблицы и согласованной схемы. Конструктор в примере упрощён; реальный контроллер должен получать только нужные зависимости.

### Шаг 4. Создайте шаблон

Разместите `templates/sample.php`. Используйте общий каркас и CSS-классы из `public/assets/css/app.css`: `ui-page-head`, `ui-page-title`, `ui-panel`, `ui-card`, `ui-table`, `ui-fields`, `ui-form-actions`, `btn`, `ui-back-link`.

Любые данные из БД и запроса выводите через `htmlspecialchars()`:

```php
<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
```

### Шаг 5. Зарегистрируйте страницу и право

Добавьте код, подпись и маршруты в `PageRegistry::CORE`. Если странице нужен пункт меню — настройте `navigation`/группу/порядок в принятом формате. Если административный пункт — используйте `ADMIN_NAVIGATION`.

Не дублируйте список страниц в шаблоне или контроллере. Для отключения страницы используется `PageVisibility` и `XX_RJD_DISABLED_PAGES`.

### Шаг 6. Проверьте

```bash
php -l src/Controllers/SampleController.php
php -l templates/sample.php
composer check:registry
git diff --check
```

Затем откройте URL напрямую, проверьте пользователя с правом и без права, проверьте отключение страницы, пустые данные и узкий экран.

## Вариант B. Отчётная страница

Для отчётов основная конфигурация — `src/Reports/ReportConfig.php`. Добавьте определение в массив `$reports` внутри `ReportConfig::all()`.

Обычно определение передаёт в `self::report()`:

- название;
- endpoint фильтров;
- endpoint summary;
- endpoint detail;
- описание фильтров;
- группировки/dimensions;
- конфигурацию колонок;
- флаги поведения.

Пример структуры уже существующего отчёта — `approach`, `departure` или `downtime` в `ReportConfig::all()`. Не копируйте код вслепую: параметры `self::report()` нужно сверить с её сигнатурой.

Далее:

1. Добавьте серверные методы в `ApiController` с именами, указанными в `apiHandlers()`.
2. Используйте `ReportConfig::validateFilters()` для проверки входных фильтров. Генератор маршрутов уже вызывает эту проверку для endpoints, зарегистрированных через реестр.
3. Добавьте/проверьте нужные колонки в `ReportConfig` и `rjd-reference-columns.php`.
4. Если есть нестандартная навигация или панель, проверьте `navigationSettings()` и `pageSettings()` в `ReportConfig` и шаблон `templates/app.php`.
5. Проверьте API, права, фильтры, summary/detail и экспорт.
6. Выполните `composer check:registry`.

## Что документировать вместе с новой страницей

В `docs/логика/логика_страниц.md` (или в актуальной главе этой handbook) запишите:

- назначение страницы;
- URL и код страницы;
- endpoints;
- контроллер и методы;
- таблицы/представления и точные условия выборки;
- организационные ограничения;
- фильтры и значения по умолчанию;
- права;
- переходы к детализации;
- проверки, выполненные после разработки.
