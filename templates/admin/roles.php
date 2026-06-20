<?php
/** @var string  $appName */
/** @var string  $basePath */
/** @var array   $user */
/** @var array   $roles     список ролей [id, code, name, description, is_system] */
/** @var array   $rolePages [role_id => [page => true]] */
/** @var array   $pages     [code => name] */
/** @var ?string $flashOk */
/** @var ?string $flashErr */
/** @var string  $csrf */
$basePath = $basePath ?? '';

// Боковое меню — раздел администрирования
$navGroups = [
    ['label' => 'Администрирование', 'items' => [
        ['label' => 'Пользователи', 'url' => $basePath . '/admin/users'],
        ['label' => 'Роли',         'url' => $basePath . '/admin/roles', 'active' => true],
    ]],
];

// CSS-класс бейджа по коду роли
$roleClass = function (?string $code) {
    switch ($code) {
        case 'ADMIN':    return 'role-admin';
        case 'OPERATOR': return 'role-operator';
        case 'VIEWER':   return 'role-viewer';
        default:         return 'role-custom';
    }
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Роли</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    /* --- Раздел администрирования --- */
    .admin-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
    .admin-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .admin-title small { display:block; font-size:12px; font-weight:500; color:var(--text-3); margin-top:2px; }
    .admin-actions { display:flex; gap:8px; align-items:center; }

    .flash { padding:10px 14px; border-radius:9px; font-size:13px; margin-bottom:16px; }
    .flash-ok  { background:#e8f6ef; color:var(--brand-green); border:1px solid #bfe6d2; }
    .flash-err { background:#fbecec; color:var(--brand-neg);   border:1px solid #f0c9c9; }

    .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .panel + .panel { margin-top:18px; }
    .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .panel-title { font-size:14px; font-weight:600; }

    .role-badge { display:inline-flex; align-items:center; padding:3px 11px; border-radius:99px; font-size:11.5px; font-weight:600; }
    .role-admin    { background:var(--accent-lt); color:var(--accent); }
    .role-operator { background:#e4eefa; color:var(--brand-blue); }
    .role-viewer   { background:var(--hover-green); color:var(--text-2); }
    .role-custom   { background:#fbf0e3; color:#a4671b; }

    /* Роли — карточки */
    .roles-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:14px; padding:18px; }
    .role-card { border:1px solid var(--border); border-radius:11px; padding:14px 16px; display:flex; flex-direction:column; gap:10px; }
    .role-card-head { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .role-code { font-family:monospace; font-size:11px; color:var(--text-3); }
    .role-card input.t-name { border:1px solid var(--border); border-radius:8px; padding:6px 9px; font-family:inherit; font-size:13px; font-weight:600; outline:none; color:var(--text-1); width:100%; }
    .role-card input.t-desc { border:1px solid var(--border); border-radius:8px; padding:6px 9px; font-family:inherit; font-size:12px; outline:none; color:var(--text-2); width:100%; }
    .role-card input:focus { border-color:var(--accent); }
    .pages-list { display:flex; flex-direction:column; gap:7px; }
    .pages-list .lbl { font-size:11px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.05em; }
    .pg { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-1); cursor:pointer; }
    .pg input { width:15px; height:15px; accent-color:var(--accent); cursor:pointer; }
    .role-card-foot { display:flex; gap:6px; margin-top:2px; }
    .role-static-note { font-size:12px; color:var(--text-2); background:var(--accent-lt); border-radius:8px; padding:8px 10px; }

    /* Модалки на JS */
    .modal-wrap { position:fixed; inset:0; background:rgba(27,23,38,.45); display:none; align-items:center; justify-content:center; z-index:200; }
    .modal-wrap.open { display:flex; }
    .modal { background:var(--surface); border-radius:14px; width:440px; max-width:92vw; overflow:hidden; box-shadow:0 24px 60px rgba(27,23,38,.25); }
    .modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
    .modal-head .t { font-size:15px; font-weight:700; }
    .modal-x { cursor:pointer; color:var(--text-3); font-size:18px; line-height:1; background:none; border:none; }
    .modal-x:hover { color:var(--text-1); }
    .modal-body { padding:20px; display:flex; flex-direction:column; gap:14px; }
    .fg { display:flex; flex-direction:column; gap:5px; }
    .fg label { font-size:12px; font-weight:600; color:var(--text-2); }
    .fg input { border:1px solid var(--border); border-radius:8px; padding:8px 11px; font-family:inherit; font-size:13px; outline:none; color:var(--text-1); }
    .fg input:focus { border-color:var(--accent); }
    .fg2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .modal-foot { padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }
  </style>
</head>
<body>

<?php
  $headerSub   = '<div class="brand-sub">Администрирование</div>';
  $headerRight = '';
  include __DIR__ . '/../partials/header.php';
?>

<div class="app-body">

  <aside class="sidebar">
    <?php foreach ($navGroups as $group): ?>
      <div class="nav-group">
        <span class="nav-group-label"><?= htmlspecialchars($group['label']) ?></span>
        <?php foreach ($group['items'] as $item): ?>
          <a class="nav-item<?= !empty($item['active']) ? ' active' : '' ?>"
             href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </aside>

  <main class="main-content">

    <div class="admin-head">
      <div class="admin-title">
        Роли
        <small>Управление ролями и доступом</small>
      </div>
      <div class="admin-actions">
        <button type="button" class="btn btn-primary" onclick="openModal('roleModal')">+ Добавить роль</button>
      </div>
    </div>

    <?php if (!empty($flashOk)): ?>
      <div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashErr)): ?>
      <div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <!-- Роли -->
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Роли</span>
      </div>
      <div class="roles-grid">
        <?php foreach ($roles as $r): ?>
          <?php $isAdminRole = ($r['code'] ?? '') === 'ADMIN'; ?>
          <div class="role-card">
            <div class="role-card-head">
              <span class="role-badge <?= $roleClass($r['code']) ?>"><?= htmlspecialchars($r['name']) ?></span>
              <span class="role-code"><?= htmlspecialchars($r['code']) ?></span>
            </div>

            <?php if ($isAdminRole): ?>
              <div class="role-static-note">Полный доступ ко всем разделам. Доступ роли «Администратор» изменить нельзя.</div>
            <?php else: ?>
              <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/roles/save" style="display:flex; flex-direction:column; gap:10px">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="role_id" value="<?= (int) $r['id'] ?>">
                <input class="t-name" name="name" value="<?= htmlspecialchars($r['name']) ?>" placeholder="Название роли">
                <input class="t-desc" name="description" value="<?= htmlspecialchars($r['description'] ?? '') ?>" placeholder="Описание">
                <div class="pages-list">
                  <span class="lbl">Доступ к разделам</span>
                  <?php foreach ($pages as $code => $label): ?>
                    <label class="pg">
                      <input type="checkbox" name="pages[]" value="<?= htmlspecialchars($code) ?>"
                        <?= !empty($rolePages[(int) $r['id']][$code]) ? 'checked' : '' ?>>
                      <?= htmlspecialchars($label) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
                <div class="role-card-foot">
                  <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                  <?php if ((int) ($r['is_system'] ?? 0) === 0): ?>
                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--brand-neg)"
                            formaction="<?= htmlspecialchars($basePath) ?>/admin/roles/delete"
                            onclick="return confirm('Удалить роль «<?= htmlspecialchars(addslashes($r['name']), ENT_QUOTES) ?>»?')">Удалить</button>
                  <?php endif; ?>
                </div>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </main>
</div>

<!-- Модалка: добавить роль -->
<div class="modal-wrap" id="roleModal">
  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/roles" class="modal">
    <div class="modal-head">
      <span class="t">Новая роль</span>
      <button type="button" class="modal-x" onclick="closeModal('roleModal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="fg2">
        <div class="fg">
          <label>Код (латиницей)</label>
          <input type="text" name="code" placeholder="MANAGER" required>
        </div>
        <div class="fg">
          <label>Название</label>
          <input type="text" name="name" placeholder="Менеджер" required>
        </div>
      </div>
      <div class="fg">
        <label>Описание</label>
        <input type="text" name="description" placeholder="Краткое описание роли">
      </div>
      <div class="fg">
        <label>Доступ к разделам</label>
        <div class="pages-list">
          <?php foreach ($pages as $code => $label): ?>
            <label class="pg">
              <input type="checkbox" name="pages[]" value="<?= htmlspecialchars($code) ?>"
                <?= in_array($code, ['dashboard', 'maps'], true) ? 'checked' : '' ?>>
              <?= htmlspecialchars($label) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('roleModal')">Отмена</button>
      <button type="submit" class="btn btn-primary">Создать роль</button>
    </div>
  </form>
</div>

<script>
  'use strict'
  // --- Модалки ---
  function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('open') }
  function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('open') }
  // закрытие по клику на подложку и по Esc
  var wraps = document.querySelectorAll('.modal-wrap')
  for (var i = 0; i < wraps.length; i++) {
    wraps[i].addEventListener('click', function (e) {
      if (e.target === this) this.classList.remove('open')
    })
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      for (var j = 0; j < wraps.length; j++) wraps[j].classList.remove('open')
    }
  })
</script>

</body>
</html>
