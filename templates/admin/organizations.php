<?php
$basePath = $basePath ?? '';
$search = $search ?? '';
$organizations = $organizations ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Организации</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .admin-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; flex-wrap:wrap; }
    .admin-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .admin-title small { display:block; font-size:12px; font-weight:500; color:var(--text-3); margin-top:2px; }
    .search-row { display:flex; gap:8px; align-items:center; }
    .search-input { border:1px solid var(--border); border-radius:9px; padding:7px 12px; font-family:inherit; font-size:13px; outline:none; color:var(--text-1); width:300px; max-width:55vw; }
    .search-input:focus { border-color:var(--accent); }
    .flash { padding:10px 14px; border-radius:9px; font-size:13px; margin-bottom:16px; }
    .flash-ok { background:#e8f6ef; color:var(--brand-green); border:1px solid #bfe6d2; }
    .flash-err { background:#fbecec; color:var(--brand-neg); border:1px solid #f0c9c9; }
    .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:visible; }
    .panel + .panel { margin-top:18px; }
    .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
    .panel-title { font-size:14px; font-weight:600; }
    .organization-form { display:grid; grid-template-columns:180px minmax(280px, 1fr) minmax(220px, 1fr) auto; gap:10px; padding:16px 18px; align-items:end; }
    .fg { display:flex; flex-direction:column; gap:5px; }
    .fg label { font-size:12px; font-weight:600; color:var(--text-2); }
    .fg input { border:1px solid var(--border); border-radius:8px; padding:8px 10px; font-family:inherit; font-size:13px; color:var(--text-1); outline:none; width:100%; box-sizing:border-box; }
    .fg input:focus { border-color:var(--accent); }
    .data-table { width:100%; border-collapse:collapse; }
    .data-table th { padding:9px 14px; text-align:left; font-size:11px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid var(--border); white-space:nowrap; }
    .data-table td { padding:10px 14px; border-bottom:1px solid var(--border); font-size:13px; vertical-align:middle; color:var(--text-1); }
    .data-table tbody tr:last-child td { border-bottom:none; }
    .data-table tbody tr:hover td { background:var(--hover-green,#f5f4f9); }
    .organization-code { font-weight:700; font-family:var(--mono, monospace); color:var(--accent); }
    .muted { color:var(--text-3); }
    .status { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; white-space:nowrap; }
    .status-dot { width:7px; height:7px; border-radius:50%; flex:none; }
    .dot-on { background:var(--brand-green); }
    .dot-off { background:var(--text-3); }
    .actions-cell { white-space:nowrap; display:flex; justify-content:flex-end; gap:4px; align-items:center; }
    .inline-form { display:inline-flex; }
    .icon-btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width:30px;
      height:30px;
      border:none;
      border-radius:8px;
      background:transparent;
      cursor:pointer;
      color:var(--text-3);
      transition:background .15s, color .15s;
      padding:0;
      flex-shrink:0;
    }
    .icon-btn--edit:hover { background:var(--hover-green); color:var(--accent); }
    .icon-btn--off:hover { background:#fbeaea; color:var(--brand-neg,#d94040); }
    .icon-btn--on:hover { background:#e8f6ef; color:var(--brand-green); }
    .empty { padding:28px; text-align:center; color:var(--text-3); font-size:13px; }
    .modal-wrap { position:fixed; inset:0; background:rgba(27,23,38,.45); display:none; align-items:center; justify-content:center; z-index:200; }
    .modal-wrap.open { display:flex; }
    .modal { background:var(--surface); border-radius:14px; width:560px; max-width:92vw; overflow:hidden; box-shadow:0 24px 60px rgba(27,23,38,.25); }
    .modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
    .modal-head .t { font-size:15px; font-weight:700; }
    .modal-x { cursor:pointer; color:var(--text-3); font-size:18px; line-height:1; background:none; border:none; }
    .modal-x:hover { color:var(--text-1); }
    .modal-body { padding:20px; display:flex; flex-direction:column; gap:14px; }
    .modal-foot { padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; }
    @media (max-width: 1050px) {
      .organization-form { grid-template-columns:1fr 1fr; }
    }
  </style>
</head>
<body>

<?php
  $headerSub = '<div class="brand-sub">Администрирование</div>';
  $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" id="backBtn" title="На главную"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
  $headerRight = '';
  include __DIR__ . '/../partials/header.php';
?>

<div class="app-body">
  <?php $activeAdminPage = 'organizations'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>

  <main class="main-content">
    <div class="admin-head">
      <div class="admin-title">
        Организации
        <small>Справочник организаций</small>
      </div>
      <form method="GET" action="<?= htmlspecialchars($basePath) ?>/admin/directories/organizations" class="search-row">
        <input type="search" class="search-input" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Код или наименование">
        <button type="submit" class="btn btn-ghost">Найти</button>
      </form>
    </div>

    <?php if (!empty($flashOk)): ?>
      <div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div>
    <?php endif; ?>
    <?php if (!empty($flashErr)): ?>
      <div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Добавить организацию</span>
      </div>
      <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/organizations/save" class="organization-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="fg">
          <label for="new_code">Код</label>
          <input id="new_code" name="code" required maxlength="50" pattern="[A-Za-z][A-Za-z0-9_]*" placeholder="Код организации">
        </div>
        <div class="fg">
          <label for="new_name">Наименование</label>
          <input id="new_name" name="name" required maxlength="300" placeholder="Полное наименование организации">
        </div>
        <div class="fg">
          <label for="new_short_name">Краткое наименование</label>
          <input id="new_short_name" name="short_name" maxlength="150" placeholder="Краткое наименование организации">
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
      </form>
    </div>

    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">Организации (<?= count($organizations) ?>)</span>
      </div>

      <?php if (empty($organizations)): ?>
        <div class="empty">Записей нет</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th style="width:180px">Код</th>
              <th>Наименование</th>
              <th>Краткое наименование</th>
              <th style="width:120px">Статус</th>
              <th style="width:1%"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($organizations as $organization): ?>
              <?php
                $id = (int) ($organization['id'] ?? 0);
                $code = (string) ($organization['code'] ?? '');
                $name = (string) ($organization['name'] ?? '');
                $shortName = (string) ($organization['short_name'] ?? '');
                $isActive = (int) ($organization['is_active'] ?? 0) === 1;
              ?>
              <tr>
                <td><span class="organization-code"><?= htmlspecialchars($code) ?></span></td>
                <td><?= htmlspecialchars($name) ?></td>
                <td><?= $shortName !== '' ? htmlspecialchars($shortName) : '<span class="muted">—</span>' ?></td>
                <td>
                  <span class="status">
                    <span class="status-dot <?= $isActive ? 'dot-on' : 'dot-off' ?>"></span>
                    <?= $isActive ? 'Активна' : 'Отключена' ?>
                  </span>
                </td>
                <td>
                  <div class="actions-cell">
                    <button type="button"
                            class="icon-btn icon-btn--edit"
                            title="Редактировать"
                            data-edit-organization
                            data-id="<?= $id ?>"
                            data-code="<?= htmlspecialchars($code) ?>"
                            data-name="<?= htmlspecialchars($name) ?>"
                            data-short-name="<?= htmlspecialchars($shortName) ?>">
                      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.5 1.5a1.414 1.414 0 0 1 2 2L4 11l-3 1 1-3Z"/>
                      </svg>
                    </button>
                    <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/organizations/active" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= $id ?>">
                      <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                      <button type="submit"
                              class="icon-btn <?= $isActive ? 'icon-btn--off' : 'icon-btn--on' ?>"
                              title="<?= $isActive ? 'Отключить' : 'Включить' ?>"
                              onclick="return confirm('<?= $isActive ? 'Отключить' : 'Включить' ?> организацию?')">
                        <?php if ($isActive): ?>
                          <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                            <circle cx="7.5" cy="7.5" r="5.5"/>
                            <path d="M3.6 3.6l7.8 7.8"/>
                          </svg>
                        <?php else: ?>
                          <svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.5 7.5l3.2 3.2 6.8-7"/>
                          </svg>
                        <?php endif; ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </main>
</div>

<div class="modal-wrap" id="organizationModal">
  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/directories/organizations/save" class="modal">
    <div class="modal-head">
      <span class="t">Редактирование организации</span>
      <button type="button" class="modal-x" data-close-modal>✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="id" id="edit_id">
      <div class="fg">
        <label for="edit_code">Код</label>
        <input id="edit_code" name="code" required maxlength="50" pattern="[A-Za-z][A-Za-z0-9_]*">
      </div>
      <div class="fg">
        <label for="edit_name">Наименование</label>
        <input id="edit_name" name="name" required maxlength="300">
      </div>
      <div class="fg">
        <label for="edit_short_name">Краткое наименование</label>
        <input id="edit_short_name" name="short_name" maxlength="150">
      </div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" data-close-modal>Отмена</button>
      <button type="submit" class="btn btn-primary">Сохранить</button>
    </div>
  </form>
</div>

<script>
  var organizationModal = document.getElementById('organizationModal');

  function closeOrganizationModal() {
    organizationModal.classList.remove('open');
  }

  document.querySelectorAll('[data-edit-organization]').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('edit_id').value = button.dataset.id;
      document.getElementById('edit_code').value = button.dataset.code;
      document.getElementById('edit_name').value = button.dataset.name;
      document.getElementById('edit_short_name').value = button.dataset.shortName;
      organizationModal.classList.add('open');
      document.getElementById('edit_name').focus();
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach(function (button) {
    button.addEventListener('click', closeOrganizationModal);
  });

  organizationModal.addEventListener('click', function (event) {
    if (event.target === organizationModal) {
      closeOrganizationModal();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeOrganizationModal();
    }
  });
</script>
</body>
</html>
