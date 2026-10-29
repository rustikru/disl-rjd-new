<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';
$organizations = $organizations ?? [];
$organizationsById = [];
foreach ($organizations as $organization) {
  $organizationsById[(int) $organization['id']] = $organization;
}
$organizationNames = static function (array $row) use ($organizationsById): array {
  $ids = array_values(array_unique(array_merge([(int) ($row['organization_id'] ?? 0)], array_map('intval', $row['organization_ids'] ?? []))));
  $names = [];
  foreach ($ids as $id) {
    if ($id > 0 && isset($organizationsById[$id])) {
      $organization = $organizationsById[$id];
      $names[] = (string) ($organization['short_name'] ?: $organization['name']);
    }
  }
  return $names;
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
    .user-link {
      font-weight: 400;
      color: var(--accent);
      text-decoration: none
    }

    .user-link:hover {
      text-decoration: underline
    }

    .muted {
      display: block;
      color: var(--text-3);
      font-size: 10.5px;
      margin-top: 2px
    }

    .pager {
      border-top: 1px solid var(--border-lt)
    }

    .pager .app-pagination {
      padding: 12px 18px
    }

    .pager-empty {
      padding: 12px 18px;
      text-align: center;
      color: var(--text-3);
      font-size: 12px
    }

    .modal-wrap {
      position: fixed;
      inset: 0;
      background: rgba(27, 23, 38, .45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 200
    }

    .modal-wrap.open {
      display: flex
    }

    .modal {
      background: var(--surface);
      border-radius: 14px;
      width: 520px;
      max-width: 92vw;
      overflow: hidden;
      box-shadow: 0 24px 60px rgba(27, 23, 38, .25)
    }

    .modal-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      border-bottom: 1px solid var(--border)
    }

    .modal-head .t {
      font-size: 15px;
      font-weight: 700
    }

    .modal-x {
      cursor: pointer;
      color: var(--text-3);
      font-size: 18px;
      background: none;
      border: none
    }

    .modal-body {
      padding: 20px;
      display: flex;
      flex-direction: column;
      gap: 14px
    }

    .fg {
      display: flex;
      flex-direction: column;
      gap: 5px
    }

    .fg label {
      font-size: 12px;
      font-weight: 600;
      color: var(--text-2)
    }

    .fg input,
    .fg select {
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 8px 11px;
      font: inherit;
      font-size: 13px;
      background: var(--surface)
    }

    .fg2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px
    }

    .checks {
      display: flex;
      flex-wrap: wrap;
      gap: 8px 16px
    }

    .check {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 12px
    }

    .check input {
      accent-color: var(--accent)
    }

    .modal-foot {
      padding: 14px 20px;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: flex-end;
      gap: 8px
    }

    @media(max-width:700px) {
      .fg2 {
        grid-template-columns: 1fr
      }
    }
  </style>
</head>

