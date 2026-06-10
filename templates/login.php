<?php
/** @var string $appName */
/** @var string|null $error */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход — <?= htmlspecialchars($appName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>

<div class="login-wrap">

  <div class="brand">
    <div class="brand-icon">
      <svg width="28" height="28" viewBox="0 0 30 30" fill="none">
        <rect x="1" y="10" width="28" height="12" rx="2" fill="currentColor" opacity=".9"/>
        <circle cx="7.5"  cy="24" r="3" fill="currentColor"/>
        <circle cx="22.5" cy="24" r="3" fill="currentColor"/>
        <rect x="6"  y="7" width="7" height="5" rx="1" fill="currentColor" opacity=".55"/>
        <rect x="17" y="7" width="7" height="5" rx="1" fill="currentColor" opacity=".55"/>
      </svg>
    </div>
    <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
    <div class="brand-sub">Система дислокации вагонов</div>
  </div>

  <div class="card">
    <div class="card-title">Вход в систему</div>

    <?php if ($error): ?>
    <div class="error-msg visible"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login" id="loginForm">
      <div class="field">
        <label class="field-label" for="username">Логин</label>
        <input class="field-input<?= $error ? ' error' : '' ?>"
          type="text" id="username" name="username"
          placeholder="Введите логин" autocomplete="username"
          value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
      </div>

      <div class="field">
        <label class="field-label" for="password">Пароль</label>
        <input class="field-input<?= $error ? ' error' : '' ?>"
          type="password" id="password" name="password"
          placeholder="Введите пароль" autocomplete="current-password" required>
      </div>

      <button class="btn-login" type="submit" id="submitBtn">
        <span class="btn-text">Войти</span>
        <span class="btn-spinner" aria-hidden="true"></span>
      </button>
    </form>
  </div>

</div>

<div class="login-footer">
  &copy; <?= date('Y') ?> <?= htmlspecialchars($appName) ?>
</div>

<script src="/assets/js/auth.js"></script>
</body>
</html>
