<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Группы доступа</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .access-group-link{font-weight:600;color:var(--accent);text-decoration:none}.access-group-link:hover{text-decoration:underline}
    .access-group-roles{display:flex;flex-wrap:wrap;gap:5px}.access-group-role{display:inline-flex;padding:3px 7px;border:1px solid var(--border-lt);border-radius:6px;color:var(--text-2);font-size:11px;background:var(--surface)}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Администрирование</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'access-groups'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content">
    <div class="ui-page-head">
      <div class="ui-page-title">Группы доступа<small>Готовые наборы ролей для назначения пользователям</small></div>
      <?php if (!empty($canManageAccessGroups)): ?><a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/admin/access-groups/new">+ Группа доступа</a><?php endif; ?>
    </div>
    <?php uiNotices($flashOk ?? null, $flashErr ?? null); ?>
    <section class="ui-panel">
      <div class="ui-panel-head"><span class="ui-panel-title">Группы доступа (<?= count($groups) ?>)</span><input id="accessGroupSearch" class="ui-search-input" type="search" placeholder="Поиск по названию и ролям…"></div>
      <div class="ui-table-wrap">
        <table class="ui-table">
          <thead><tr><th>Название</th><th>Роли</th><th>Количество</th><?php if (!empty($canManageAccessGroups)): ?><th></th><?php endif; ?></tr></thead>
          <tbody id="accessGroupsBody">
          <?php foreach ($groups as $group): ?>
            <?php $names = $groupRoles[(int) $group['id']] ?? []; $searchText = mb_strtolower(implode(' ', array_merge([(string) $group['name'], (string) ($group['description'] ?? '')], $names))); ?>
            <tr data-access-group data-search="<?= htmlspecialchars($searchText) ?>">
              <td>
                <?php if (!empty($canManageAccessGroups)): ?><a class="access-group-link" href="<?= htmlspecialchars($basePath) ?>/admin/access-groups/<?= (int) $group['id'] ?>/edit"><?= htmlspecialchars($group['name']) ?></a><?php else: ?><strong><?= htmlspecialchars($group['name']) ?></strong><?php endif; ?>
                <?php if (!empty($group['description'])): ?><span class="ui-muted" style="display:block;margin-top:3px"><?= htmlspecialchars($group['description']) ?></span><?php endif; ?>
              </td>
              <td><div class="access-group-roles"><?php foreach ($names as $name): ?><span class="access-group-role"><?= htmlspecialchars($name) ?></span><?php endforeach; ?><?php if (!$names): ?><span class="ui-muted">Роли не назначены</span><?php endif; ?></div></td>
              <td><?= (int) $group['role_count'] ?></td>
              <?php if (!empty($canManageAccessGroups)): ?><td><div class="actions"><a class="ui-icon-button ui-icon-button--edit" href="<?= htmlspecialchars($basePath) ?>/admin/access-groups/<?= (int) $group['id'] ?>/edit" title="Редактировать" aria-label="Редактировать"><?= uiEditIcon() ?></a></div></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (!$groups): ?><tr><td colspan="<?= !empty($canManageAccessGroups) ? 4 : 3 ?>" class="ui-empty">Группы доступа ещё не созданы.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
<script>
;(function(){var input=document.getElementById('accessGroupSearch'),rows=[].slice.call(document.querySelectorAll('#accessGroupsBody tr[data-access-group]'));if(!input)return;input.addEventListener('input',function(){var q=this.value.toLowerCase().trim();rows.forEach(function(row){row.hidden=q!==''&&row.dataset.search.indexOf(q)<0})})})()
</script>
</body>
</html>
