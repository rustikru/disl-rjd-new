<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $user */
/** @var array  $roles  список ролей [id, code, name, description, is_system] */
/** @var array  $users  список пользователей */
/** @var array  $pages  доступные страницы [code => name] */
/** @var array  $rolePages  [role_id => [page => true]] */
/** @var ?string $flashOk */
/** @var ?string $flashErr */
$basePath = $basePath ?? '';

// Боковое меню — повторяет навигацию основного приложения (templates/app.php / app.js)
$navGroups = [
    ['label' => 'Движение вагонов', 'items' => [
        ['label' => 'Дислокация',          'url' => $basePath . '/#dislocation'],
        ['label' => 'Подход вагонов',      'url' => $basePath . '/#approach'],
        ['label' => 'Отправление вагонов', 'url' => $basePath . '/#departure'],
        ['label' => 'Погрузка',            'url' => $basePath . '/#loading'],
        ['label' => 'Сырьё',               'url' => $basePath . '/#raw-material'],
    ]],
    ['label' => 'Аналитика', 'items' => [
        ['label' => 'Анализ данных за период', 'url' => $basePath . '/#analysis-period'],
        ['label' => 'Карта',                   'url' => $basePath . '/maps'],
    ]],
    ['label' => 'Простои и оборот', 'items' => [
        ['label' => 'Простои', 'url' => $basePath . '/#downtime'],
    ]],
    ['label' => 'Импорт', 'items' => [
        ['label' => 'Загрузка справки РЖД', 'url' => $basePath . '/import'],
    ]],
    ['label' => 'Администрирование', 'items' => [
        ['label' => 'Пользователи', 'url' => $basePath . '/admin', 'active' => true],
    ]],
];

// Инициалы для аватара
$initials = function (string $name) {
    $parts = preg_split('/\s+/', trim($name));
    $a = mb_substr($parts[0] ?? '', 0, 1);
    $b = mb_substr($parts[1] ?? '', 0, 1);
    return mb_strtoupper($a . $b);
};

