<?php
require_once __DIR__ . '/../partials/ui.php';
$displayDate = static fn($date) => $date ? (new DateTimeImmutable((string) $date))->format('d.m.Y') : '';
$fromRow = $totalRows ? (($page - 1) * $perPage + 1) : 0;
$toRow = min($totalRows, $page * $perPage);
$pageUrl = static function (int $targetPage) use ($basePath, $search): string {
    $params = ['page' => $targetPage];
    if ($search !== '')
        $params['q'] = $search;
    return $basePath . '/directories/idle-control?' . http_build_query($params); };
?>
<!doctype html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($appName) ?> — Контроль простоев</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
    <style>
        .idle-control-actions {
            display: flex;
            gap: 4px;
            justify-content: flex-end
        }

        .idle-control-actions form {
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
        <?php $activeAdminPage = 'idle-control';
        include __DIR__ . '/../partials/directories-sidebar.php'; ?>
        <main class="main-content">
            <div class="ui-page-head">
                <div class="ui-page-title">Контроль простоев<small>Периоды и причины исключения вагонов</small></div>
                <?php if ($canEdit): ?><a class="btn btn-primary"
                        href="<?= htmlspecialchars($basePath) ?>/directories/idle-control/new">+ Новая запись</a><?php endif; ?>
            </div><?php uiNotices($flashOk, $flashErr); ?>
            <section class="ui-panel">
                <div class="ui-panel-head"><span class="ui-panel-title">Записи (<?= (int) $totalRows ?>)</span>
                    <form class="ui-search" method="get"><input class="ui-search-input" type="search" name="q"
                            value="<?= htmlspecialchars($search) ?>" placeholder="Вагон, причина или примечание"><button
                            class="btn btn-ghost">Найти</button></form>
                </div>
                <?php if (!$rows): ?>
                    <div class="ui-empty">Записи не найдены</div><?php else: ?>
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>№ вагона</th>
                                    <th>Дата с</th>
                                    <th>Дата по</th>
                                    <th>Причина</th>
                                    <th>Исключён</th>
                                    <th>Примечание</th>
                                    <th>Создал</th><?php if ($canEdit): ?>
                                        <th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['car_number']) ?></td>
                                        <td><?= htmlspecialchars($displayDate($row['start_date'])) ?></td>
                                        <td><?= htmlspecialchars($row['end_date'] ? $displayDate($row['end_date']) : '—') ?></td>
                                        <td><?= htmlspecialchars($row['idle_reasons_name']) ?></td>
                                        <td><?= $row['is_excluded'] === 'Y' ? 'Да' : 'Нет' ?></td>
                                        <td><?= htmlspecialchars($row['note'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row['created_name'] ?? '') ?></td><?php if ($canEdit): ?>
                                            <td>
                                                <div class="idle-control-actions"><a class="ui-icon-button ui-icon-button--edit"
                                                        href="<?= htmlspecialchars($basePath) ?>/directories/idle-control/<?= (int) $row['id_control'] ?>/edit"
                                                        title="Редактировать" aria-label="Редактировать"><?= uiEditIcon() ?></a>
                                                    <form method="post"
                                                        action="<?= htmlspecialchars($basePath) ?>/directories/idle-control/delete"
                                                        onsubmit="return confirm('Удалить запись?')"><input type="hidden"
                                                            name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input
                                                            type="hidden" name="id_control"
                                                            value="<?= (int) $row['id_control'] ?>"><button
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
                        $paginationLabel = 'Страницы контроля простоев';
                        include __DIR__ . '/../partials/pagination.php'; ?>
                    </div><?php endif; ?>
            </section>
        </main>
    </div>
</body>

</html>