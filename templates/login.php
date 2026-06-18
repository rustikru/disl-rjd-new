<?php
/** @var string $appName */
/** @var string $basePath */
/** @var string|null $error */
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Вход в систему</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/auth.css">
</head>

<body>

  <div class="login-wrap">

    <div class="brand">
      <!-- <img class="brand-logo" src="<?= htmlspecialchars($basePath) ?>/assets/img/meta-logo.png" alt=""> -->
      <!-- <div class="brand-name"><?= htmlspecialchars($appName ?? 'Дислокация') ?></div> -->
    </div>

    <div class="card">
      <div class="card-title">Вход в систему</div>

      <?php if ($error): ?>
        <div class="error-msg visible"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="<?= htmlspecialchars($basePath) ?>/login" id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
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

  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/auth.js"></script>
</body>

</html>