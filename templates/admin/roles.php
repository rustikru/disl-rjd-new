<?php
$basePath = $basePath ?? '';

// CSS-класс бейджа по коду роли
$roleClass = function (?string $code) {
    switch ($code) {
        case 'ADMIN':    return 'role-admin';
        case 'OPERATOR': return 'role-operator';
        case 'VIEWER':   return 'role-viewer';
        default:         return 'role-custom';
    }
};

$totalPages = count($pages);
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
    /* --- Заголовок раздела --- */
    .admin-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
    .admin-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .admin-title small { display:block; font-size:12px; font-weight:500; color:var(--text-3); margin-top:2px; }
    .admin-actions { display:flex; gap:8px; align-items:center; }

    /* --- Flash-сообщения --- */
    .flash { padding:10px 14px; border-radius:9px; font-size:13px; margin-bottom:16px; }
    .flash-ok  { background:#e8f6ef; color:var(--brand-green); border:1px solid #bfe6d2; }
    .flash-err { background:#fbecec; color:var(--brand-neg);   border:1px solid #f0c9c9; }

    /* --- Панель --- */
    .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:visible; }
    .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .panel-title { font-size:14px; font-weight:600; }

    /* --- Бейджи ролей --- */
    .role-badge { display:inline-flex; align-items:center; padding:3px 11px; border-radius:99px; font-size:11.5px; font-weight:600; }
    .role-admin    { background:var(--accent-lt); color:var(--accent); }
    .role-operator { background:#e4eefa; color:var(--brand-blue); }
    .role-viewer   { background:var(--hover-green); color:var(--text-2); }
    .role-custom   { background:#fbf0e3; color:#a4671b; }

    /* --- Таблица --- */
    .data-table { width:auto; border-collapse:collapse; }
    .data-table th {
      padding:9px 14px;
      text-align:left;
      font-size:11px;
      font-weight:700;
      color:var(--text-3);
      text-transform:uppercase;
      letter-spacing:.05em;
      border-bottom:1px solid var(--border);
      white-space:nowrap;
    }
    .data-table td {
      padding:10px 14px;
      vertical-align:middle;
      border-bottom:1px solid var(--border);
      font-size:13px;
      color:var(--text-1);
    }
    .data-table tbody tr:last-child td { border-bottom:none; }
    .data-table tbody tr:hover td { background:var(--hover-green,#f5f4f9); }

    /* --- Инпуты в таблице --- */
    .tbl-input {
      border:1px solid var(--border);
      border-radius:7px;
      padding:5px 9px;
      font-family:inherit;
      font-size:13px;
      outline:none;
      color:var(--text-1);
      width:100%;
      background:transparent;
    }
    .tbl-input:focus { border-color:var(--accent); background:var(--surface); }

    /* --- Выпадающий список разделов --- */
    details.pg-drop { position:relative; display:inline-block; }
    summary.pg-drop-btn {
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border:1px solid var(--border);
      border-radius:7px;
      font-size:13px;
      font-weight:500;
      color:var(--text-1);
      cursor:pointer;
      list-style:none;
      user-select:none;
      white-space:nowrap;
    }
    summary.pg-drop-btn::-webkit-details-marker { display:none; }
    summary.pg-drop-btn::marker { display:none; }
    summary.pg-drop-btn::after { content:'▾'; font-size:11px; color:var(--text-3); }
    details[open] summary.pg-drop-btn { border-color:var(--accent); }

    .pg-drop-menu {
      position:absolute;
      top:calc(100% + 4px);
      left:0;
      min-width:180px;
      background:var(--surface);
      border:1px solid var(--border);
      border-radius:9px;
      box-shadow:0 6px 24px rgba(27,23,38,.13);
      padding:8px 0;
      z-index:300;
    }
    .pg-item {
      display:flex;
      align-items:center;
      gap:9px;
      padding:7px 14px;
      font-size:13px;
      color:var(--text-1);
      cursor:pointer;
    }
    .pg-item:hover { background:var(--hover-green,#f5f4f9); }
    .pg-item input { width:15px; height:15px; accent-color:var(--accent); cursor:pointer; flex-shrink:0; }

    /* --- Примечание ADMIN --- */
    .admin-note {
      font-size:12px;
      color:var(--text-3);
      font-style:italic;
    }

    /* --- Кнопки действий --- */
    .actions-cell { white-space:nowrap; display:flex; gap:6px; align-items:center; }
    .btn-save, .btn-del {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:30px;
      height:30px;
      border:none;
      border-radius:8px;
      background:transparent;
      cursor:pointer;
      transition:background .15s, color .15s;
      padding:0;
      flex-shrink:0;
    }
    .btn-save { color:var(--brand-green,#2aa26b); }
    .btn-save:hover { background:#e8f6ef; }
    .btn-del  { color:var(--text-3); }
    .btn-del:hover  { background:#fbeaea; color:var(--brand-neg,#d94040); }

    /* --- Модалки --- */
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
  $headerRight = '<a href="' . htmlspecialchars($basePath) . '/" class="btn btn-ghost btn-sm" id="backBtn" style="margin-right:4px">← На главную</a>';
  include __DIR__ . '/../partials/header.php';
?>

<div class="app-body">

  <?php $activeAdminPage = 'roles'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>

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

    <!--
      Внешние формы (по одной на каждую редактируемую роль).
      Поля таблицы ссылаются на форму через атрибут form="rf-{id}".
    -->
    <?php foreach ($roles as $r): ?>
      <?php if (($r['code'] ?? '') !== 'ADMIN'): ?>
        <form id="rf-<?= (int) $r['id'] ?>"
              method="POST"
              action="<?= htmlspecialchars($basePath) ?>/admin/roles/save"
              style="display:none">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="role_id"    value="<?= (int) $r['id'] ?>">
        </form>
        <form id="rfd-<?= (int) $r['id'] ?>"
              method="POST"
              action="<?= htmlspecialchars($basePath) ?>/admin/roles/delete"
              style="display:none">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="role_id"    value="<?= (int) $r['id'] ?>">
        </form>
      <?php endif; ?>
    <?php endforeach; ?>

    <!-- Таблица ролей -->
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Роли (<?= count($roles) ?>)</span>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th style="width:1%;white-space:nowrap">Код</th>
            <th style="width:160px">Название</th>
            <th>Описание</th>
            <th style="width:1%;white-space:nowrap">Разделы доступа</th>
            <th style="width:1%;white-space:nowrap"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $r): ?>
            <?php $isAdmin = ($r['code'] ?? '') === 'ADMIN'; ?>
            <?php
              // Количество доступных разделов для этой роли
              $grantedCount = count($rolePages[(int) $r['id']] ?? []);
              $pgSummary = $grantedCount > 0
                ? $grantedCount . ' из ' . $totalPages
                : 'Не выбраны';
            ?>
            <tr>
              <!-- Код роли -->
              <td>
                <span class="role-badge <?= $roleClass($r['code'] ?? null) ?>">
                  <?= htmlspecialchars($r['code'] ?? '') ?>
                </span>
              </td>

              <!-- Название -->
              <td>
                <?php if ($isAdmin): ?>
                  <span style="font-weight:600"><?= htmlspecialchars($r['name']) ?></span>
                <?php else: ?>
                  <input class="tbl-input"
                         name="name"
                         value="<?= htmlspecialchars($r['name']) ?>"
                         placeholder="Название"
                         form="rf-<?= (int) $r['id'] ?>">
                <?php endif; ?>
              </td>

              <!-- Описание -->
              <td style="min-width:200px">
                <?php if ($isAdmin): ?>
                  <span class="admin-note">Полный доступ ко всем разделам</span>
                <?php else: ?>
                  <textarea class="tbl-input"
                            name="description"
                            placeholder="Описание"
                            rows="2"
                            style="resize:vertical;min-height:36px;vertical-align:top"
                            form="rf-<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['description'] ?? '') ?></textarea>
                <?php endif; ?>
              </td>

              <!-- Разделы доступа -->
              <td>
                <?php if ($isAdmin): ?>
                  <span class="admin-note">Все</span>
                <?php else: ?>
                  <details class="pg-drop" id="pgd-<?= (int) $r['id'] ?>">
                    <summary class="pg-drop-btn" id="pgds-<?= (int) $r['id'] ?>">
                      <?= htmlspecialchars($pgSummary) ?>
                    </summary>
                    <div class="pg-drop-menu" id="pgdm-<?= (int) $r['id'] ?>">
                      <?php foreach ($pages as $code => $label): ?>
                        <label class="pg-item">
                          <input type="checkbox"
                                 name="pages[]"
                                 value="<?= htmlspecialchars($code) ?>"
                                 form="rf-<?= (int) $r['id'] ?>"
                                 data-summary="pgds-<?= (int) $r['id'] ?>"
                                 data-total="<?= $totalPages ?>"
                                 data-menu="pgdm-<?= (int) $r['id'] ?>"
                                 <?= !empty($rolePages[(int) $r['id']][$code]) ? 'checked' : '' ?>>
                          <?= htmlspecialchars($label) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </details>
                <?php endif; ?>
              </td>

              <!-- Действия -->
              <td>
                <?php if (!$isAdmin): ?>
                  <div class="actions-cell">
                    <button type="submit" class="btn-save"
                            form="rf-<?= (int) $r['id'] ?>" title="Сохранить">
                      <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><polyline points="2.5 8 5.5 11.5 12.5 3.5"/></svg>
                    </button>
                    <button type="submit"
                            class="btn-del"
                            form="rfd-<?= (int) $r['id'] ?>"
                            title="Удалить роль"
                            onclick="return confirm('Удалить роль «<?= htmlspecialchars(addslashes($r['name']), ENT_QUOTES) ?>»?')">
                      <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 3.5 14 3.5"/>
                        <path d="M5 3.5V2.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v1"/>
                        <path d="M2.5 3.5l.9 9a1 1 0 0 0 1 .9h6.2a1 1 0 0 0 1-.9l.9-9"/>
                      </svg>
                    </button>
                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
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
        <textarea name="description" placeholder="Краткое описание роли" rows="2"
                  style="border:1px solid var(--border);border-radius:8px;padding:8px 11px;font-family:inherit;font-size:13px;outline:none;color:var(--text-1);resize:vertical;min-height:36px"></textarea>
      </div>
      <div class="fg">
        <label>Доступ к разделам</label>
        <div class="pages-list" style="display:flex;flex-direction:column;gap:7px">
          <?php foreach ($pages as $code => $label): ?>
            <label class="pg-item" style="padding:0">
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

  /* --- Модалки --- */
  function openModal(id) { var m = document.getElementById(id); if (m) m.classList.add('open') }
  function closeModal(id) { var m = document.getElementById(id); if (m) m.classList.remove('open') }
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

  /* --- Выпадающий список разделов: position:fixed чтобы не обрезался --- */
  var drops = document.querySelectorAll('details.pg-drop')
  for (var d = 0; d < drops.length; d++) {
    (function (picker) {
      var menuId = picker.id.replace('pgd-', 'pgdm-')
      var drop   = document.getElementById(menuId)
      if (!drop) return
      picker.addEventListener('toggle', function () {
        if (picker.open) {
          var r = picker.getBoundingClientRect()
          drop.style.position = 'fixed'
          drop.style.top      = (r.bottom + 4) + 'px'
          drop.style.left     = r.left + 'px'
          drop.style.minWidth = Math.max(r.width, 180) + 'px'
          drop.style.zIndex   = '500'
        } else {
          drop.style.cssText = ''
        }
      })
    })(drops[d])
  }

  /* --- Закрытие dropdown при клике вне --- */
  document.addEventListener('click', function (e) {
    for (var d = 0; d < drops.length; d++) {
      if (drops[d].open && !drops[d].contains(e.target)) {
        drops[d].removeAttribute('open')
      }
    }
  })

  /* --- Обновление счётчика разделов в summary при изменении чекбоксов --- */
  var boxes = document.querySelectorAll('.pg-drop-menu input[type="checkbox"]')
  for (var b = 0; b < boxes.length; b++) {
    boxes[b].addEventListener('change', function () {
      var summId = this.getAttribute('data-summary')
      var menuId = this.getAttribute('data-menu')
      var total  = parseInt(this.getAttribute('data-total'), 10)
      var summ   = document.getElementById(summId)
      var menu   = document.getElementById(menuId)
      if (!summ || !menu) return
      var all     = menu.querySelectorAll('input[type="checkbox"]')
      var checked = 0
      for (var k = 0; k < all.length; k++) {
        if (all[k].checked) checked++
      }
      summ.textContent = checked > 0 ? (checked + ' из ' + total) : 'Не выбраны'
    })
  }
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
