<?php
$basePath = $basePath ?? '';
$mailingsForAdmin = $mailingsForAdmin ?? [];
$mailingStatusFilter = $mailingStatusFilter ?? 'all';
$mailingStatusCounts = $mailingStatusCounts ?? ['all' => count($mailingsForAdmin), 'active' => 0, 'disabled' => 0];
$mailingsPage = $mailingsPage ?? 1;
$mailingsPages = $mailingsPages ?? 1;
$mailingsTotal = $mailingsTotal ?? count($mailingsForAdmin);
$runJournal = $runJournal ?? [];
$runJournalPage = $runJournalPage ?? 1;
$runJournalPages = $runJournalPages ?? 1;
$runJournalTotal = $runJournalTotal ?? count($runJournal);
$reportNames = $reportNames ?? [];
$scheduleNames = ['daily' => 'Каждый день', 'weekly' => 'Еженедельно', 'monthly' => 'Ежемесячно'];
$weekDayNames = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
$statusNames = ['running' => 'Выполняется', 'success' => 'Успешно', 'error' => 'Ошибка'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Рассылки пользователей</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .admin-head { margin-bottom:18px; }
    .admin-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .admin-title small { display:block; margin-top:3px; color:var(--text-3); font-size:12px; font-weight:500; }
    .mailing-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .mailing-panel + .mailing-panel { margin-top:18px; }
    .mailing-panel-head { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:14px 18px; border-bottom:1px solid var(--border); }
    .mailing-panel-title { font-size:14px; font-weight:700; }
    .mailing-count { color:var(--text-3); font-size:12px; }
    .mailing-filter { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
    .mailing-filter-link { display:inline-flex; align-items:center; gap:6px; min-height:30px; padding:5px 10px; border:1px solid var(--border); border-radius:8px; color:var(--text-2); background:var(--surface); font-size:12px; font-weight:600; text-decoration:none; }
    .mailing-filter-link:hover { color:var(--accent); border-color:var(--accent); }
    .mailing-filter-link.is-active { color:var(--accent); border-color:var(--accent); background:var(--accent-lt); }
    .mailing-filter-number { color:var(--text-3); font-size:11px; }
    .mailing-table-wrap { overflow:auto; }
    .mailing-table { width:100%; border-collapse:collapse; min-width:980px; }
    .mailing-table th { padding:10px 14px; text-align:left; border-bottom:1px solid var(--border); color:var(--text-3); font-size:11px; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
    .mailing-table td { padding:12px 14px; border-bottom:1px solid var(--border); color:var(--text-1); font-size:12.5px; vertical-align:top; }
    .mailing-table tbody tr:last-child td { border-bottom:0; }
    .mailing-table tbody tr:hover td { background:var(--hover-green); }
    .mailing-name { font-weight:700; margin-bottom:3px; }
    .mailing-muted { color:var(--text-3); font-size:11.5px; line-height:1.45; overflow-wrap:anywhere; }
    .mailing-status { display:inline-flex; align-items:center; gap:6px; font-weight:600; white-space:nowrap; }
    .mailing-status::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--text-3); }
    .mailing-status--success { color:var(--brand-green); }
    .mailing-status--success::before { background:var(--brand-green); }
    .mailing-status--error { color:var(--brand-neg); }
    .mailing-status--error::before { background:var(--brand-neg); }
    .mailing-status--running { color:var(--accent); }
    .mailing-status--running::before { background:var(--accent); }
    .mailing-empty { padding:28px; text-align:center; color:var(--text-3); }
    .mailing-flash { padding:10px 14px; margin-bottom:14px; border-radius:9px; font-size:13px; }
    .mailing-flash--ok { color:var(--brand-green); background:#e8f6ef; border:1px solid #bfe6d2; }
    .mailing-flash--error { color:var(--brand-neg); background:#fbecec; border:1px solid #f0c9c9; }
    .mailing-actions { display:flex; gap:6px; justify-content:flex-end; }
    .mailing-actions form { margin:0; }
    .mailing-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:31px; height:31px; padding:0; border:1px solid var(--border); border-radius:8px; color:var(--text-2); background:var(--surface); cursor:pointer; }
    .mailing-icon-btn:hover { color:var(--accent); border-color:var(--accent); background:var(--accent-lt); }
    .compact-mailing-table th { padding:8px 11px; font-size:10px; }
    .compact-mailing-table td { padding:8px 11px; font-size:11.5px; line-height:1.3; }
    .compact-mailing-table .mailing-name { margin-bottom:1px; }
    .compact-mailing-table .mailing-muted { font-size:10.5px; line-height:1.3; }
  </style>
</head>
<body>
<?php
  $headerSub = '<div class="brand-sub">Администрирование</div>';
  $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную" aria-label="На главную"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
  $headerRight = '';
  include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'report-mailings'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content">
    <div class="admin-head"><div class="admin-title">Рассылки пользователей<small>Рассылки пользователей и журнал их запуска</small></div></div>
    <?php if (!empty($flashOk)): ?><div class="mailing-flash mailing-flash--ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="mailing-flash mailing-flash--error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <section class="mailing-panel">
      <div class="mailing-panel-head">
        <div class="mailing-panel-title">Рассылки</div>
        <nav class="mailing-filter" aria-label="Фильтр рассылок">
          <?php foreach (['all' => 'Все', 'active' => 'Активные', 'disabled' => 'Отключённые'] as $filterCode => $filterName): ?>
            <a class="mailing-filter-link<?= $mailingStatusFilter === $filterCode ? ' is-active' : '' ?>" href="<?= htmlspecialchars($basePath) ?>/admin/report-mailings?status=<?= $filterCode ?>"><?= $filterName ?><span class="mailing-filter-number"><?= (int) ($mailingStatusCounts[$filterCode] ?? 0) ?></span></a>
          <?php endforeach; ?>
        </nav>
      </div>
      <?php if (!$mailingsForAdmin): ?><div class="mailing-empty"><?= $mailingStatusFilter === 'active' ? 'Активных рассылок нет' : ($mailingStatusFilter === 'disabled' ? 'Отключённых рассылок нет' : 'Рассылок пока нет') ?></div><?php else: ?>
        <div class="mailing-table-wrap"><table class="mailing-table compact-mailing-table">
          <thead><tr><th>Пользователь</th><th>Рассылка</th><th>Статус</th><th>Отчёты</th><th>Получатели</th><th>Расписание</th><th>Последний запуск</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($mailingsForAdmin as $activeMailing): ?>
            <?php
              $mailingEnabled = (int) $activeMailing['is_active'] === 1;
              $activeReportNames = [];
              foreach (array_filter(array_map('trim', explode(',', (string) $activeMailing['report_codes']))) as $activeReportCode) {
                $activeReportNames[] = $reportNames[$activeReportCode] ?? $activeReportCode;
              }
              $activeSchedule = $scheduleNames[$activeMailing['schedule_type']] ?? $activeMailing['schedule_type'];
              if ($activeMailing['schedule_type'] === 'weekly' && !empty($activeMailing['week_days'])) {
                $activeWeekDays = array_filter(explode(',', (string) $activeMailing['week_days']));
                $activeWeekDayNames = array_map(
                  static fn(string $day): string => $weekDayNames[(int) $day] ?? $day,
                  $activeWeekDays
                );
                $activeSchedule .= ': ' . implode(', ', $activeWeekDayNames);
              }
              if ($activeMailing['schedule_type'] === 'monthly') $activeSchedule .= ': ' . (int) $activeMailing['month_day'] . '-го числа';
            ?>
            <tr>
              <td><div class="mailing-name"><?= htmlspecialchars($activeMailing['display_name'] ?: $activeMailing['username']) ?></div><div class="mailing-muted"><?= htmlspecialchars($activeMailing['username']) ?></div></td>
              <td><div class="mailing-name"><?= htmlspecialchars($activeMailing['name']) ?></div><div class="mailing-muted"><?= htmlspecialchars($activeMailing['organization_short_name'] ?: $activeMailing['organization_name']) ?></div></td>
              <td><span class="mailing-status<?= $mailingEnabled ? ' mailing-status--success' : '' ?>"><?= $mailingEnabled ? 'Активна' : 'Отключена' ?></span></td>
              <td><?= htmlspecialchars(implode(', ', $activeReportNames)) ?></td>
              <td><?= htmlspecialchars($activeMailing['recipients']) ?></td>
              <td><?= htmlspecialchars($activeSchedule) ?><div class="mailing-muted">Время: <?= htmlspecialchars($activeMailing['send_time']) ?></div></td>
              <td><?= !empty($activeMailing['last_run_at']) ? htmlspecialchars($activeMailing['last_run_at']) : '<span class="mailing-muted">Не запускалась</span>' ?></td>
              <td><div class="mailing-actions">
                <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/report-mailings/run">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $activeMailing['id'] ?>">
                  <button class="mailing-icon-btn" type="submit" title="Сформировать немедленно" aria-label="Сформировать немедленно"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7Z"/></svg></button>
                </form>
                <form method="POST" action="<?= htmlspecialchars($basePath) ?>/admin/report-mailings/active" onsubmit="return confirm('<?= $mailingEnabled ? 'Отключить' : 'Включить' ?> рассылку пользователя?')">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $activeMailing['id'] ?>"><input type="hidden" name="is_active" value="<?= $mailingEnabled ? 0 : 1 ?>">
                  <?php if ($mailingEnabled): ?>
                    <button class="mailing-icon-btn" type="submit" title="Отключить" aria-label="Отключить"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg></button>
                  <?php else: ?>
                    <button class="mailing-icon-btn" type="submit" title="Включить" aria-label="Включить"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.8 0"/></svg></button>
                  <?php endif; ?>
                </form>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if ($mailingsPages > 1): ?>
          <?php
            $paginationPage = $mailingsPage;
            $paginationPages = $mailingsPages;
            $paginationUrl = static fn(int $page): string => $basePath . '/admin/report-mailings?status=' . urlencode($mailingStatusFilter) . '&mailings_page=' . $page . '&journal_page=' . $runJournalPage;
            $paginationLabel = 'Страницы списка рассылок';
            include __DIR__ . '/../partials/pagination.php';
          ?>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="mailing-panel">
      <div class="mailing-panel-head"><div class="mailing-panel-title">Журнал запусков</div><div class="mailing-count">Всего: <?= (int) $runJournalTotal ?></div></div>
      <?php if (!$runJournal): ?><div class="mailing-empty">Запусков пока нет</div><?php else: ?>
        <div class="mailing-table-wrap"><table class="mailing-table compact-mailing-table">
          <thead><tr><th>Начало</th><th>Статус</th><th>Пользователь</th><th>Рассылка</th><th>Получатели</th><th>Результат</th></tr></thead>
          <tbody>
          <?php foreach ($runJournal as $journalEntry): ?>
            <tr>
              <td><?= htmlspecialchars($journalEntry['started_at']) ?><div class="mailing-muted"><?= !empty($journalEntry['finished_at']) ? 'Завершён: ' . htmlspecialchars($journalEntry['finished_at']) : 'Выполняется' ?></div></td>
              <td><span class="mailing-status mailing-status--<?= htmlspecialchars($journalEntry['status']) ?>"><?= htmlspecialchars($statusNames[$journalEntry['status']] ?? $journalEntry['status']) ?></span><div class="mailing-muted">Режим: <?= htmlspecialchars($journalEntry['run_mode']) ?></div></td>
              <td><div class="mailing-name"><?= htmlspecialchars($journalEntry['display_name'] ?: $journalEntry['username']) ?></div><div class="mailing-muted"><?= htmlspecialchars($journalEntry['username']) ?></div></td>
              <td><div class="mailing-name"><?= htmlspecialchars($journalEntry['mailing_name']) ?></div><div class="mailing-muted"><?= htmlspecialchars($journalEntry['organization_short_name'] ?: $journalEntry['organization_name']) ?></div></td>
              <td><?= htmlspecialchars($journalEntry['recipients'] ?? '') ?></td>
              <td><?= htmlspecialchars($journalEntry['message'] ?? '') ?><div class="mailing-muted"><?= htmlspecialchars($journalEntry['result_path'] ?? '') ?></div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php if ($runJournalPages > 1): ?>
          <?php
            $paginationPage = $runJournalPage;
            $paginationPages = $runJournalPages;
            $paginationUrl = static fn(int $page): string => $basePath . '/admin/report-mailings?status=' . urlencode($mailingStatusFilter) . '&mailings_page=' . $mailingsPage . '&journal_page=' . $page;
            $paginationLabel = 'Страницы журнала запусков';
            include __DIR__ . '/../partials/pagination.php';
          ?>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
</div>
</body>
</html>
