<?php
/** @var string $appName */
/** @var string|null $error */
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход в систему</title>
  <link rel="stylesheet" href="/assets/css/auth.css">
</head>

<body>

  <div class="login-wrap">

    <div class="card">
      <div class="card-title">Вход в систему</div>

      <?php if ($error): ?>
        <div class="error-msg visible"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="/login" id="loginForm">
        <div class="field">
          <label class="field-label" for="username">Логин</label>
          <input class="field-input<?= $error ? ' error' : '' ?>" type="text" id="username" name="username"
            placeholder="Введите логин" autocomplete="username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
        </div>

        <div class="field">
          <label class="field-label" for="password">Пароль</label>
          <input class="field-input<?= $error ? ' error' : '' ?>" type="password" id="password" name="password"
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
    &copy; <?= date('Y') ?><!-- <?= htmlspecialchars($appName) ?> -->
  </div>

  <script src="/assets/js/auth.js"></script>
</body>

</html>