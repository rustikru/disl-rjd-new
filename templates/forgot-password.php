<?php
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Восстановление пароля</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/auth.css">
</head>
<body>
  <div class="login-wrap">
    <div class="card">
      <div class="card-title">Восстановление пароля</div>
      <?php if (!empty($message)): ?>
        <p class="confirmation-text"><?= htmlspecialchars($message) ?></p>
      <?php else: ?>
        <p class="card-note">Введите логин. Ссылка для нового пароля будет отправлена на почту.</p>
        <?php if (!empty($error)): ?><div class="error-msg visible"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/forgot-password">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <div class="field">
            <label class="field-label" for="username">Логин</label>
            <input class="field-input" type="text" id="username" name="username" placeholder="Введите логин" autocomplete="username" required autofocus>
          </div>
          <button class="btn-login" type="submit">Восстановить пароль</button>
        </form>
      <?php endif; ?>
      <a class="auth-link auth-link--back" href="<?= htmlspecialchars($basePath) ?>/login">Вернуться ко входу</a>
    </div>
  </div>
  <div class="login-footer">&copy; <?= date('Y') ?></div>
</body>
</html>

