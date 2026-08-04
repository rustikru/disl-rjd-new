<?php
$basePath = $basePath ?? '';
$statusLabels = [
    'PENDING' => 'Ожидает',
    'RUNNING' => 'Выполняется',
    'SENT' => 'Отправлена',
    'SKIPPED' => 'Пропущена',
    'ERROR' => 'Ошибка',
];
$reportLabels = [];
foreach ($reports as $code => $report) {
    $reportLabels[$code] = (string) ($report['name'] ?? $code);
}
$dateText = static function ($value): string {
    if (!$value) {
        return '—';
    }
    try {
        return (new DateTime((string) $value))->format('d.m.Y H:i');
    } catch (Throwable $error) {
        return (string) $value;
    }
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Рассылки</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .admin-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px;flex-wrap:wrap}
    .admin-title{font-size:18px;font-weight:700;color:var(--text-1)}
    .admin-title small{display:block;font-size:12px;font-weight:500;color:var(--text-3);margin-top:2px}
    .flash{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:16px}
    .flash-ok{background:#e8f6ef;color:var(--brand-green);border:1px solid #bfe6d2}
    .flash-err{background:#fbecec;color:var(--brand-neg);border:1px solid #f0c9c9}
    .panel{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:18px}
    .panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border)}
    .panel-title{font-size:14px;font-weight:600}
    .table-wrap{overflow:auto}
    .data-table{width:100%;border-collapse:collapse;min-width:1040px}
    .data-table th{padding:9px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border);white-space:nowrap}
    .data-table td{padding:10px 14px;vertical-align:top;border-bottom:1px solid var(--border);font-size:13px;color:var(--text-1)}
    .data-table tbody tr:last-child td{border-bottom:none}
    .data-table tbody tr:hover td{background:var(--hover-green,#f5f4f9)}
    .data-table tbody tr.open-details{cursor:pointer}
    .data-table tbody tr.open-details:focus{outline:2px solid var(--accent);outline-offset:-2px}
    .muted{display:block;color:var(--text-3);font-size:11.5px;margin-top:2px}
    .status{display:inline-flex;align-items:center;gap:6px;white-space:nowrap}
    .status:before{content:"";width:7px;height:7px;border-radius:50%;background:#aaa}
    .status-SENT:before{background:#28a66f}.status-ERROR:before{background:#d94040}.status-PENDING:before{background:#c18a22}.status-RUNNING:before{background:#417ec2}.status-SKIPPED:before{background:#999}
    .empty{padding:36px;text-align:center;color:var(--text-3);font-size:13px}
    .error-text{max-width:320px;color:var(--brand-neg);font-size:12px;white-space:normal}
    .run-form{margin:0}
    .details-wrap{position:fixed;inset:0;z-index:300;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(27,23,38,.48)}
    .details-wrap.is-open{display:flex}
    .details-box{width:min(920px,96vw);max-height:90vh;overflow:auto;background:#f7f6fa;border-radius:16px;box-shadow:0 24px 70px rgba(27,23,38,.28)}
    .details-head{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 22px;border-bottom:1px solid var(--border);background:rgba(255,255,255,.96);backdrop-filter:blur(8px)}
    .details-title{font-size:17px;font-weight:700}.details-title small{display:block;margin-top:3px;color:var(--text-3);font-size:11px;font-weight:500}
    .details-close{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;border:0;border-radius:8px;background:transparent;color:var(--text-2);font-size:24px;cursor:pointer}
    .details-close:hover{background:var(--hover-green)}
    .details-body{padding:20px 22px 24px}
    .details-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}
    .details-summary-item{padding:13px 14px;border:1px solid var(--border);border-radius:11px;background:var(--surface)}
    .details-summary-value{margin-top:5px;color:var(--text-1);font-size:13px;font-weight:600;line-height:1.35}
    .details-section{margin-bottom:14px;padding:16px;border:1px solid var(--border);border-radius:12px;background:var(--surface)}.details-section:last-child{margin-bottom:0}
    .details-section h3{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 13px;font-size:13.5px;color:var(--text-1)}
    .details-section h3 span{color:var(--text-3);font-size:11px;font-weight:500}
    .details-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 24px}
    .details-item{padding:5px 0}
    .details-label{display:block;margin-bottom:3px;color:var(--text-3);font-size:10.5px;text-transform:uppercase;letter-spacing:.04em}
    .details-value{font-size:13px;color:var(--text-1);line-height:1.45;white-space:pre-wrap;overflow-wrap:anywhere}
    .details-wide{grid-column:1/-1}
    .attachment-card{position:relative;padding-left:18px}.attachment-card:before{content:"";position:absolute;left:0;top:16px;bottom:16px;width:3px;border-radius:3px;background:var(--accent)}
    .attachment-number{display:inline-flex;align-items:center;justify-content:center;min-width:25px;height:25px;padding:0 8px;border-radius:7px;background:#eee8fb;color:var(--accent);font-size:11px;font-weight:700}
    .filter-list{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)}
    .filter-chip{max-width:100%;padding:6px 9px;border-radius:8px;background:#f5f2fb;color:var(--text-2);font-size:11.5px;overflow-wrap:anywhere}.filter-chip strong{color:var(--text-1)}
    .details-list{display:flex;flex-wrap:wrap;gap:7px;margin:0;padding:0;list-style:none}.details-list li{padding:7px 10px;border-radius:8px;background:#f5f2fb;font-size:12px}
    .pagination{display:flex;align-items:center;justify-content:flex-end;gap:5px;padding:12px 18px;border-top:1px solid var(--border)}
    .page-link{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 9px;border:1px solid var(--border);border-radius:8px;color:var(--text-2);text-decoration:none;font-size:12px;background:var(--surface)}
    .page-link:hover{border-color:var(--accent);color:var(--accent)}
    .page-link.is-active{border-color:var(--accent);background:var(--accent);color:#fff;pointer-events:none}
    @media(max-width:900px){.main-content{padding:18px}.admin-head{align-items:flex-start}.details-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:640px){.details-grid{grid-template-columns:1fr}.details-wide{grid-column:auto}}
  </style>
</head>
<body>
<?php
  $headerSub = '<div class="brand-sub">Администрирование</div>';
  $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
  $headerRight = '';
  include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'mailings'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content">
    <div class="admin-head">
      <div class="admin-title">Рассылки<small>Настройки пользователей и история запусков</small></div>
      <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/mailings/run" class="run-form" onsubmit="return confirm('Обработать все ожидающие рассылки?')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <button type="submit" class="btn btn-primary">Обработать очередь</button>
      </form>
    </div>

    <?php if (!empty($flashOk)): ?><div class="flash flash-ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="flash flash-err"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <section class="panel">
      <div class="panel-head"><span class="panel-title">Настроенные рассылки (<?= count($mailings) ?>)</span></div>
      <?php if (!$mailings): ?>
        <div class="empty">Настроенных рассылок пока нет</div>
      <?php else: ?>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Рассылка</th><th>Пользователь</th><th>Отчёт</th><th>Расписание</th><th>Получатели</th><th>Статус</th><th>Следующий запуск</th><th>Последний результат</th></tr></thead>
          <tbody>
          <?php foreach ($mailings as $mailing): ?>
            <?php $lastStatus = (string) ($mailing['last_status'] ?? ''); ?>
            <tr class="open-details" tabindex="0" role="button" aria-label="Открыть настройки рассылки <?= htmlspecialchars((string) $mailing['name']) ?>" data-mailing-id="<?= (int) $mailing['id'] ?>">
              <td><strong><?= htmlspecialchars((string) $mailing['name']) ?></strong><span class="muted">ID <?= (int) $mailing['id'] ?></span></td>
              <td><?= htmlspecialchars((string) ($mailing['display_name'] ?: $mailing['username'])) ?><span class="muted"><?= htmlspecialchars((string) $mailing['username']) ?></span></td>
              <td>Вложений: <?= max(1, (int) ($mailing['attachment_count'] ?? 1)) ?><span class="muted">XLSX</span></td>
              <td><?= htmlspecialchars((string) $mailing['schedule_label']) ?></td>
              <td><?= (int) $mailing['recipient_count'] ?></td>
              <td><span class="status <?= (int) $mailing['is_active'] === 1 ? 'status-SENT' : 'status-SKIPPED' ?>"><?= (int) $mailing['is_active'] === 1 ? 'Активна' : 'Отключена' ?></span></td>
              <td><?= htmlspecialchars($dateText($mailing['next_run_at'] ?? null)) ?></td>
              <td><?= $lastStatus !== '' ? '<span class="status status-' . htmlspecialchars($lastStatus) . '">' . htmlspecialchars($statusLabels[$lastStatus] ?? $lastStatus) . '</span>' : '—' ?><span class="muted"><?= htmlspecialchars($dateText($mailing['last_attempt_at'] ?? null)) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>

    <section class="panel" id="run-history">
      <div class="panel-head"><span class="panel-title">История запусков (<?= (int) $runsCount ?>)</span></div>
      <?php if (!$runs): ?>
        <div class="empty">Запусков пока нет</div>
      <?php else: ?>
        <div class="table-wrap"><table class="data-table">
          <thead><tr><th>Запуск</th><th>Рассылка</th><th>Пользователь</th><th>Статус</th><th>Дата отчёта</th><th>Результат / ошибка</th></tr></thead>
          <tbody>
          <?php foreach ($runs as $run): ?>
            <?php $runStatus = (string) $run['status']; ?>
            <tr>
              <td><?= htmlspecialchars($dateText($run['started_at'] ?? null)) ?><span class="muted">ID <?= (int) $run['id'] ?></span></td>
              <td><strong><?= htmlspecialchars((string) $run['mailing_name']) ?></strong><span class="muted">Вложений: <?= max(1, (int) ($run['attachment_count'] ?? 1)) ?></span></td>
              <td><?= htmlspecialchars((string) ($run['display_name'] ?: $run['username'])) ?><span class="muted"><?= htmlspecialchars((string) $run['username']) ?></span></td>
              <td><span class="status status-<?= htmlspecialchars($runStatus) ?>"><?= htmlspecialchars($statusLabels[$runStatus] ?? $runStatus) ?></span><span class="muted"><?= htmlspecialchars($dateText($run['finished_at'] ?? null)) ?></span></td>
              <td><?= htmlspecialchars($dateText($run['report_dt'] ?? null)) ?></td>
              <td><?php if (!empty($run['error_message'])): ?><div class="error-text"><?= htmlspecialchars((string) $run['error_message']) ?></div><?php else: ?>Файлы сформированы<?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if ($runsPages > 1): ?>
          <nav class="pagination" aria-label="Страницы истории запусков">
            <?php for ($pageNumber = 1; $pageNumber <= $runsPages; $pageNumber++): ?>
              <a class="page-link<?= $pageNumber === $runsPage ? ' is-active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/admin/mailings?history_page=<?= $pageNumber ?>#run-history"<?= $pageNumber === $runsPage ? ' aria-current="page"' : '' ?>><?= $pageNumber ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
</div>

<?php foreach ($mailings as $mailing): ?>
  <?php
    $attachments = (array) ($mailing['attachments'] ?? []);
  ?>
  <div class="details-wrap" id="mailing-details-<?= (int) $mailing['id'] ?>" role="dialog" aria-modal="true" aria-labelledby="mailing-title-<?= (int) $mailing['id'] ?>">
    <div class="details-box">
      <div class="details-head">
        <div class="details-title" id="mailing-title-<?= (int) $mailing['id'] ?>"><?= htmlspecialchars((string) $mailing['name']) ?><small>Рассылка №<?= (int) $mailing['id'] ?> · <?= htmlspecialchars((string) ($mailing['display_name'] ?: $mailing['username'])) ?></small></div>
        <button type="button" class="details-close" data-close-details aria-label="Закрыть">×</button>
      </div>
      <div class="details-body">
        <div class="details-summary">
          <div class="details-summary-item"><span class="details-label">Состояние</span><div class="details-summary-value"><span class="status <?= (int) $mailing['is_active'] === 1 ? 'status-SENT' : 'status-SKIPPED' ?>"><?= (int) $mailing['is_active'] === 1 ? 'Активна' : 'Отключена' ?></span></div></div>
          <div class="details-summary-item"><span class="details-label">Расписание</span><div class="details-summary-value"><?= htmlspecialchars((string) $mailing['schedule_label']) ?></div></div>
          <div class="details-summary-item"><span class="details-label">Следующий запуск</span><div class="details-summary-value"><?= htmlspecialchars($dateText($mailing['next_run_at'] ?? null)) ?></div></div>
          <div class="details-summary-item"><span class="details-label">Отчёты</span><div class="details-summary-value"><?= count($attachments) ?> · XLSX</div></div>
        </div>
        <section class="details-section">
          <h3>Общие настройки</h3>
          <div class="details-grid">
            <div class="details-item"><span class="details-label">Пользователь</span><div class="details-value"><?= htmlspecialchars((string) ($mailing['display_name'] ?: $mailing['username'])) ?> (<?= htmlspecialchars((string) $mailing['username']) ?>)</div></div>
            <div class="details-item"><span class="details-label">Не отправлять пустой отчёт</span><div class="details-value"><?= (int) $mailing['skip_empty'] === 1 ? 'Да' : 'Нет' ?></div></div>
            <div class="details-item"><span class="details-label">Последний запуск</span><div class="details-value"><?= htmlspecialchars($dateText($mailing['last_run_at'] ?? null)) ?></div></div>
            <div class="details-item"><span class="details-label">Создана</span><div class="details-value"><?= htmlspecialchars($dateText($mailing['created_at'] ?? null)) ?></div></div>
          </div>
        </section>
        <?php foreach ($attachments as $attachmentIndex => $attachment): ?>
          <?php
            $attachmentReport = $reports[$attachment['report_code']] ?? [];
            $attachmentView = strtoupper((string) $attachment['report_view']) === 'SUMMARY' ? 'Сводный' : 'Подробный';
            $filterLabels = [];
            foreach (($attachmentReport['filters'] ?? []) as $filter) $filterLabels[(string) $filter['name']] = (string) $filter['label'];
          ?>
          <section class="details-section attachment-card">
            <h3><span class="attachment-number"><?= $attachmentIndex + 1 ?></span><?= htmlspecialchars($reportLabels[$attachment['report_code']] ?? (string) $attachment['report_code']) ?><span><?= htmlspecialchars($attachmentView) ?> отчёт</span></h3>
            <div class="details-grid">
              <div class="details-item"><span class="details-label">Организация</span><div class="details-value"><?= htmlspecialchars((string) ($attachment['organization_short_name'] ?: ($attachment['organization_name'] ?? 'Общий отчёт'))) ?></div></div>
              <div class="details-item"><span class="details-label">Формат</span><div class="details-value">XLSX</div></div>
            </div>
            <?php if (!empty($attachment['filters'])): ?>
              <div class="filter-list">
                <?php foreach ((array) $attachment['filters'] as $key => $value): ?>
                  <?php $valueText = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value; ?>
                  <?php if ($valueText !== ''): ?><div class="filter-chip"><strong><?= htmlspecialchars($filterLabels[(string) $key] ?? (string) $key) ?>:</strong> <?= htmlspecialchars($valueText) ?></div><?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
        <section class="details-section">
          <h3>Получатели <span><?= count((array) ($mailing['recipients'] ?? [])) ?></span></h3>
          <?php if (empty($mailing['recipients'])): ?>
            <div class="details-value">Получатели не заданы</div>
          <?php else: ?>
            <ul class="details-list">
              <?php foreach ($mailing['recipients'] as $recipient): ?>
                <li><?= htmlspecialchars((string) $recipient['email']) ?> — <?= htmlspecialchars(['TO' => 'Кому', 'CC' => 'Копия', 'BCC' => 'Копия'][strtoupper((string) $recipient['send_type'])] ?? (string) $recipient['send_type']) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
        <section class="details-section">
          <h3>Содержание письма</h3>
          <div class="details-grid">
            <div class="details-item details-wide"><span class="details-label">Тема</span><div class="details-value"><?= htmlspecialchars((string) ($mailing['subject_text'] ?? '—')) ?></div></div>
            <div class="details-item details-wide"><span class="details-label">Текст</span><div class="details-value"><?= htmlspecialchars((string) ($mailing['body_text'] ?? '—')) ?></div></div>
          </div>
        </section>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<script>
document.querySelectorAll('[data-mailing-id]').forEach(function (row) {
  function openDetails() {
    var modal = document.getElementById('mailing-details-' + row.dataset.mailingId)
    if (modal) modal.classList.add('is-open')
  }
  row.addEventListener('click', openDetails)
  row.addEventListener('keydown', function (event) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      openDetails()
    }
  })
})
document.querySelectorAll('.details-wrap').forEach(function (modal) {
  modal.addEventListener('click', function (event) {
    if (event.target === modal || event.target.closest('[data-close-details]')) modal.classList.remove('is-open')
  })
})
document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') document.querySelectorAll('.details-wrap.is-open').forEach(function (modal) { modal.classList.remove('is-open') })
})
</script>
</body>
</html>
