<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';
$actionNames = [
    'LOGIN' => 'Вход в систему',
    'LOGOUT' => 'Выход из системы',
    'PASSWORD_RESET' => 'Восстановление пароля',
    'PASSWORD_CHANGE' => 'Изменение пароля',
    'USER_CREATE' => 'Создание пользователя',
    'USER_UPDATE' => 'Изменение пользователя',
    'USER_ENABLE' => 'Включение пользователя',
    'USER_DISABLE' => 'Отключение пользователя',
    'USER_ROLES_UPDATE' => 'Изменение ролей',
    'USER_ORGANIZATIONS_UPDATE' => 'Изменение организаций',
    'AUDIT_ENABLED' => 'Включение журнала',
    'MAILING_CREATE' => 'Создание рассылки',
    'MAILING_UPDATE' => 'Изменение рассылки',
    'MAILING_ENABLE' => 'Включение рассылки',
    'MAILING_DISABLE' => 'Отключение рассылки',
    'MAILING_DELETE' => 'Удаление рассылки',
    'MAILING_RUN' => 'Ручной запуск рассылки',
    'IMPORT_UPLOAD' => 'Загрузка справки',
    'NORM_SAVE' => 'Сохранение норматива',
    'NORM_DELETE' => 'Удаление норматива',
    'STATION_CREATE' => 'Создание станции',
    'STATION_UPDATE' => 'Изменение станции',
    'STATION_DELETE' => 'Удаление станции',
    'ROLE_CREATE' => 'Создание роли',
    'ROLE_UPDATE' => 'Изменение роли',
    'ROLE_DELETE' => 'Удаление роли',
    'ORGANIZATION_CREATE' => 'Создание организации',
    'ORGANIZATION_UPDATE' => 'Изменение организации',
    'ORGANIZATION_ENABLE' => 'Включение организации',
    'ORGANIZATION_DISABLE' => 'Отключение организации',
];
$pageUrl = static function (int $number) use ($basePath, $search): string {
    return $basePath . '/admin/audit?' . http_build_query(['q' => $search, 'page' => $number]);
};
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> — Журнал действий</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
    <style>
        .audit-table {
            min-width: 900px
        }

        .audit-table td {
            vertical-align: top
        }

        .audit-code {
            font-size: 11px
        }

        @media(max-width:700px) {
            .audit-head {
                align-items: stretch;
                flex-direction: column
            }
        }
    </style>
</head>

<body>
    <?php $headerSub = '<div class="brand-sub">Администрирование</div>';
    $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
    $headerRight = '';
    include __DIR__ . '/../partials/header.php'; ?>
    <div class="app-body"><?php $activeAdminPage = 'audit';
    include __DIR__ . '/../partials/admin-sidebar.php'; ?>
        <main class="main-content">
            <div class="ui-page-head audit-head">
                <div class="ui-page-title">Журнал действий<small></small></div>
                <form class="ui-search" method="get"><input class="ui-search-input" name="q"
                        value="<?= htmlspecialchars($search) ?>" placeholder="Пользователь, действие или объект"><button
                        class="btn btn-primary">Найти</button></form>
            </div>
            <section class="ui-panel">
                <div class="ui-panel-head"><span class="ui-panel-title">Записей: <?= $total ?></span></div>
                <div class="ui-table-wrap">
                    <table class="ui-table audit-table">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Пользователь</th>
                                <th>Действие</th>
                                <th>Объект</th>
                                <th>Описание</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="6" class="ui-empty">Записи не найдены</td>
                                </tr><?php endif; ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                                    <td><?= htmlspecialchars($row['username'] ?: 'Система') ?></td>
                                    <td><?= htmlspecialchars($actionNames[$row['action_code']] ?? $row['action_code']) ?>
                                    </td>
                                    <td><span
                                            class="ui-code audit-code"><?= htmlspecialchars(trim(($row['object_type'] ?? '') . ' ' . ($row['object_id'] ?? ''))) ?: '—' ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($row['description'] ?: '—') ?></td>
                                    <td class="ui-muted"><?= htmlspecialchars($row['ip_address'] ?: '—') ?></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($pages > 1): ?>    <?php $paginationPage = $page;
                      $paginationPages = $pages;
                      $paginationUrl = $pageUrl;
                      $paginationLabel = 'Страницы журнала действий';
                      include __DIR__ . '/../partials/pagination.php'; ?><?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>