<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';
$isNewAccessGroup = (bool) $isNewAccessGroup;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — <?= $isNewAccessGroup ? 'Новая группа доступа' : 'Редактирование группы доступа' ?></title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .access-group-fields{grid-template-columns:220px 1fr;gap:9px 12px}.section-label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.04em}
    .role-groups{display:grid;gap:14px;margin-top:10px}.role-group{padding:12px;border:1px solid var(--border-lt);border-radius:9px;background:var(--surface)}
    .role-group-title{margin:0 0 9px;font-size:12px;color:var(--accent)}.role-options{display:grid;grid-template-columns:1fr 1fr;gap:8px 10px}
    .role-option{display:flex;align-items:center;gap:8px;padding:9px 11px;border:1px solid var(--border-lt);border-radius:8px;font-size:12px;background:var(--surface)}
    .role-option:has(input:checked){background:var(--accent-lt);border-color:var(--accent)}.role-option input{accent-color:var(--accent)}
    .access-group-actions{justify-content:space-between}.access-group-actions-main{display:flex;align-items:center;gap:8px}.btn-danger{color:var(--brand-neg);border-color:#efc8c8;background:var(--surface)}
    @media(max-width:650px){.access-group-fields,.role-options{grid-template-columns:1fr}.access-group-actions{align-items:stretch}.access-group-actions-main{justify-content:flex-end}}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Администрирование</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/admin/access-groups" class="btn-nav-back" title="К группам доступа">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'access-groups'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content ui-form-page">
    <div class="ui-page-head ui-page-head--form"><a class="ui-back-link" href="<?= htmlspecialchars($basePath) ?>/admin/access-groups">← К списку групп доступа</a><h1><?= $isNewAccessGroup ? 'Новая группа доступа' : 'Редактирование группы доступа' ?></h1></div>
    <?php uiNotices($flashOk ?? null, $flashErr ?? null); ?>
    <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/access-groups/save">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <?php if (!$isNewAccessGroup): ?><input type="hidden" name="access_group_id" value="<?= (int) $editedAccessGroup['id'] ?>"><?php endif; ?>
      <section class="ui-card">
        <div class="ui-card-head">Группа доступа</div>
        <div class="ui-card-body">
          <div class="ui-fields access-group-fields">
            <div class="ui-field"><label>Название</label><input type="text" name="name" maxlength="120" value="<?= htmlspecialchars($editedAccessGroup['name'] ?? '') ?>" required></div>
            <div class="ui-field"><label>Описание</label><input type="text" name="description" maxlength="500" value="<?= htmlspecialchars($editedAccessGroup['description'] ?? '') ?>"></div>
          </div>
          <div class="section-label" style="margin-top:14px">Входящие роли</div>
          <?php
          $roleGroups = [];
          foreach ($roles as $role) {
            $roleGroups[$role['code'] === 'ADMIN' ? 'Администрирование' : 'Пользовательские роли'][] = $role;
          }
          ?>
          <div class="role-groups">
            <?php foreach ($roleGroups as $groupName => $groupRoles): ?>
              <section class="role-group">
                <h2 class="role-group-title"><?= htmlspecialchars($groupName) ?></h2>
                <div class="role-options">
                  <?php foreach ($groupRoles as $role): ?>
                    <label class="role-option"><input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>><?= htmlspecialchars($role['name']) ?></label>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endforeach; ?>
          </div>
          <p class="ui-help" style="margin-top:12px">Группа доступа — это шаблон назначения. Права на конкретные страницы по-прежнему настраиваются в разделе «Роли».</p>
        </div>
      </section>
      <div class="ui-form-actions access-group-actions">
        <?php if (!$isNewAccessGroup): ?><button class="btn btn-danger" type="submit" form="deleteAccessGroupForm" onclick="return confirm('Удалить группу доступа?')">Удалить группу</button><?php else: ?><span></span><?php endif; ?>
        <div class="access-group-actions-main"><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/admin/access-groups">Отмена</a><button type="submit" class="btn btn-primary"><?= $isNewAccessGroup ? 'Создать' : 'Сохранить' ?></button></div>
      </div>
    </form>
    <?php if (!$isNewAccessGroup): ?><form id="deleteAccessGroupForm" method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/access-groups/delete"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="access_group_id" value="<?= (int) $editedAccessGroup['id'] ?>"></form><?php endif; ?>
  </main>
</div>
</body>
</html>
