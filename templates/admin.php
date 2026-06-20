<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $user */
/** @var array  $roles  список ролей [id, code, name, description] */
/** @var array  $users  список пользователей */
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
        default:         return 'role-viewer';
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
    .admin-head { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; }
    .admin-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .admin-title small { display:block; font-size:12px; font-weight:500; color:var(--text-3); margin-top:2px; }

    .flash { padding:10px 14px; border-radius:9px; font-size:13px; margin-bottom:16px; }
    .flash-ok  { background:#e8f6ef; color:var(--brand-green); border:1px solid #bfe6d2; }
    .flash-err { background:#fbecec; color:var(--brand-neg);   border:1px solid #f0c9c9; }

    .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .panel + .panel { margin-top:18px; }
    .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--border); }
    .panel-title { font-size:14px; font-weight:600; }

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
    .role-none     { background:#f4f3f6; color:var(--text-3); }

    .status { display:inline-flex; align-items:center; gap:6px; font-size:12.5px; }
    .status-dot { width:7px; height:7px; border-radius:50%; flex:none; }
    .dot-on  { background:var(--brand-green); }
    .dot-off { background:var(--text-3); }

    .row-form { display:flex; align-items:center; gap:6px; }
    .role-select { border:1px solid var(--border); border-radius:8px; padding:5px 9px; font-family:inherit;
      font-size:12.5px; color:var(--text-1); background:var(--surface); cursor:pointer; outline:none; }
    .role-select:focus { border-color:var(--accent); }
    .inline-form { display:inline; }

    /* модалка на CSS-чекбоксе */
    #toggleModal { display:none; }
    .modal-wrap { position:fixed; inset:0; background:rgba(27,23,38,.45); display:none; align-items:center; justify-content:center; z-index:200; }
    #toggleModal:checked ~ .modal-wrap { display:flex; }
    .modal { background:var(--surface); border-radius:14px; width:440px; max-width:92vw; overflow:hidden; box-shadow:0 24px 60px rgba(27,23,38,.25); }
    .modal-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
    .modal-head .t { font-size:15px; font-weight:700; }
    .modal-x { cursor:pointer; color:var(--text-3); font-size:18px; line-height:1; }
    .modal-x:hover { color:var(--text-1); }
    .modal-body { padding:20px; display:flex; flex-direction:column; gap:14px; }
    .fg { display:flex; flex-direction:column; gap:5px; }
    .fg label { font-size:12px; font-weight:600; color:var(--text-2); }
    .fg input, .fg select { border:1px solid var(--border); border-radius:8px; padding:8px 11px; font-family:inherit; font-size:13px; outline:none; color:var(--text-1); }
    .fg input:focus, .fg select:focus { border-color:var(--accent); }
    .fg2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
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
      <label for="toggleModal" class="btn btn-primary">+ Добавить пользователя</label>
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
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php $isActive = (int) ($u['is_active'] ?? 0) === 1; ?>
            <tr>
              <td>
                <div class="u-cell">
                  <span class="u-avatar"><?= htmlspecialchars($initials((string) ($u['display_name'] ?: $u['username']))) ?></span>
                  <div>
                    <div class="u-name"><?= htmlspecialchars($u['display_name'] ?: $u['username']) ?></div>
                    <div class="u-login"><?= htmlspecialchars($u['username']) ?></div>
                  </div>
                </div>
              </td>
              <td><?= htmlspecialchars($u['email'] ?? '') ?: '<span style="color:var(--text-3)">—</span>' ?></td>
              <td>
                <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users/role" class="row-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <select name="role_id" class="role-select">
                    <option value="">— не назначена —</option>
                    <?php foreach ($roles as $r): ?>
                      <option value="<?= (int) $r['id'] ?>" <?= ((int) ($u['role_id'] ?? 0) === (int) $r['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button type="submit" class="btn btn-ghost btn-sm">Сохранить</button>
                </form>
              </td>
              <td>
                <span class="status">
                  <span class="status-dot <?= $isActive ? 'dot-on' : 'dot-off' ?>"></span>
                  <?= $isActive ? 'Активен' : 'Заблокирован' ?>
                </span>
              </td>
              <td style="text-align:right">
                <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users/active" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <input type="hidden" name="is_active" value="<?= $isActive ? 0 : 1 ?>">
                  <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-ghost' : 'btn-primary' ?>">
                    <?= $isActive ? 'Заблокировать' : 'Разблокировать' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($users)): ?>
            <tr><td colspan="5" style="text-align:center; color:var(--text-3); padding:24px">Пользователей нет</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Роли -->
    <div class="panel">
      <div class="panel-head"><span class="panel-title">Роли</span></div>
      <table class="admin-table">
        <thead>
          <tr><th>Роль</th><th>Код</th><th>Описание</th></tr>
        </thead>
        <tbody>
          <?php foreach ($roles as $r): ?>
            <tr>
              <td><span class="role-badge <?= $roleClass($r['code']) ?>"><?= htmlspecialchars($r['name']) ?></span></td>
              <td style="font-family:monospace; color:var(--text-2)"><?= htmlspecialchars($r['code']) ?></td>
              <td style="color:var(--text-2)"><?= htmlspecialchars($r['description'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>

<!-- Модалка: добавить пользователя -->
<input type="checkbox" id="toggleModal">
<div class="modal-wrap">
  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/users" class="modal">
    <div class="modal-head">
      <span class="t">Новый пользователь</span>
      <label for="toggleModal" class="modal-x">✕</label>
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
        <label>Пароль <span style="color:var(--text-3); font-weight:400">(можно оставить пустым для входа через AD)</span></label>
        <input type="password" name="password" autocomplete="new-password">
      </div>
      <div class="fg">
        <label>Роль</label>
        <select name="role_id">
          <option value="">— не назначена —</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="modal-foot">
      <label for="toggleModal" class="btn btn-ghost">Отмена</label>
      <button type="submit" class="btn btn-primary">Создать</button>
    </div>
  </form>
</div>

</body>
</html>
