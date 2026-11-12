<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($appName) ?> — Журналы</title><link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css"></head><body>
<?php $headerSub = '<div class="brand-sub">Администрирование</div>'; $headerLeft = ''; $headerRight = ''; include __DIR__ . '/../partials/header.php'; ?>
<div class="app-body"><?php $activeAdminPage = 'logs'; include __DIR__ . '/../partials/admin-sidebar.php'; ?><main class="main-content">
<div class="ui-page-head"><div class="ui-page-title">Журналы<small>Просмотр и очистка диагностических файлов приложения.</small></div></div>
<?php if ($message !== null): ?><div class="ui-notice ui-notice--<?= str_contains($message, 'очищен') ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<section class="ui-panel logs-panel">
  <div class="logs-tabs">
    <a class="<?= $tab === 'errors' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/admin/logs?tab=errors">Ошибки</a>
    <a class="<?= $tab === 'sql' ? 'is-active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/admin/logs?tab=sql">SQL-запросы</a>
  </div>
  <div class="logs-head"><div><strong><?= $tab === 'errors' ? 'Журнал ошибок' : 'Журнал SQL-запросов' ?></strong><div class="ui-help"><?= htmlspecialchars($path) ?></div></div><div class="ui-form-actions"><button class="btn btn-secondary js-log-refresh" type="button">Обновить</button><form method="post" onsubmit="return confirm('Очистить выбранный журнал?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><button class="btn btn-secondary" type="submit">Очистить журнал</button></form></div></div>
  <textarea class="logs-text" readonly aria-label="Содержимое журнала"><?= htmlspecialchars($logText) ?></textarea>
</section>
</main></div>
<script>
  document.querySelector('.js-log-refresh')?.addEventListener('click', async function () {
    var button = this;
    var textarea = document.querySelector('.logs-text');
    button.disabled = true;
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', <?= json_encode($tab, JSON_UNESCAPED_UNICODE) ?>);
      url.searchParams.set('_', Date.now().toString());
      var response = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
      if (!response.ok) throw new Error('refresh failed');
      var html = await response.text();
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = doc.querySelector('.logs-text');
      if (fresh && textarea) textarea.value = fresh.value;
    } catch (error) {
      window.alert('Не удалось обновить журнал.');
    } finally {
      button.disabled = false;
    }
  });
</script>
</body></html>
