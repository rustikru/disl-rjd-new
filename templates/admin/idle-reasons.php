<?php
require_once __DIR__ . '/../partials/ui.php';
$fromRow = $totalRows ? (($page - 1) * $perPage + 1) : 0;
$toRow = min($totalRows, $page * $perPage);
$pageUrl = static function (int $targetPage) use ($basePath, $search): string {
    $params = ['page' => $targetPage];
    if ($search !== '')
        $params['q'] = $search;
    return $basePath . '/directories/idle-reasons?' . http_build_query($params); };
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($appName) ?> — Причины простоя</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
    <style>
        .reason-actions {
            display: flex;
            gap: 4px;
            justify-content: flex-end
        }

        .reason-actions form {
            margin: 0
        }
    </style>
</head>

<body>
    <?php $headerSub = '<div class="brand-sub">Справочники</div>';
    $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/directories/" class="btn-nav-back" title="К справочникам">←</a>';
    $headerRight = '';
    include __DIR__ . '/../partials/header.php'; ?>
    <div class="app-body">
        <?php $activeAdminPage = 'idle-reasons';
        include __DIR__ . '/../partials/directories-sidebar.php'; ?>
        <main class="main-content">
            <div class="ui-page-head">
                <div class="ui-page-title">Причины простоя<small>Справочник причин контроля простоев</small></div>
                <?php if ($canEdit): ?><a class="btn btn-primary"
                        href="<?= htmlspecialchars($basePath) ?>/directories/idle-reasons/new">+ Новая запись</a><?php endif; ?>
            </div><?php uiNotices($flashOk, $flashErr); ?>
            <section class="ui-panel">
                <div class="ui-panel-head"><span class="ui-panel-title">Причины (<?= (int) $totalRows ?>)</span>
                    <form class="ui-search" method="get"><input class="ui-search-input" type="search" name="q"
                            value="<?= htmlspecialchars($search) ?>" placeholder="Название или описание"><button
                            class="btn btn-ghost">Найти</button></form>
                </div>
                <?php if (!$rows): ?>
                    <div class="ui-empty">Причины не найдены</div><?php else: ?>
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Название</th>
                                    <th>Описание</th><?php if ($canEdit): ?>
                                        <th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['descr'] ?? '') ?></td><?php if ($canEdit): ?>
                                            <td>
                                                <div class="reason-actions"><a class="ui-icon-button ui-icon-button--edit"
                                                        href="<?= htmlspecialchars($basePath) ?>/directories/idle-reasons/<?= (int) $row['id'] ?>/edit"
                                                        title="Редактировать" aria-label="Редактировать"><?= uiEditIcon() ?></a>
                                                    <form method="post"
                                                        action="<?= htmlspecialchars($basePath) ?>/directories/idle-reasons/delete"
                                                        onsubmit="return confirm('Удалить причину?')"><input type="hidden"
                                                            name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input
                                                            type="hidden" name="id" value="<?= (int) $row['id'] ?>"><button
                                                            class="ui-icon-button ui-icon-button--delete" title="Удалить"
                                                            aria-label="Удалить"><?= uiDeleteIcon() ?></button></form>
                                                </div>
                                            </td><?php endif; ?>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ui-pagination-wrap">
                        <div class="ui-pagination-info">Показаны <?= (int) $fromRow ?>–<?= (int) $toRow ?> из <?= (int) $totalRows ?>
                        </div>
                        <?php $paginationPage = $page;
                        $paginationPages = $totalPages;
                        $paginationUrl = $pageUrl;
                        $paginationLabel = 'Страницы причин простоя';
                        include __DIR__ . '/../partials/pagination.php'; ?>
                    </div><?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>