<body>
  <?php
  $headerSub = '<div class="brand-sub">Администрирование</div>';
  $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
  $headerRight = '';
  include __DIR__ . '/../partials/header.php';
  ?>
  <div class="app-body">
    <?php $activeAdminPage = 'users';
    include __DIR__ . '/../partials/admin-sidebar.php'; ?>
    <main class="main-content">
      <div class="ui-page-head">
        <div class="ui-page-title">Пользователи<small></small></div>
        <a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/admin/users/new">+ Пользователь</a>
      </div>
      <?php uiNotices($flashOk ?? null, $flashErr ?? null); ?>
      <section class="ui-panel">
        <div class="ui-panel-head"><span class="ui-panel-title">Пользователи (<?= count($users) ?>)</span><input
            type="search" id="userSearch" class="ui-search-input" placeholder="Поиск по имени, логину, e-mail…"
            autocomplete="off"></div>
        <div class="ui-table-wrap">
          <table class="ui-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>Имя</th>
                <th>E-mail</th>
                <th>Последняя авторизация</th>
                <th>Статус</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="usersBody">
              <?php foreach ($users as $row): ?>
                <?php
                $active = (int) ($row['is_active'] ?? 0) === 1;
                $name = (string) ($row['display_name'] ?: $row['username']);
                $rowOrganizations = $organizationNames($row);
                $searchText = mb_strtolower(implode(' ', [$name, $row['username'] ?? '', $row['email'] ?? '', implode(' ', $rowOrganizations), implode(' ', array_column($row['roles'] ?? [], 'name'))]));
                ?>
                <tr data-user data-search="<?= htmlspecialchars($searchText) ?>">
                  <td><?= (int) $row['id'] ?></td>
                  <td><a class="user-link"
                      href="<?= htmlspecialchars($basePath) ?>/admin/users/<?= (int) $row['id'] ?>/edit"><?= htmlspecialchars($row['username']) ?></a>
                  </td>
                  <td><?= htmlspecialchars($name) ?></td>
                  <td><?= !empty($row['email']) ? htmlspecialchars($row['email']) : '<span class="ui-muted">—</span>' ?>
                  </td>
                  <td>
                    <?= !empty($row['last_login_at']) ? htmlspecialchars($row['last_login_at']) : '<span class="ui-muted">—</span>' ?>
                  </td>
                  <td><span
                      class="ui-status <?= $active ? 'ui-status--on' : '' ?>"><?= $active ? 'Активен' : 'Отключён' ?></span>
                  </td>
                  <td><a class="ui-icon-button ui-icon-button--edit"
                      href="<?= htmlspecialchars($basePath) ?>/admin/users/<?= (int) $row['id'] ?>/edit"
                      title="Редактировать" aria-label="Редактировать"><?= uiEditIcon() ?></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="pager" id="usersPager" hidden>
          <div class="pager-empty" id="emptyNote" hidden>Ничего не найдено</div>
          <nav class="app-pagination" id="usersPagination" aria-label="Страницы списка пользователей"></nav>
        </div>
      </section>
    </main>
  </div>
  <script>
    ; (function () {
      var input = document.getElementById('userSearch'), rows = [].slice.call(document.querySelectorAll('#usersBody tr[data-user]')), per = 10, page = 1, filtered = rows, pager = document.getElementById('usersPager'), nav = document.getElementById('usersPagination'), empty = document.getElementById('emptyNote');
      function button(label, target, disabled, active) { var b = document.createElement('button'); b.type = 'button'; b.className = 'app-pagination__link' + (disabled ? ' is-disabled' : '') + (active ? ' is-active' : ''); b.textContent = label; b.disabled = disabled; b.onclick = function () { if (!disabled) { page = target; render() } }; return b }
      function render() { var pages = Math.max(1, Math.ceil(filtered.length / per)); page = Math.min(page, pages); rows.forEach(function (r) { r.hidden = true }); filtered.slice((page - 1) * per, page * per).forEach(function (r) { r.hidden = false }); empty.hidden = filtered.length > 0; nav.innerHTML = ''; nav.appendChild(button('‹', page - 1, page === 1, false)); var nums = [1, 2, 3, page - 1, page, page + 1, pages].filter(function (n, i, a) { return n > 0 && n <= pages && a.indexOf(n) === i }).sort(function (a, b) { return a - b }), prev = 0; nums.forEach(function (n) { if (prev && n - prev > 1) { var d = document.createElement('span'); d.className = 'app-pagination__dots'; d.textContent = '…'; nav.appendChild(d) } nav.appendChild(button(String(n), n, false, n === page)); prev = n }); nav.appendChild(button('›', page + 1, page === pages, false)); pager.hidden = filtered.length > 0 && filtered.length <= per }
      input.addEventListener('input', function () { var q = this.value.toLowerCase().trim(); filtered = q ? rows.filter(function (r) { return r.dataset.search.indexOf(q) >= 0 }) : rows; page = 1; render() }); render();
    })()
  </script>
</body>

</html>