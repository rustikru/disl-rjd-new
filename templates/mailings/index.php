<?php
$basePath = $basePath ?? '';
$catalog = $catalog ?? [];
$statusLabels = [
    'PENDING' => 'В очереди',
    'RUNNING' => 'Выполняется',
    'SENT' => 'Отправлено',
    'SKIPPED' => 'Пропущено',
    'ERROR' => 'Ошибка',
];
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Мои рассылки — <?= htmlspecialchars($appName) ?></title>
  <link rel="icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/mailings.css">
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Отчёты и рассылки</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<main class="mailing-page">
  <div class="mailing-wrap">
    <div class="mailing-head">
      <div>
        <h1>Мои рассылки</h1>
        <div class="mailing-subtitle">Автоматическая отправка отчётов по выбранному расписанию</div>
      </div>
      <a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/mailings/form">+ Новая рассылка</a>
    </div>

    <?php if ($flashOk): ?><div class="mailing-alert is-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="mailing-alert is-error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <div class="mailing-tabs">
      <button type="button" class="mailing-tab is-active" data-mailing-tab="list">Рассылки</button>
      <button type="button" class="mailing-tab" data-mailing-tab="history">История запусков</button>
    </div>

    <section class="mailing-panel is-active" data-mailing-panel="list">
      <div class="mailing-card">
        <div class="mailing-card-head">
          <span class="mailing-card-title">Рассылки (<?= count($mailings) ?>)</span>
        </div>
        <?php if (!$mailings): ?>
          <div class="mailing-empty">Рассылок пока нет. Создайте первую рассылку в этом разделе.</div>
        <?php else: ?>
          <div class="mailing-table-wrap">
            <table class="mailing-table">
              <thead><tr><th>Рассылка</th><th>Организация</th><th>Расписание</th><th>Получатели</th><th>Статус</th><th>Следующий запуск</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($mailings as $mailing): ?>
                <?php
                  $report = $catalog[$mailing['report_code']] ?? null;
                  $organizationName = $mailing['organization_short_name'] ?: ($mailing['organization_name'] ?: 'Общий отчёт');
                  $lastStatus = strtoupper((string) ($mailing['last_status'] ?? ''));
                ?>
                <tr>
                  <td><div class="mailing-name"><?= htmlspecialchars($mailing['name']) ?></div><div class="mailing-note"><?= htmlspecialchars($report['name'] ?? $mailing['report_code']) ?> · <?= $mailing['report_view'] === 'SUMMARY' ? 'сводный' : 'подробный' ?> · XLSX</div></td>
                  <td><?= htmlspecialchars($organizationName) ?></td>
                  <td><?= htmlspecialchars($mailing['schedule_label']) ?></td>
                  <td><?= (int) $mailing['recipient_count'] ?></td>
                  <td><span class="mailing-status"><span class="mailing-dot <?= (int) $mailing['is_active'] === 1 ? 'is-active' : '' ?>"></span><?= (int) $mailing['is_active'] === 1 ? 'Активна' : 'Отключена' ?></span><?php if ($lastStatus): ?><div class="mailing-note">Последний: <?= htmlspecialchars($statusLabels[$lastStatus] ?? $lastStatus) ?></div><?php endif; ?></td>
                  <td><?= $mailing['next_run_at'] ? htmlspecialchars((new DateTime($mailing['next_run_at']))->format('d.m.Y H:i')) : '—' ?></td>
                  <td>
                    <div class="mailing-actions">
                      <form class="mailing-inline" method="post" action="<?= htmlspecialchars($basePath) ?>/mailings/run"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $mailing['id'] ?>"><button class="mailing-icon-button" title="Запустить сейчас" aria-label="Запустить сейчас"><svg class="is-filled" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button></form>
                      <a class="mailing-icon-button" href="<?= htmlspecialchars($basePath) ?>/mailings/form?id=<?= (int) $mailing['id'] ?>" title="Изменить" aria-label="Изменить"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h3.5L18 9.5 14.5 6 4 16.5z"/><path d="M16 4.5 19.5 8"/></svg></a>
                      <form class="mailing-inline" method="post" action="<?= htmlspecialchars($basePath) ?>/mailings/active"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $mailing['id'] ?>"><input type="hidden" name="is_active" value="<?= (int) $mailing['is_active'] === 1 ? 0 : 1 ?>"><button class="mailing-icon-button" title="<?= (int) $mailing['is_active'] === 1 ? 'Отключить' : 'Включить' ?>" aria-label="<?= (int) $mailing['is_active'] === 1 ? 'Отключить' : 'Включить' ?>"><?php if ((int) $mailing['is_active'] === 1): ?><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6v12M16 6v12"/></svg><?php else: ?><svg class="is-filled" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><?php endif; ?></button></form>
                      <form class="mailing-inline" method="post" action="<?= htmlspecialchars($basePath) ?>/mailings/delete" onsubmit="return confirm('Удалить рассылку и её историю?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $mailing['id'] ?>"><button class="mailing-icon-button is-delete" title="Удалить" aria-label="Удалить"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M9 7V4h6v3M8 10v8M12 10v8M16 10v8M7 7l1 14h8l1-14"/></svg></button></form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="mailing-panel" data-mailing-panel="history">
      <div class="mailing-card">
        <div class="mailing-card-head"><span class="mailing-card-title">Последние запуски</span></div>
        <?php if (!$runs): ?>
          <div class="mailing-empty">История запусков пока пуста.</div>
        <?php else: ?>
          <div class="mailing-table-wrap"><table class="mailing-table"><thead><tr><th>Дата</th><th>Рассылка</th><th>Статус</th><th>Дата отчёта</th><th>Сообщение</th></tr></thead><tbody>
          <?php foreach ($runs as $run): $status = strtoupper((string) $run['status']); ?>
            <tr><td><?= htmlspecialchars((new DateTime($run['started_at']))->format('d.m.Y H:i')) ?></td><td><?= htmlspecialchars($run['mailing_name']) ?></td><td><span class="mailing-status"><span class="mailing-dot is-<?= strtolower($status) ?>"></span><?= htmlspecialchars($statusLabels[$status] ?? $status) ?></span></td><td><?= $run['report_dt'] ? htmlspecialchars((new DateTime($run['report_dt']))->format('d.m.Y H:i')) : '—' ?></td><td><?= htmlspecialchars($run['error_message'] ?? '') ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
<script>
document.querySelectorAll('[data-mailing-tab]').forEach(function (button) {
  button.addEventListener('click', function () {
    document.querySelectorAll('[data-mailing-tab]').forEach(function (item) { item.classList.toggle('is-active', item === button) })
    document.querySelectorAll('[data-mailing-panel]').forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.mailingPanel === button.dataset.mailingTab) })
  })
})
</script>
</body>
</html>
