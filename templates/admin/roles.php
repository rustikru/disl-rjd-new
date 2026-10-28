<?php $basePath = $basePath ?? ''; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Роли</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .admin-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.admin-title{font-size:18px;font-weight:700}.admin-title small{display:block;font-size:12px;font-weight:500;color:var(--text-3);margin-top:2px}.flash{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px}.flash-ok{background:#e8f6ef;color:var(--brand-green);border:1px solid #bfe6d2}.flash-err{background:#fbecec;color:var(--brand-neg);border:1px solid #f0c9c9}.panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}.panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border)}.panel-title{font-size:14px;font-weight:600}.search-input{border:1px solid var(--border);border-radius:8px;padding:7px 11px;font:inherit;font-size:12px;width:250px;outline:none}.search-input:focus{border-color:var(--accent)}.table-wrap{overflow-x:auto}.admin-table{width:100%;border-collapse:collapse;min-width:720px}.admin-table th{padding:9px 11px;text-align:left;font-size:10px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;background:var(--row-head);border-bottom:1px solid var(--border)}.admin-table td{padding:9px 11px;border-bottom:1px solid var(--border-lt);font-size:12px;vertical-align:middle}.admin-table tbody tr:hover td{background:var(--hover-green)}.role-link{font-weight:650;color:var(--accent);text-decoration:none}.role-link:hover{text-decoration:underline}.code{font-family:var(--mono,monospace);font-weight:650}.muted{color:var(--text-3)}.badge{display:inline-flex;padding:2px 7px;border-radius:99px;background:var(--accent-lt);color:var(--accent);font-size:10.5px}.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border:0;border-radius:6px;background:transparent;color:var(--text-3);text-decoration:none}.icon-btn:hover{background:var(--hover-green);color:var(--accent)}
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
    <div class="admin-head"><div class="admin-title">Роли<small>Права доступа пользователей</small></div><a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/admin/roles/new">+ Роль</a></div>
    <?php if (!empty($flashOk)): ?><div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <section class="panel">
      <div class="panel-head"><span class="panel-title">Роли (<?= count($roles) ?>)</span><input id="roleSearch" class="search-input" type="search" placeholder="Поиск по коду и названию…"></div>
      <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>ID</th><th>Код</th><th>Название</th><th>Описание</th><th></th></tr></thead>
        <tbody id="rolesBody">
        <?php foreach ($roles as $role): ?>
          <?php $searchText = mb_strtolower(implode(' ', [$role['code'] ?? '', $role['name'] ?? '', $role['description'] ?? ''])); ?>
          <tr data-role data-search="<?= htmlspecialchars($searchText) ?>">
            <td><?= (int) $role['id'] ?></td>
            <td><span class="code"><?= htmlspecialchars($role['code']) ?></span></td>
            <td><a class="role-link" href="<?= htmlspecialchars($basePath) ?>/admin/roles/<?= (int) $role['id'] ?>/edit"><?= htmlspecialchars($role['name']) ?></a></td>
            <td><?= !empty($role['description']) ? htmlspecialchars($role['description']) : '<span class="muted">—</span>' ?></td>
            <td><a class="icon-btn" href="<?= htmlspecialchars($basePath) ?>/admin/roles/<?= (int) $role['id'] ?>/edit" title="Редактировать" aria-label="Редактировать"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M8 1H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1M8 1v3h3L8 1Z"/><path d="M4 5h2M4 7h1m6-1 2 2-5 5H6v-2Z"/></svg></a></td>
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
