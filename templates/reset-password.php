<?php
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Новый пароль</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/auth.css">
</head>
<body>
  <div class="login-wrap">
    <div class="card">
      <div class="card-title">Новый пароль</div>
      <?php if (!empty($error)): ?><div class="error-msg visible"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if (!$tokenValid): ?>
        <div class="error-msg visible">Ссылка недействительна или срок её действия истёк.</div>
        <a class="btn-login auth-button-link" href="<?= htmlspecialchars($basePath) ?>/forgot-password">Получить новую ссылку</a>
      <?php else: ?>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/reset-password">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="field">
            <label class="field-label" for="password">Новый пароль</label>
            <input class="field-input" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required autofocus>
          </div>
          <div class="field">
            <label class="field-label" for="password_confirmation">Повторите пароль</label>
            <input class="field-input" type="password" id="password_confirmation" name="password_confirmation" minlength="8" autocomplete="new-password" required>
          </div>
          <button class="btn-login" type="submit">Сохранить пароль</button>
        </form>
      <?php endif; ?>
      <a class="auth-link auth-link--back" href="<?= htmlspecialchars($basePath) ?>/login">Вернуться ко входу</a>
    </div>
  </div>
  <div class="login-footer">&copy; <?= date('Y') ?></div>
</body>
</html>

