<?php
$e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$name = trim((string) ($profile['display_name'] ?? '')) ?: $profile['username'];
$displayName = trim((string) ($profile['display_name'] ?? ''));
$parts = preg_split('/\s+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY);
$initials = count($parts) >= 2
  ? mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[1], 0, 1, 'UTF-8')
  : mb_substr((string) $profile['username'], 0, 2, 'UTF-8');
$initials = mb_strtoupper($initials, 'UTF-8');
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
    .profile-page {
      max-width: 1100px;
      margin: 0 auto;
      padding: 28px 24px;
    }

    .profile-page h1 {
      font-size: 20px;
      margin: 0 0 20px;
    }

    .profile-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 22px;
    }

    .profile-intro {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }

    .profile-avatar {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--accent-lt);
      color: var(--accent);
      display: grid;
      place-items: center;
      font-size: 20px;
      font-weight: 600;
      flex-shrink: 0;
    }

    .profile-name {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 4px;
      overflow-wrap: anywhere;
    }

    .profile-login {
      color: var(--text-2);
    }

    .profile-status {
      margin-left: auto;
      border-radius: 20px;
      padding: 6px 12px;
      background: var(--accent-lt);
      color: var(--accent);
    }

    .profile-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }

    .profile-card h2 {
      margin: 0 0 18px;
      font-size: 15px;
    }

    .profile-data {
      margin: 0;
      display: grid;
      grid-template-columns: minmax(110px, 1fr) 2fr;
      gap: 14px;
    }

    .profile-data dt {
      color: var(--text-2);
    }

    .profile-data dd {
      margin: 0;
      overflow-wrap: anywhere;
    }

    .profile-item+.profile-item {
      border-top: 1px solid var(--border);
      padding-top: 12px;
      margin-top: 12px;
    }

    .profile-item small {
      display: block;
      color: var(--text-2);
      margin-top: 5px;
    }

    .profile-access {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .profile-access span {
      background: var(--accent-lt);
      color: var(--accent);
      padding: 6px 10px;
      border-radius: 8px;
    }

    @media(max-width:700px) {
      .profile-grid {
        grid-template-columns: 1fr;
      }

      .profile-page {
        padding: 20px 14px;
      }

      .profile-data {
        grid-template-columns: 1fr;
        gap: 6px;
      }

      .profile-data dd {
        margin-bottom: 10px;
      }
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
    <h1>Профиль пользователя</h1>
    <section class="profile-card profile-intro">
      <div class="profile-avatar" aria-hidden="true"><?= $e($initials) ?></div>
      <div>
        <div class="profile-name"><?= $e($name) ?></div>
        <div class="profile-login"><?= $e($profile['username']) ?></div>
      </div>
      <span class="profile-status"><?= (int) $profile['is_active'] === 1 ? 'Активен' : 'Отключён' ?></span>
    </section>
    <div class="profile-grid">
      <section class="profile-card">
        <h2>Учётная запись</h2>
        <dl class="profile-data">
              <?php foreach (['Имя' => $profile['display_name'], 'Логин' => $profile['username'], 'Электронная почта' => $profile['email']] as $label => $value): ?>
            <dt><?= $e($label) ?></dt>
            <dd><?= $e($value !== null && $value !== '' ? $value : 'Не указано') ?></dd>
              <?php endforeach; ?>
        </dl>
      </section>
      <section class="profile-card">
        <h2>Организации</h2>
          <?php foreach ($organizations as $organization): ?>
          <div class="profile-item">
          
            <?= $e($organization['name']) ?><small><?= $e($organization['code']) ?><?= (int) $organization['id'] === $organizationId ? ' · Текущая организация' : '' ?><?= !empty($organization['is_primary']) ? ' · Основная' : '' ?></small>
              </div><?php endforeach; ?>
        <?php if (!$organizations): ?>
            <div>Организации не назначены</div><?php endif; ?>
      </section>
      <section class="profile-card">
          <h2>Роли</h2>
        <?php foreach ($roles as $role): ?>
          <div class="profile-item">
                <?= $e($role['name']) ?><small><?= $e($role['code']) ?><?= !empty($role['description']) ? ' · ' . $e($role['description']) : '' ?></small>
          </div><?php endforeach; ?>
        <?php if (!$roles): ?>
            <div>Роли не назначены</div><?php endif; ?>
      </section>
      <section class="profile-card">
          

              <h2>Доступные разделы</h2>
        <div class="profile-access">
          <?php foreach ($access as $label): ?><span><?= $e($label) ?></span><?php endforeach; ?></div>
        <?php if (!$access): ?>
            <div>Разделы не назначены</div><?php endif; ?>
      </section>
    </div>
  </main>
</body>

</html>