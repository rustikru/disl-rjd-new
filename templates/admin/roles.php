<?php $basePath = $basePath ?? ''; require_once __DIR__ . '/../partials/ui.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Роли</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .role-link{font-weight:400;color:var(--accent);text-decoration:none}.role-link:hover{text-decoration:underline}
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
  <?php $activeAdminPage = 'roles'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content">
    <div class="ui-page-head"><div class="ui-page-title">Роли<small>Права доступа пользователей</small></div><a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/admin/roles/new">+ Роль</a></div>
    <?php uiNotices($flashOk ?? null, $flashErr ?? null); ?>
    <section class="ui-panel">
      <div class="ui-panel-head"><span class="ui-panel-title">Роли (<?= count($roles) ?>)</span><input id="roleSearch" class="ui-search-input" type="search" placeholder="Поиск по коду и названию…"></div>
      <div class="ui-table-wrap"><table class="ui-table">
        <thead><tr><th>ID</th><th>Код</th><th>Название</th><th>Описание</th><th></th></tr></thead>
        <tbody id="rolesBody">
        <?php foreach ($roles as $role): ?>
          <?php $searchText = mb_strtolower(implode(' ', [$role['code'] ?? '', $role['name'] ?? '', $role['description'] ?? ''])); ?>
          <tr data-role data-search="<?= htmlspecialchars($searchText) ?>">
            <td><?= (int) $role['id'] ?></td>
            <td><span class="ui-code"><?= htmlspecialchars($role['code']) ?></span></td>
            <td><a class="role-link" href="<?= htmlspecialchars($basePath) ?>/admin/roles/<?= (int) $role['id'] ?>/edit"><?= htmlspecialchars($role['name']) ?></a></td>
            <td><?= !empty($role['description']) ? htmlspecialchars($role['description']) : '<span class="ui-muted">—</span>' ?></td>
            <td><a class="ui-icon-button ui-icon-button--edit" href="<?= htmlspecialchars($basePath) ?>/admin/roles/<?= (int) $role['id'] ?>/edit" title="Редактировать" aria-label="Редактировать"><?= uiEditIcon() ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
  </main>
</div>
<script>
;(function(){var input=document.getElementById('roleSearch'),rows=[].slice.call(document.querySelectorAll('#rolesBody tr[data-role]'));input.addEventListener('input',function(){var q=this.value.toLowerCase().trim();rows.forEach(function(row){row.hidden=q!==''&&row.dataset.search.indexOf(q)<0})})})()
</script>
</body></html>