// CSS-класс бейджа по коду роли
$roleClass = function (?string $code) {
    switch ($code) {
        case 'ADMIN':    return 'role-admin';
        case 'OPERATOR': return 'role-operator';
        case 'VIEWER':   return 'role-viewer';
        default:         return 'role-custom';
    }
};
$csrf = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Администрирование</title>
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
    .search-input { border:1px solid var(--border); border-radius:9px; padding:7px 12px; font-family:inherit; font-size:13px; outline:none; color:var(--text-1); width:240px; max-width:50vw; }
    .search-input:focus { border-color:var(--accent); }

    .admin-table { width:100%; border-collapse:collapse; }
    .admin-table th { padding:10px 16px; text-align:left; font-size:10.5px; font-weight:700; color:var(--text-3);
      text-transform:uppercase; letter-spacing:.06em; background:var(--row-head); border-bottom:1px solid var(--border); }
    .admin-table td { padding:11px 16px; border-bottom:1px solid var(--border-lt); vertical-align:middle; font-size:13px; }
    .admin-table tr:last-child td { border-bottom:none; }
    .admin-table tbody tr:hover td { background:var(--hover-green); }

    .u-cell { display:flex; align-items:center; gap:10px; }
    .u-avatar { width:30px; height:30px; border-radius:50%; background:var(--accent-lt); color:var(--accent);
      font-size:11.5px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; flex:none; }
    .u-name { font-weight:600; }
    .u-login { font-size:11px; color:var(--text-3); }

    .role-badge { display:inline-flex; align-items:center; padding:3px 11px; border-radius:99px; font-size:11.5px; font-weight:600; }
    .role-admin    { background:var(--accent-lt); color:var(--accent); }
    .role-operator { background:#e4eefa; color:var(--brand-blue); }
    .role-viewer   { background:var(--hover-green); color:var(--text-2); }
    .role-custom   { background:#fbf0e3; color:#a4671b; }

    .status { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; }
    .status-dot { width:7px; height:7px; border-radius:50%; flex:none; }
    .dot-on  { background:var(--brand-green); }
    .dot-off { background:var(--text-3); }

    .row-form { display:flex; align-items:center; gap:6px; }
    .row-actions { display:flex; gap:6px; justify-content:flex-end; }
    .role-select { border:1px solid var(--border); border-radius:8px; padding:5px 9px; font-family:inherit;
      font-size:12.5px; color:var(--text-1); background:var(--surface); cursor:pointer; outline:none; }
    .role-select:focus { border-color:var(--accent); }
    .inline-form { display:inline; }

    .pager { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:12px 18px; border-top:1px solid var(--border-lt); font-size:12.5px; color:var(--text-2); }
    .pager button { border:1px solid var(--border); background:var(--surface); border-radius:8px; padding:5px 11px; cursor:pointer; font-size:12.5px; color:var(--text-1); }
    .pager button:disabled { opacity:.4; cursor:default; }

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
    .fg input, .fg select { border:1px solid var(--border); border-radius:8px; padding:8px 11px; font-family:inherit; font-size:13px; outline:none; color:var(--text-1); }
    .fg input:focus, .fg select:focus { border-color:var(--accent); }
    .fg2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .pwd-row { display:flex; gap:8px; }
    .pwd-row input { flex:1; }
    .modal-foot { padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }
  </style>
</head>
<body>

<?php
  $headerSub   = '<div class="brand-sub">Администрирование</div>';
  $headerRight = '';
  include __DIR__ . '/partials/header.php';
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
        Пользователи и роли
        <small>Управление доступом к разделам приложения</small>
      </div>
      <div class="admin-actions">
        <button type="button" class="btn btn-ghost" onclick="openModal('roleModal')">+ Роль</button>
        <button type="button" class="btn btn-primary" onclick="openModal('userModal')">+ Пользователь</button>
      </div>
    </div>

    <?php if (!empty($flashOk)): ?>
      <div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashErr)): ?>
      <div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <!-- Пользователи -->
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Пользователи (<?= count($users) ?>)</span>
        <input type="search" id="userSearch" class="search-input" placeholder="Поиск по имени, логину, e-mail…" autocomplete="off">
      </div>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Пользователь</th>
            <th>E-mail</th>
            <th>Роль</th>
            <th>Статус</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="usersBody">
          <?php foreach ($users as $u): ?>
            <?php
              $isActive = (int) ($u['is_active'] ?? 0) === 1;
              $name = (string) ($u['display_name'] ?: $u['username']);
              $haystack = mb_strtolower($name . ' ' . ($u['username'] ?? '') . ' ' . ($u['email'] ?? ''));
            ?>
            <tr data-user data-search="<?= htmlspecialchars($haystack) ?>">
              <td>
                <div class="u-cell">
                  <span class="u-avatar"><?= htmlspecialchars($initials($name)) ?></span>
                  <div>
                    <div class="u-name"><?= htmlspecialchars($name) ?></div>
                    <div class="u-login"><?= htmlspecialchars($u['username']) ?></div>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($u['email'] ?? '') ?: '<span style="color:var(--text-3)">—</span>' ?></td>
              <td>
                <?php
                  $userRoleIds = array_column($u['roles'], 'id');
                ?>
                <div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:6px">
                  <?php if (!empty($u['roles'])): ?>
                    <?php foreach ($u['roles'] as $ur): ?>
                      <span class="role-badge <?= $roleClass($ur['code']) ?>"><?= htmlspecialchars($ur['name']) ?></span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span style="color:var(--text-3)">—</span>
                  <?php endif; ?>
                </div>
                <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users/roles" class="row-form" style="flex-wrap:wrap; gap:6px 12px">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <?php foreach ($roles as $r): ?>
                    <label style="display:inline-flex; align-items:center; gap:4px; font-size:12px; cursor:pointer">
                      <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>"
                             <?= in_array((int) $r['id'], array_map('intval', $userRoleIds), true) ? 'checked' : '' ?>
                             style="accent-color:var(--accent)">
                      <?= htmlspecialchars($r['name']) ?>
                    </label>
                  <?php endforeach; ?>
                  <button type="submit" class="btn btn-ghost btn-sm">Сохранить</button>
                </form>
              </td>
              <td>
                <span class="status">
                  <span class="status-dot <?= $isActive ? 'dot-on' : 'dot-off' ?>"></span>
                  <?= $isActive ? 'Активен' : 'Заблокирован' ?>
                </span>
              </td>
              <td>
                <div class="row-actions">
                  <button type="button" class="btn btn-ghost btn-sm"
                          onclick="openPwd(<?= (int) $u['id'] ?>, '<?= htmlspecialchars(addslashes($name), ENT_QUOTES) ?>')">Пароль</button>
                  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users/active" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-ghost' : 'btn-primary' ?>">
                      <?= $isActive ? 'Заблокировать' : 'Разблокировать' ?>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr><td colspan="5" style="text-align:center; color:var(--text-3); padding:24px">Пользователей нет</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <div class="pager" id="usersPager" style="display:none">
        <span id="emptyNote" style="display:none; margin-right:auto; color:var(--text-3)">Ничего не найдено</span>
        <button type="button" id="prevBtn">←</button>
        <span id="pageInfo">1 / 1</span>
        <button type="button" id="nextBtn">→</button>
      </div>
    </div>

    <!-- Роли -->
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Роли</span>
        <button type="button" class="btn btn-ghost btn-sm" onclick="openModal('roleModal')">+ Добавить роль</button>
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

