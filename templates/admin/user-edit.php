<?php
$basePath = $basePath ?? '';
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
    .page-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}.page-head h1{font-size:19px;margin:10px 0 0}.back-link{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--accent);text-decoration:none;font-size:12px;font-weight:600}.back-link:hover{border-color:var(--accent);background:var(--accent-lt)}.edit-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(300px,.8fr);gap:16px}.edit-grid.single{grid-template-columns:1fr}.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}.card+.card{margin-top:16px}.card-head{padding:13px 17px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700}.card-body{padding:17px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:13px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}.field label,.section-label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.04em}.field input,.field select{border:1px solid var(--border);border-radius:8px;padding:9px 11px;font:inherit;font-size:13px;color:var(--text-1);background:var(--surface);outline:none}.field input:focus,.field select:focus{border-color:var(--accent)}.field select[multiple]{min-height:112px;padding:5px}.field select[multiple] option{padding:7px 9px;border-radius:5px}.help{font-size:10.5px;color:var(--text-3)}.switch-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0}.switch-row input{width:18px;height:18px;accent-color:var(--accent)}.option-list{display:grid;grid-template-columns:1fr 1fr;gap:7px 10px;margin-top:8px}.option{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid var(--border-lt);border-radius:8px;font-size:12px}.option input{accent-color:var(--accent)}.dates{display:grid;gap:0}.date-row{display:flex;justify-content:space-between;gap:15px;padding:10px 0;border-bottom:1px solid var(--border-lt);font-size:12px}.date-row:last-child{border:0}.date-row span:first-child{color:var(--text-3)}.date-row span:last-child{font-weight:600;text-align:right}.flash{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px}.flash-ok{background:#e8f6ef;color:var(--brand-green);border:1px solid #bfe6d2}.flash-err{background:#fbecec;color:var(--brand-neg);border:1px solid #f0c9c9}.form-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}@media(max-width:900px){.edit-grid{grid-template-columns:1fr}}@media(max-width:620px){.fields,.option-list{grid-template-columns:1fr}}
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
  <main class="main-content">
    <div class="page-head"><div><a class="back-link" href="<?= htmlspecialchars($basePath) ?>/admin/users">← К списку пользователей</a><h1><?= $isNewUser ? 'Новый пользователь' : 'Редактирование пользователя' ?></h1></div></div>
    <?php if (!empty($flashOk)): ?><div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars($basePath) ?><?= $isNewUser ? '/admin/users' : '/admin/users/save' ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <?php if (!$isNewUser): ?><input type="hidden" name="user_id" value="<?= (int) $editedUser['id'] ?>"><?php endif; ?>
      <div class="edit-grid <?= $isNewUser ? 'single' : '' ?>">
        <div>
          <section class="card">
            <div class="card-head">Учётная запись</div>
            <div class="card-body">
              <div class="fields">
                <div class="field"><label>Логин</label><input type="text" name="username" value="<?= htmlspecialchars($editedUser['username']) ?>" required></div>
                <div class="field"><label>Имя</label><input type="text" name="display_name" value="<?= htmlspecialchars($editedUser['display_name']) ?>" required></div>
                <div class="field full"><label>E-mail</label><input type="email" name="email" value="<?= htmlspecialchars($editedUser['email'] ?? '') ?>"></div>
                <div class="field full"><label><?= $isNewUser ? 'Пароль' : 'Новый пароль' ?></label><input type="password" name="password" autocomplete="new-password"><span class="help"><?= $isNewUser ? 'Оставьте пустым для входа через AD' : 'Оставьте пустым, если пароль менять не нужно' ?></span></div>
              </div>
              <label class="switch-row"><strong>Пользователь активен</strong><input type="checkbox" name="is_active" value="1" <?= (int) $editedUser['is_active'] === 1 ? 'checked' : '' ?>></label>
            </div>
          </section>
          <section class="card">
            <div class="card-head">Организации</div>
            <div class="card-body">
              <div class="field"><label>Основная организация</label><select name="organization_id" id="mainOrganization" required><option value="">— Выберите —</option><?php foreach ($organizations as $organization): ?><?php if ((int) ($organization['is_active'] ?? 1) === 1 || (int) $organization['id'] === $mainOrganizationId): ?><option value="<?= (int) $organization['id'] ?>" <?= (int) $organization['id'] === $mainOrganizationId ? 'selected' : '' ?>><?= htmlspecialchars($organization['short_name'] ?: $organization['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
              <div class="field" style="margin-top:14px"><label>Дополнительные организации</label><select name="organization_ids[]" id="extraOrganizations" multiple><?php foreach ($organizations as $organization): ?><?php if ((int) ($organization['is_active'] ?? 1) === 1 || in_array((int) $organization['id'], $extraOrganizationIds, true)): ?><option value="<?= (int) $organization['id'] ?>" <?= in_array((int) $organization['id'], $extraOrganizationIds, true) ? 'selected' : '' ?>><?= htmlspecialchars($organization['short_name'] ?: $organization['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
            </div>
          </section>
          <section class="card">
            <div class="card-head">Роли</div>
            <div class="card-body"><div class="option-list"><?php foreach ($roles as $role): ?><label class="option"><input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $roleIds, true) ? 'checked' : '' ?>><?= htmlspecialchars($role['name']) ?></label><?php endforeach; ?></div></div>
          </section>
        </div>
        <?php if (!$isNewUser): ?><aside>
          <section class="card">
            <div class="card-head">Сведения</div>
            <div class="card-body dates">
              <div class="date-row"><span>ID</span><span><?= (int) $editedUser['id'] ?></span></div>
              <div class="date-row"><span>Дата создания</span><span><?= htmlspecialchars($editedUser['created_at'] ?: '—') ?></span></div>
              <div class="date-row"><span>Дата изменения</span><span><?= htmlspecialchars($editedUser['updated_at'] ?: '—') ?></span></div>
              <div class="date-row"><span>Последняя авторизация</span><span><?= htmlspecialchars($editedUser['last_login_at'] ?: '—') ?></span></div>
            </div>
          </section>
        </aside><?php endif; ?>
      </div>
      <div class="form-actions"><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/admin/users">Отмена</a><button type="submit" class="btn btn-primary"><?= $isNewUser ? 'Создать' : 'Сохранить' ?></button></div>
    </form>
  </main>
</div>
<script>
;(function(){var main=document.getElementById('mainOrganization'),extra=document.getElementById('extraOrganizations');function sync(){[].slice.call(extra.options).forEach(function(option){var isMain=option.value===main.value;if(isMain)option.selected=false;option.disabled=isMain})}main.addEventListener('change',sync);sync()})()
</script>
</body></html>
