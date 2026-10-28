<?php $basePath = $basePath ?? ''; $isAdminRole = ($editedRole['code'] ?? '') === 'ADMIN'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — <?= $isNewRole ? 'Новая роль' : 'Редактирование роли' ?></title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .page-head{margin-bottom:16px}.page-head h1{font-size:19px;margin:10px 0 0}.back-link{display:inline-flex;padding:7px 11px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--accent);text-decoration:none;font-size:12px;font-weight:600}.back-link:hover{border-color:var(--accent);background:var(--accent-lt)}.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;max-width:900px}.card-head{padding:13px 17px;border-bottom:1px solid var(--border);font-size:14px;font-weight:700}.card-body{padding:17px}.fields{display:grid;grid-template-columns:260px 1fr;gap:13px}.field{display:flex;flex-direction:column;gap:5px}.field.full{grid-column:1/-1}.field label,.section-label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.04em}.field input,.field textarea{border:1px solid var(--border);border-radius:8px;padding:9px 11px;font:inherit;font-size:13px;background:var(--surface);outline:none}.field input:focus,.field textarea:focus{border-color:var(--accent)}.field textarea{min-height:78px;resize:vertical}.pages{display:grid;grid-template-columns:1fr 1fr;gap:8px 10px;margin-top:8px}.page-option{display:flex;align-items:center;gap:8px;padding:9px 11px;border:1px solid var(--border-lt);border-radius:8px;font-size:12px}.page-option input{accent-color:var(--accent)}.help{font-size:10.5px;color:var(--text-3)}.flash{max-width:872px;padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px}.flash-ok{background:#e8f6ef;color:var(--brand-green);border:1px solid #bfe6d2}.flash-err{background:#fbecec;color:var(--brand-neg);border:1px solid #f0c9c9}.actions{display:flex;justify-content:flex-end;gap:8px;max-width:900px;margin-top:16px}.delete-row{max-width:900px;margin-top:22px;padding-top:15px;border-top:1px solid var(--border);display:flex;justify-content:flex-end}.btn-danger{color:var(--brand-neg);border-color:#efc8c8;background:var(--surface)}@media(max-width:650px){.fields,.pages{grid-template-columns:1fr}}
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
    <div class="page-head"><a class="back-link" href="<?= htmlspecialchars($basePath) ?>/admin/roles">← К списку ролей</a><h1><?= $isNewRole ? 'Новая роль' : 'Редактирование роли' ?></h1></div>
    <?php if (!empty($flashOk)): ?><div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>
    <form method="POST" action="<?= htmlspecialchars($basePath) ?><?= $isNewRole ? '/admin/roles' : '/admin/roles/save' ?>">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <?php if (!$isNewRole): ?><input type="hidden" name="role_id" value="<?= (int) $editedRole['id'] ?>"><?php endif; ?>
      <section class="card">
        <div class="card-head">Роль</div>
        <div class="card-body">
          <div class="fields">
            <div class="field"><label>Код</label><input type="text" name="code" value="<?= htmlspecialchars($editedRole['code'] ?? '') ?>" <?= $isNewRole ? 'required' : 'readonly' ?>><span class="help">Латинские буквы, цифры и знак _</span></div>
            <div class="field"><label>Название</label><input type="text" name="name" value="<?= htmlspecialchars($editedRole['name'] ?? '') ?>" required></div>
            <div class="field full"><label>Описание</label><textarea name="description"><?= htmlspecialchars($editedRole['description'] ?? '') ?></textarea></div>
          </div>
          <div class="section-label" style="margin-top:18px">Доступные разделы</div>
          <?php if ($isAdminRole): ?><p class="help">Администратору доступны все разделы. Этот доступ изменить нельзя.</p><?php endif; ?>
          <div class="pages"><?php foreach ($pages as $pageCode => $pageName): ?><label class="page-option"><input type="checkbox" name="pages[]" value="<?= htmlspecialchars($pageCode) ?>" <?= in_array($pageCode, $selectedPages, true) || $isAdminRole ? 'checked' : '' ?> <?= $isAdminRole ? 'disabled' : '' ?>><?= htmlspecialchars($pageName) ?></label><?php endforeach; ?></div>
        </div>
      </section>
      <div class="actions"><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/admin/roles">Отмена</a><button class="btn btn-primary" type="submit"><?= $isNewRole ? 'Создать' : 'Сохранить' ?></button></div>
    </form>
    <?php if (!$isNewRole && (int) ($editedRole['is_system'] ?? 0) !== 1 && !$isAdminRole): ?><div class="delete-row"><form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/roles/delete" onsubmit="return confirm('Удалить роль?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="role_id" value="<?= (int) $editedRole['id'] ?>"><button class="btn btn-danger" type="submit">Удалить роль</button></form></div><?php endif; ?>
  </main>
</div>
</body></html>
