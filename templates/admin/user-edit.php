<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';
$isNewUser = $isNewUser ?? false;
$roleIds = array_map('intval', $editedUser['role_ids'] ?? []);
$extraOrganizationIds = array_map('intval', $editedUser['organization_ids'] ?? []);
$mainOrganizationId = (int) ($editedUser['organization_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — <?= $isNewUser ? 'Новый пользователь' : 'Редактирование пользователя' ?></title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .edit-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(300px,.8fr);gap:16px}.edit-grid.single{grid-template-columns:1fr}.switch-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0}.switch-row input{width:18px;height:18px;accent-color:var(--accent)}.option-list,.organization-list{display:grid;grid-template-columns:1fr 1fr;gap:7px 10px;margin-top:8px}.option{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border-lt);border-radius:8px;font-size:12px}.option:has(input:checked){background:var(--accent-lt);border-color:var(--accent)}.option input{accent-color:var(--accent)}.option.is-main{opacity:.55}.dates{display:grid;gap:0}.date-row{display:flex;justify-content:space-between;gap:15px;padding:10px 0;border-bottom:1px solid var(--border-lt);font-size:12px}.date-row:last-child{border:0}.date-row span:first-child{color:var(--text-3)}.date-row span:last-child{font-weight:600;text-align:right}@media(max-width:900px){.edit-grid{grid-template-columns:1fr}}@media(max-width:620px){.option-list,.organization-list{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Администрирование</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/admin/users" class="btn-nav-back" title="К пользователям">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'users'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content ui-form-page">
    <div class="ui-page-head ui-page-head--form"><a class="ui-back-link" href="<?= htmlspecialchars($basePath) ?>/admin/users">← К списку пользователей</a><h1><?= $isNewUser ? 'Новый пользователь' : 'Редактирование пользователя' ?></h1></div>
    <?php uiNotices($flashOk ?? null, $flashErr ?? null); ?>
    <form method="POST" action="<?= htmlspecialchars($basePath) ?><?= $isNewUser ? '/admin/users' : '/admin/users/save' ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <?php if (!$isNewUser): ?><input type="hidden" name="user_id" value="<?= (int) $editedUser['id'] ?>"><?php endif; ?>
      <div class="edit-grid <?= $isNewUser ? 'single' : '' ?>">
        <div>
          <section class="ui-card">
            <div class="ui-card-head">Учётная запись</div>
            <div class="ui-card-body">
              <div class="ui-fields">
                <div class="ui-field"><label>Логин</label><input type="text" name="username" value="<?= htmlspecialchars($editedUser['username']) ?>" required></div>
                <div class="ui-field"><label>Имя</label><input type="text" name="display_name" value="<?= htmlspecialchars($editedUser['display_name']) ?>" required></div>
                <div class="ui-field ui-field--full"><label>E-mail</label><input type="email" name="email" value="<?= htmlspecialchars($editedUser['email'] ?? '') ?>"></div>
                <div class="ui-field ui-field--full"><label><?= $isNewUser ? 'Пароль' : 'Новый пароль' ?></label><input type="password" name="password" autocomplete="new-password"><span class="ui-help"><?= $isNewUser ? 'Оставьте пустым для входа через AD' : 'Оставьте пустым, если пароль менять не нужно' ?></span></div>
              </div>
              <label class="switch-row"><strong>Пользователь активен</strong><input type="checkbox" name="is_active" value="1" <?= (int) $editedUser['is_active'] === 1 ? 'checked' : '' ?>></label>
            </div>
          </section>
          <section class="ui-card">
            <div class="ui-card-head">Организации</div>
            <div class="ui-card-body">
              <div class="ui-field"><label>Основная организация</label><select name="organization_id" id="mainOrganization" required><option value="">— Выберите —</option><?php foreach ($organizations as $organization): ?><?php if ((int) ($organization['is_active'] ?? 1) === 1 || (int) $organization['id'] === $mainOrganizationId): ?><option value="<?= (int) $organization['id'] ?>" <?= (int) $organization['id'] === $mainOrganizationId ? 'selected' : '' ?>><?= htmlspecialchars($organization['short_name'] ?: $organization['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
              <div class="ui-field" style="margin-top:14px"><label>Дополнительные организации</label><span class="ui-help">Отметьте организации, доступные пользователю дополнительно.</span><div class="organization-list" id="extraOrganizations"><?php foreach ($organizations as $organization): ?><?php if ((int) ($organization['is_active'] ?? 1) === 1 || in_array((int) $organization['id'], $extraOrganizationIds, true)): ?><label class="option"><input type="checkbox" name="organization_ids[]" value="<?= (int) $organization['id'] ?>" <?= in_array((int) $organization['id'], $extraOrganizationIds, true) ? 'checked' : '' ?>><?= htmlspecialchars($organization['short_name'] ?: $organization['name']) ?></label><?php endif; ?><?php endforeach; ?></div></div>
            </div>
          </section>
          <section class="ui-card">
            <div class="ui-card-head">Роли</div>
            <div class="ui-card-body"><div class="option-list"><?php foreach ($roles as $role): ?><label class="option"><input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $roleIds, true) ? 'checked' : '' ?>><?= htmlspecialchars($role['name']) ?></label><?php endforeach; ?></div></div>
          </section>
        </div>
        <?php if (!$isNewUser): ?><aside>
          <section class="ui-card">
            <div class="ui-card-head">Сведения</div>
            <div class="ui-card-body dates">
              <div class="date-row"><span>ID</span><span><?= (int) $editedUser['id'] ?></span></div>
              <div class="date-row"><span>Дата создания</span><span><?= htmlspecialchars($editedUser['created_at'] ?: '—') ?></span></div>
              <div class="date-row"><span>Дата изменения</span><span><?= htmlspecialchars($editedUser['updated_at'] ?: '—') ?></span></div>
              <div class="date-row"><span>Последняя авторизация</span><span><?= htmlspecialchars($editedUser['last_login_at'] ?: '—') ?></span></div>
            </div>
          </section>
        </aside><?php endif; ?>
      </div>
      <div class="ui-form-actions"><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/admin/users">Отмена</a><button type="submit" class="btn btn-primary"><?= $isNewUser ? 'Создать' : 'Сохранить' ?></button></div>
    </form>
  </main>
</div>
<script>
;(function(){var main=document.getElementById('mainOrganization'),extra=document.getElementById('extraOrganizations');function sync(){[].slice.call(extra.querySelectorAll('input')).forEach(function(box){var same=box.value===main.value;if(same)box.checked=false;box.disabled=same;box.closest('label').classList.toggle('is-main',same)})}main.addEventListener('change',sync);sync()})()
</script>
</body></html>
