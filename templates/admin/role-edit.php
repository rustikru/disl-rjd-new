<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';
$isAdminRole = ($editedRole['code'] ?? '') === 'ADMIN';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — <?= $isNewRole ? 'Новая роль' : ($isViewOnly ? 'Просмотр роли' : 'Редактирование роли') ?></title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .role-fields{grid-template-columns:260px 1fr}.section-label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.04em}.pages{display:grid;grid-template-columns:1fr 1fr;gap:8px 10px;margin-top:8px}.page-option{display:flex;align-items:center;gap:8px;padding:9px 11px;border:1px solid var(--border-lt);border-radius:8px;font-size:12px}.page-option input{accent-color:var(--accent)}.delete-row{max-width:900px;margin-top:22px;padding-top:15px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}.btn-danger{color:var(--brand-neg);border-color:#efc8c8;background:var(--surface)}@media(max-width:650px){.role-fields,.pages{grid-template-columns:1fr}}
  </style>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Администрирование</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/admin/roles" class="btn-nav-back" title="К ролям">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'roles'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content">
    <div class="ui-page-head ui-page-head--form"><a class="ui-back-link" href="<?= htmlspecialchars($basePath) ?>/admin/roles">← К списку ролей</a><h1><?= $isNewRole ? 'Новая роль' : ($isViewOnly ? 'Просмотр роли' : 'Редактирование роли') ?></h1></div>
    <?php uiNotices($flashOk ?? null, $flashErr ?? null); ?>
    <form method="POST" action="<?= htmlspecialchars($basePath) ?><?= $isNewRole ? '/admin/roles' : '/admin/roles/save' ?>" <?= $isViewOnly ? 'onsubmit="return false"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <?php if (!$isNewRole): ?><input type="hidden" name="role_id" value="<?= (int) $editedRole['id'] ?>"><?php endif; ?>
      <section class="ui-card">
        <div class="ui-card-head">Роль</div>
        <div class="ui-card-body">
          <div class="ui-fields role-fields">
            <div class="ui-field"><label>Код</label><input type="text" name="code" value="<?= htmlspecialchars($editedRole['code'] ?? '') ?>" <?= $isNewRole ? 'required' : 'readonly' ?>><span class="ui-help">Латинские буквы, цифры и знак _</span></div>
            <div class="ui-field"><label>Название</label><input type="text" name="name" value="<?= htmlspecialchars($editedRole['name'] ?? '') ?>" <?= $isViewOnly ? 'readonly' : 'required' ?>></div>
            <div class="ui-field ui-field--full"><label>Описание</label><textarea name="description" <?= $isViewOnly ? 'readonly' : '' ?>><?= htmlspecialchars($editedRole['description'] ?? '') ?></textarea></div>
          </div>
          <div class="section-label" style="margin-top:18px">Доступные разделы</div>
          <?php if ($isAdminRole): ?><p class="ui-help">Администратору доступны все разделы. Этот доступ изменить нельзя.</p><?php endif; ?>
          <div class="pages"><?php foreach ($pages as $pageCode => $pageName): ?><label class="page-option"><input type="checkbox" name="pages[]" value="<?= htmlspecialchars($pageCode) ?>" <?= in_array($pageCode, $selectedPages, true) || $isAdminRole ? 'checked' : '' ?> <?= $isAdminRole || $isViewOnly ? 'disabled' : '' ?>><?= htmlspecialchars($pageName) ?></label><?php endforeach; ?></div>
        </div>
      </section>
      <div class="ui-form-actions"><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/admin/roles"><?= $isViewOnly ? 'Назад' : 'Отмена' ?></a><?php if (!$isViewOnly): ?><button class="btn btn-primary" type="submit"><?= $isNewRole ? 'Создать' : 'Сохранить' ?></button><?php endif; ?></div>
    </form>
    <?php if (!$isViewOnly && !$isNewRole && (int) ($editedRole['is_system'] ?? 0) !== 1 && !$isAdminRole): ?><div class="delete-row"><form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/roles/delete" onsubmit="return confirm('Удалить роль?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="role_id" value="<?= (int) $editedRole['id'] ?>"><button class="btn btn-danger" type="submit">Удалить роль</button></form></div><?php endif; ?>
  </main>
</div>
</body></html>
