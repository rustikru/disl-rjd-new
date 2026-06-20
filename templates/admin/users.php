<?php
$basePath = $basePath ?? '';

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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Пользователи</title>
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

    .row-actions { display:flex; gap:6px; justify-content:flex-end; }
    .inline-form { display:inline; }

    .pager { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:12px 18px; border-top:1px solid var(--border-lt); font-size:12.5px; color:var(--text-2); }
    .pager button { border:1px solid var(--border); background:var(--surface); border-radius:8px; padding:5px 11px; cursor:pointer; font-size:12.5px; color:var(--text-1); }
    .pager button:disabled { opacity:.4; cursor:default; }

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
    .pwd-row { display:flex; gap:8px; }
    .pwd-row input { flex:1; }
    .modal-foot { padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }

    /* pages-list — компактные чекбоксы ролей в модалке */
    .pages-list { display:flex; flex-direction:column; gap:7px; }
    .pages-list .lbl { font-size:11px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.05em; }
    .pg { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-1); cursor:pointer; }
    .pg input { width:15px; height:15px; accent-color:var(--accent); cursor:pointer; }

    /* Компактный выпадающий селектор ролей */
    .role-picker { position:relative; display:inline-block; }
    summary.role-picker-btn { cursor:pointer; display:flex; align-items:center; gap:4px; flex-wrap:wrap;
      padding:4px 8px 4px 6px; border:1px solid var(--border); border-radius:8px;
      list-style:none; }
    summary.role-picker-btn::marker,
    summary.role-picker-btn::-webkit-details-marker { display:none; content:''; }
    .rp-empty { font-size:12px; color:var(--text-3); white-space:nowrap; }
    .rp-arrow { font-size:10px; color:var(--text-3); margin-left:4px; flex:none; transition:transform .15s; }
    .role-picker[open] .rp-arrow { transform:rotate(180deg); }
    /* позиция переопределяется JS (fixed) чтобы вырваться из overflow:hidden панели */
    .role-picker-drop { position:absolute; top:calc(100% + 4px); left:0; z-index:200;
      background:var(--surface); border:1px solid var(--border); border-radius:10px;
      box-shadow:0 6px 20px rgba(27,23,38,.14); min-width:180px; padding:8px 0 0; }
    .rp-item { display:flex; align-items:center; gap:8px; padding:6px 14px; cursor:pointer; }
    .rp-item:hover { background:var(--hover-green); }
    .rp-item input { accent-color:var(--accent); width:15px; height:15px; flex:none; cursor:pointer; }
    .rp-foot { padding:8px 14px 10px; border-top:1px solid var(--border-lt); margin-top:4px; }
  </style>
</head>
<body>

<?php
  $headerSub   = '<div class="brand-sub">Администрирование</div>';
  $headerRight = '<a href="' . htmlspecialchars($basePath) . '/" class="btn btn-ghost btn-sm" id="backBtn" style="margin-right:4px">← На главную</a>';
  include __DIR__ . '/../partials/header.php';
?>

<div class="app-body">

  <?php $activeAdminPage = 'users'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>

  <main class="main-content">

    <div class="admin-head">
      <div class="admin-title">
        Пользователи
        <small>Управление доступом</small>
      </div>
      <div class="admin-actions">
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
            <th>Роли</th>
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
                <details class="role-picker">
                  <summary class="role-picker-btn">
                    <?php if (!empty($u['roles'])): ?>
                      <?php foreach ($u['roles'] as $ur): ?>
                        <span class="role-badge <?= $roleClass($ur['code']) ?>"><?= htmlspecialchars($ur['name']) ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="rp-empty">— не назначена —</span>
                    <?php endif; ?>
                    <span class="rp-arrow">▾</span>
                  </summary>
                  <div class="role-picker-drop">
                    <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users/roles">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                      <?php foreach ($roles as $r): ?>
                        <label class="rp-item">
                          <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>"
                            <?= in_array((int) $r['id'], array_map('intval', array_column($u['roles'], 'id')), true) ? 'checked' : '' ?>>
                          <span class="role-badge <?= $roleClass($r['code']) ?>"><?= htmlspecialchars($r['name']) ?></span>
                        </label>
                      <?php endforeach; ?>
                      <div class="rp-foot">
                        <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                      </div>
                    </form>
                  </div>
                </details>
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
        <div style="display:flex; flex-wrap:wrap; gap:6px 16px">
          <?php foreach ($roles as $r): ?>
            <label class="pg">
              <input type="checkbox" name="role_ids[]" value="<?= (int) $r['id'] ?>">
              <span class="role-badge <?= $roleClass($r['code']) ?>"><?= htmlspecialchars($r['name']) ?></span>
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

  // --- Role-picker: вырываем dropdown из overflow:hidden через position:fixed ---
  ;(function () {
    var pickers = [].slice.call(document.querySelectorAll('.role-picker'))
    for (var i = 0; i < pickers.length; i++) {
      (function (picker) {
        var drop = picker.querySelector('.role-picker-drop')
        if (!drop) return
        picker.addEventListener('toggle', function () {
          if (picker.open) {
            var r = picker.getBoundingClientRect()
            drop.style.position = 'fixed'
            drop.style.top      = (r.bottom + 4) + 'px'
            drop.style.left     = r.left + 'px'
            drop.style.minWidth = Math.max(r.width, 200) + 'px'
            drop.style.zIndex   = '500'
          } else {
            drop.style.cssText = ''
          }
        })
      })(pickers[i])
    }
  })()
</script>

<script>
  /* --- Кнопка «← На главную»: history.back() если пришли не со страницы /admin, иначе идём на / --- */
  ;(function () {
    var btn = document.getElementById('backBtn')
    if (!btn) return
    var base = <?= json_encode($basePath) ?>
    btn.addEventListener('click', function (e) {
      var ref = document.referrer
      if (ref && ref.indexOf(location.origin + base + '/admin') === -1 && history.length > 1) {
        e.preventDefault()
        history.back()
      }
      // иначе следуем href как обычная ссылка (на главную)
    })
  })()
</script>

</body>
</html>