<!-- Модалка: добавить пользователя -->
<div class="modal-wrap" id="userModal">
  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users" class="modal">
    <div class="modal-head">
      <span class="t">Новый пользователь</span>
      <button type="button" class="modal-x" onclick="closeModal('userModal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <div class="fg">
        <label>Имя пользователя</label>
        <input type="text" name="display_name" placeholder="Иванов Иван" required>
      </div>
      <div class="fg2">
        <div class="fg">
          <label>Логин</label>
          <input type="text" name="username" placeholder="ivanov_i" required>
        </div>
        <div class="fg">
          <label>E-mail</label>
          <input type="email" name="email" placeholder="ivanov@mail.ru">
        </div>
      </div>
      <div class="fg">
        <label>Пароль <span style="color:var(--text-3); font-weight:400">(пусто — для входа через AD)</span></label>
        <input type="password" name="password" autocomplete="new-password">
      </div>
      <div class="fg">
        <label>Роли</label>
        <div class="pages-list">
          <?php foreach ($roles as $r): ?>
            <label class="pg">
              <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>">
              <?= htmlspecialchars($r['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('userModal')">Отмена</button>
      <button type="submit" class="btn btn-primary">Создать</button>
    </div>
  </form>
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

<!-- Модалка: сброс пароля -->
<div class="modal-wrap" id="pwdModal">
  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users/password" class="modal">
    <div class="modal-head">
      <span class="t">Сброс пароля</span>
      <button type="button" class="modal-x" onclick="closeModal('pwdModal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="user_id" id="pwdUserId" value="">
      <p style="font-size:13px; color:var(--text-2)">Новый пароль для <b id="pwdUserName"></b>:</p>
      <div class="fg">
        <div class="pwd-row">
          <input type="text" name="password" id="pwdInput" autocomplete="new-password" placeholder="Введите или сгенерируйте">
          <button type="button" class="btn btn-ghost btn-sm" onclick="genPwd()">Сгенерировать</button>
        </div>
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" onclick="closeModal('pwdModal')">Отмена</button>
      <button type="submit" class="btn btn-primary">Сохранить пароль</button>
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

  // --- Сброс пароля ---
  function openPwd(userId, userName) {
    document.getElementById('pwdUserId').value = userId
    document.getElementById('pwdUserName').textContent = userName
    document.getElementById('pwdInput').value = ''
    openModal('pwdModal')
  }
  function genPwd() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'
    var s = ''
    for (var i = 0; i < 12; i++) s += chars.charAt(Math.floor(Math.random() * chars.length))
    document.getElementById('pwdInput').value = s
  }

  // --- Поиск и пагинация по таблице пользователей ---
  ;(function () {
    var search = document.getElementById('userSearch')
    var rows = [].slice.call(document.querySelectorAll('#usersBody tr[data-user]'))
    if (!rows.length) return
    var PER = 10
    var page = 1
    var filtered = rows
    var pager = document.getElementById('usersPager')
    var prevBtn = document.getElementById('prevBtn')
    var nextBtn = document.getElementById('nextBtn')
    var pageInfo = document.getElementById('pageInfo')
    var emptyNote = document.getElementById('emptyNote')

    function totalPages() { return Math.max(1, Math.ceil(filtered.length / PER)) }

    function render() {
      var pages = totalPages()
      if (page > pages) page = pages
      var i
      for (i = 0; i < rows.length; i++) rows[i].style.display = 'none'
      var start = (page - 1) * PER
      for (i = start; i < start + PER && i < filtered.length; i++) filtered[i].style.display = ''
      pageInfo.textContent = page + ' / ' + pages
      emptyNote.style.display = filtered.length ? 'none' : ''
      prevBtn.disabled = page <= 1
      nextBtn.disabled = page >= pages
      pager.style.display = (filtered.length > PER || filtered.length === 0) ? '' : 'flex'
    }

    function apply() {
      var q = (search.value || '').toLowerCase().trim()
      filtered = q
        ? rows.filter(function (tr) { return tr.getAttribute('data-search').indexOf(q) !== -1 })
        : rows
      page = 1
      render()
    }

    search.addEventListener('input', apply)
    prevBtn.addEventListener('click', function () { if (page > 1) { page--; render() } })
    nextBtn.addEventListener('click', function () { if (page < totalPages()) { page++; render() } })
    render()
  })()
</script>

</body>
</html>
