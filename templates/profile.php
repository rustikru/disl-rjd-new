<?php
$e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$name = trim((string) ($profile['display_name'] ?? '')) ?: $profile['username'];
$displayName = trim((string) ($profile['display_name'] ?? ''));
$parts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
$initials = count($parts) >= 2
  ? mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[1], 0, 1, 'UTF-8')
  : mb_substr((string) $profile['username'], 0, 2, 'UTF-8');
$initials = mb_strtoupper($initials, 'UTF-8');
$isActive = (int) $profile['is_active'] === 1;
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $e($appName) ?> — Профиль пользователя</title>
  <link rel="icon" href="<?= $e($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= $e($basePath) ?>/assets/css/app.css">
  <style>
    .profile-page { max-width:1180px; margin:0 auto; padding:22px 24px 40px; }
    .profile-heading { margin-bottom:14px; }
    .profile-heading h1 { margin:0; font-size:18px; line-height:1.25; }
    .profile-heading p { margin-top:3px; color:var(--text-3); font-size:11.5px; }
    .profile-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .profile-summary { display:flex; align-items:center; gap:12px; min-height:76px; padding:14px 18px; border-bottom:1px solid var(--border); }
    .profile-avatar { width:44px; height:44px; border-radius:10px; background:var(--accent-lt); color:var(--accent); display:grid; place-items:center; font-size:15px; font-weight:700; flex-shrink:0; }
    .profile-name { margin-bottom:2px; font-size:15px; font-weight:700; overflow-wrap:anywhere; }
    .profile-login { color:var(--text-3); font-size:11px; }
    .profile-status { margin-left:auto; display:inline-flex; align-items:center; gap:6px; color:var(--text-2); font-size:11.5px; font-weight:600; }
    .profile-status::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--brand-green); }
    .profile-status--disabled::before { background:var(--brand-neg); }
    .profile-sections { display:grid; grid-template-columns:1fr 1fr; }
    .profile-section { min-width:0; padding:16px 18px; border-bottom:1px solid var(--border); }
    .profile-section:nth-child(odd) { border-right:1px solid var(--border); }
    .profile-section:nth-last-child(-n+2) { border-bottom:0; }
    .profile-section h2 { margin:0; padding-bottom:10px; color:var(--text-3); font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; }
    .profile-data { margin:0; }
    .profile-data-row { display:grid; grid-template-columns:130px minmax(0, 1fr); gap:12px; padding:7px 0; }
    .profile-data-row + .profile-data-row, .profile-item + .profile-item { border-top:1px solid var(--border-lt); }
    .profile-data dt { color:var(--text-3); }
    .profile-data dd { margin:0; color:var(--text-1); overflow-wrap:anywhere; }
    .profile-item { padding:7px 0; color:var(--text-1); }
    .profile-item small { display:block; margin-top:2px; color:var(--text-3); font-size:10.5px; }
    .profile-empty { padding:7px 0; color:var(--text-3); }
    @media(max-width:700px) {
      .profile-page { padding:16px 12px 28px; }
      .profile-sections { grid-template-columns:1fr; }
      .profile-section, .profile-section:nth-child(odd), .profile-section:nth-last-child(-n+2) { border-right:0; border-bottom:1px solid var(--border); }
      .profile-section:last-child { border-bottom:0; }
      .profile-data-row { grid-template-columns:1fr; gap:2px; }
      .profile-summary { padding:12px 14px; }
    }
  </style>
</head>

<body>
  <?php
  $headerSub = '<div class="brand-sub">Профиль пользователя</div>';
  $headerLeft = '<a href="' . $e($basePath) . '/" class="btn-nav-back" title="На главную" aria-label="На главную"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 5-7 7 7 7M5 12h14"/></svg></a>';
  include __DIR__ . '/partials/header.php';
  ?>
  <main class="profile-page">
    <div class="profile-heading">
      <h1>Мой профиль</h1>
    </div>

    <section class="profile-panel">
      <div class="profile-summary">
        <div class="profile-avatar" aria-hidden="true"><?= $e($initials) ?></div>
        <div>
          <div class="profile-name"><?= $e($name) ?></div>
          <div class="profile-login"><?= $e($profile['username']) ?></div>
        </div>
        <span class="profile-status<?= $isActive ? '' : ' profile-status--disabled' ?>"><?= $isActive ? 'Активен' : 'Отключён' ?></span>
      </div>

      <div class="profile-sections">
        <section class="profile-section">
          <h2>Учётная запись</h2>
          <dl class="profile-data">
            <?php foreach (['Имя' => $profile['display_name'], 'Логин' => $profile['username'], 'Электронная почта' => $profile['email']] as $label => $value): ?>
              <div class="profile-data-row"><dt><?= $e($label) ?></dt><dd><?= $e($value !== null && $value !== '' ? $value : 'Не указано') ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </section>

        <section class="profile-section">
          <h2>Организации</h2>
          <?php foreach ($organizations as $organization): ?>
            <div class="profile-item">
              <?= $e($organization['name']) ?>
              <small><?= $e($organization['code']) ?><?= (int) $organization['id'] === $organizationId ? ' · Текущая организация' : '' ?><?= !empty($organization['is_primary']) ? ' · Основная' : '' ?></small>
            </div>
          <?php endforeach; ?>
          <?php if (!$organizations): ?><div class="profile-empty">Организации не назначены</div><?php endif; ?>
        </section>

      </div>
    </section>
  </main>
</body>

</html>
