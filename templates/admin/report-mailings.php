<?php
$basePath = $basePath ?? '';
$mailingReports = $mailingReports ?? [];
$mailings = $mailings ?? [];
$mailingsPage = $mailingsPage ?? 1;
$mailingsPages = $mailingsPages ?? 1;
$mailingsTotal = $mailingsTotal ?? count($mailings);
$editingMailing = $editingMailing ?? null;
$showMailingSettings = $showMailingSettings ?? false;
$scheduleLabels = ['daily' => 'Каждый день', 'weekly' => 'Еженедельно', 'monthly' => 'Ежемесячно'];
$weekDayLabels = [1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'];
$editingReportFilters = [];
foreach ($editingMailing['items'] ?? [] as $editingMailingItem) {
  $editingReportFilters[(string) $editingMailingItem['report_code']] = $editingMailingItem['filters'] ?? [];
}
$editingWeekDays = array_filter(explode(',', (string) ($editingMailing['week_days'] ?? '')));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Рассылка отчётов</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .mailing-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .mailing-title { font-size:18px; font-weight:700; color:var(--text-1); }
    .mailing-settings { max-width:1080px; }
    .mailing-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
    .mailing-panel--reports { overflow:visible; }
    .mailing-panel + .mailing-panel { margin-top:12px; }
    .mailing-panel-head { padding:10px 14px; border-bottom:1px solid var(--border); }
    .mailing-panel-title { font-size:13px; font-weight:600; color:var(--text-1); }
    .mailing-panel-note { margin-top:2px; font-size:11px; color:var(--text-3); }
    .mailing-panel-body { padding:10px 14px; }
    .mailing-fields { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px 12px; }
    .mailing-field { display:flex; flex-direction:column; gap:4px; }
    .mailing-field[hidden] { display:none; }
    .mailing-field--wide { grid-column:1 / -1; }
    .mailing-field label { font-size:11px; font-weight:600; color:var(--text-2); }
    .mailing-help { font-size:10.5px; line-height:1.35; color:var(--text-3); }
    .mailing-field input:not([type="checkbox"]), .mailing-field select, .mailing-field textarea { width:100%; box-sizing:border-box; border:1px solid var(--border); border-radius:7px; padding:6px 9px; font-family:inherit; font-size:12px; color:var(--text-1); background:var(--surface); outline:none; }
    .mailing-field input:not([type="checkbox"]), .mailing-field select { height:34px; }
    .mailing-field textarea { min-height:46px; resize:vertical; }
    .mailing-field input:focus, .mailing-field select:focus, .mailing-field textarea:focus { border-color:var(--accent); }
    .report-picker { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:4px 18px; }
    .report-option { display:flex; align-items:center; gap:7px; padding:3px 0; cursor:pointer; font-size:12px; color:var(--text-1); }
    .report-option:hover { color:var(--accent); }
    .report-option input { width:14px; height:14px; margin:0; accent-color:var(--accent); }
    .report-group { margin-top:10px; padding-top:9px; border-top:1px solid var(--border); }
    .report-group-title { margin-bottom:5px; color:var(--text-2); font-size:11px; font-weight:700; }
    #selectedReports { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:8px; margin-top:8px; }
    .selected-report { min-width:0; border:1px solid var(--border); border-radius:8px; overflow:visible; }
    .selected-report-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:5px 9px; background:var(--surface-2, #f7f7f9); }
    .selected-report-name { font-size:12px; font-weight:700; }
    .selected-report-remove { border:0; background:none; color:var(--brand-neg); cursor:pointer; font-size:11px; }
    .selected-report-filters { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:6px 8px; padding:7px 9px; }
    .selected-report-filters .mailing-field input:not([type="checkbox"]), .selected-report-filters .mailing-field select { height:30px; padding:4px 7px; font-size:11.5px; }
    .selected-report-filters .mailing-field label { font-size:10.5px; }
    .mailing-field--suggestions { position:relative; }
    .mailing-suggestions { position:absolute; left:0; right:0; top:calc(100% + 3px); z-index:50; max-height:190px; overflow:auto; padding:3px; background:var(--surface); border:1px solid var(--border); border-radius:7px; box-shadow:0 7px 18px rgba(27,23,38,.12); }
    .mailing-suggestions[hidden] { display:none; }
    .mailing-suggestion { display:block; width:100%; padding:5px 7px; border:0; border-radius:5px; background:transparent; color:var(--text-1); font:inherit; font-size:10.5px; line-height:1.25; text-align:left; cursor:pointer; }
    .mailing-suggestion:hover, .mailing-suggestion:focus { color:var(--accent); background:var(--accent-lt); outline:none; }
    .schedule-days { display:flex; flex-wrap:wrap; gap:8px 18px; min-height:34px; align-items:center; }
    .schedule-day { display:inline-flex; align-items:center; gap:6px; color:var(--text-2); font-size:12px; cursor:pointer; }
    .schedule-day input { width:14px; height:14px; margin:0; accent-color:var(--accent); }
    .schedule-day:has(input:checked) { color:var(--text-1); font-weight:600; }
    .mailing-schedule { grid-template-columns:260px 150px; justify-content:start; align-items:start; column-gap:12px; }
    .mailing-schedule #weekDaysField { grid-column:1 / -1; width:max-content; max-width:100%; }
    .mailing-schedule #monthDayField { width:260px; }
    .mailing-schedule .schedule-days { min-height:28px; gap:6px 16px; }
    .mailing-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:10px; }
    .mailing-actions .btn { min-height:34px; padding:6px 11px; font-size:12px; }
    .mailing-flash { padding:8px 12px; border-radius:8px; margin-bottom:10px; font-size:12px; overflow-wrap:anywhere; }
    .mailing-flash--ok { color:var(--brand-green); background:#e8f6ef; border:1px solid #bfe6d2; }
    .mailing-flash--error { color:var(--brand-neg); background:#fbecec; border:1px solid #f0c9c9; }
    .mailing-table-wrap { overflow:auto; }
    .mailing-table { width:100%; min-width:980px; border-collapse:collapse; }
    .mailing-table th { padding:8px 11px; border-bottom:1px solid var(--border); color:var(--text-3); font-size:10px; text-align:left; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
    .mailing-table td { padding:8px 11px; border-bottom:1px solid var(--border); color:var(--text-1); font-size:11.5px; line-height:1.3; vertical-align:top; }
    .mailing-table th:last-child, .mailing-table td:last-child { width:146px; min-width:146px; }
    .mailing-table td:nth-child(2) { overflow-wrap:anywhere; }
    .mailing-table tbody tr:last-child td { border-bottom:0; }
    .mailing-table tbody tr:hover td { background:var(--hover-green); }
    .mailing-card-name { font-size:11.5px; font-weight:700; color:var(--text-1); }
    .mailing-card-meta { margin-top:2px; font-size:10.5px; color:var(--text-3); line-height:1.3; overflow-wrap:anywhere; }
    .mailing-status { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; }
    .mailing-status::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--text-3); }
    .mailing-status--active { color:var(--brand-green); }
    .mailing-status--active::before { background:var(--brand-green); }
    .mailing-status--error { color:var(--brand-neg); }
    .mailing-status--error::before { background:var(--brand-neg); }
    .mailing-card-actions { display:flex; justify-content:flex-end; gap:6px; flex-wrap:nowrap; min-width:146px; }
    .mailing-card-actions form { margin:0; }
    .mailing-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; box-sizing:border-box; padding:0; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text-2); cursor:pointer; text-decoration:none; transition:background .15s,color .15s,border-color .15s; }
    .mailing-icon-btn:hover { color:var(--accent); border-color:var(--accent); background:var(--accent-lt); }
    .mailing-icon-btn--run { color:var(--text-2); border-color:var(--border); background:var(--surface); }
    .mailing-icon-btn--run:hover { color:var(--accent); border-color:var(--accent); background:var(--accent-lt); }
    .mailing-icon-btn--delete:hover { color:var(--brand-neg); border-color:var(--brand-neg); background:#fbecec; }
    .mailing-new-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 11px; font-size:12.5px; }
    .mailing-empty { padding:22px; text-align:center; color:var(--text-3); border:1px dashed var(--border); border-radius:9px; }
    @media (max-width: 900px) { #selectedReports { grid-template-columns:1fr; } }
    @media (max-width: 800px) { .report-picker { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 680px) {
      .mailing-fields, .mailing-schedule, .report-picker, .selected-report-filters { grid-template-columns:1fr; }
      .mailing-schedule #weekDaysField { grid-column:auto; width:100%; }
      .mailing-schedule #monthDayField { width:100%; }
    }
  </style>
</head>
<body>
<?php
  $headerSub = '<div class="brand-sub">Профиль пользователя</div>';
  $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную" aria-label="На главную"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
  $headerRight = '';
  include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <main class="main-content" style="max-width:1440px;margin:0 auto;width:100%">
    <div class="mailing-head">
      <div class="mailing-title"><?= $showMailingSettings ? ($editingMailing ? 'Редактирование рассылки' : 'Новая рассылка') : 'Мои рассылки' ?></div>
      <?php if (!$showMailingSettings): ?><a class="btn btn-primary mailing-new-btn" href="<?= htmlspecialchars($basePath) ?>/profile/report-mailings?view=settings"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>Новая</a><?php endif; ?>
    </div>

    <?php if (!empty($flashOk)): ?><div class="mailing-flash mailing-flash--ok"><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div class="mailing-flash mailing-flash--error"><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <?php if (!$showMailingSettings): ?>
    <section class="mailing-panel">
      <div class="mailing-panel-head">
        <div class="mailing-panel-title">Мои рассылки <span class="mailing-card-meta">Всего: <?= (int) $mailingsTotal ?></span></div>
      </div>
      <?php if (!$mailings): ?>
        <div class="mailing-panel-body"><div class="mailing-empty">Сохранённых рассылок пока нет</div></div>
      <?php else: ?>
        <div class="mailing-table-wrap">
          <table class="mailing-table">
            <thead><tr><th>Рассылка</th><th>Отчёты</th><th>Получатели</th><th>Статус</th><th>Расписание</th><th>Последний запуск</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($mailings as $savedMailing): ?>
              <?php
                $savedMailingId = (int) $savedMailing['id'];
                $savedMailingActive = (int) $savedMailing['is_active'] === 1;
                $savedReportNames = [];
                foreach ($savedMailing['items'] as $savedMailingItem) {
                  $savedReportCode = (string) $savedMailingItem['report_code'];
                  $savedReportNames[] = $mailingReports[$savedReportCode]['name'] ?? $savedReportCode;
                }
                $savedSchedule = $scheduleLabels[$savedMailing['schedule_type']] ?? $savedMailing['schedule_type'];
                if ($savedMailing['schedule_type'] === 'weekly' && !empty($savedMailing['week_days'])) {
                  $savedWeekDays = array_filter(explode(',', (string) $savedMailing['week_days']));
                  $savedWeekDayNames = array_map(
                    static fn(string $day): string => $weekDayLabels[(int) $day] ?? $day,
                    $savedWeekDays
                  );
                  $savedSchedule .= ': ' . implode(', ', $savedWeekDayNames);
                }
                if ($savedMailing['schedule_type'] === 'monthly' && !empty($savedMailing['month_day'])) {
                  $savedSchedule .= ': ' . (int) $savedMailing['month_day'] . '-го числа';
                }
                $savedSchedule .= ' в ' . $savedMailing['send_time'];
              ?>
              <tr>
                <td><div class="mailing-card-name"><?= htmlspecialchars($savedMailing['name']) ?></div><div class="mailing-card-meta"><?= htmlspecialchars($savedMailing['organization_short_name'] ?: $savedMailing['organization_name']) ?></div></td>
                <td><?= htmlspecialchars(implode(', ', $savedReportNames)) ?></td>
                <td><?= htmlspecialchars($savedMailing['recipients']) ?></td>
                <td><span class="mailing-status <?= $savedMailingActive ? 'mailing-status--active' : '' ?>"><?= $savedMailingActive ? 'Активна' : 'Отключена' ?></span></td>
                <td><?= htmlspecialchars($savedSchedule) ?></td>
                <td>
                  <?php if (!empty($savedMailing['last_run_at'])): ?>
                    <span class="mailing-status <?= $savedMailing['last_run_status'] === 'error' ? 'mailing-status--error' : 'mailing-status--active' ?>">
                      <?= $savedMailing['last_run_status'] === 'error' ? 'Ошибка запуска' : 'Сформировано' ?>
                    </span>
                    <div class="mailing-card-meta"><?= htmlspecialchars($savedMailing['last_run_at']) ?><br><?= htmlspecialchars($savedMailing['last_run_message'] ?? '') ?></div>
                  <?php else: ?>
                    <span class="mailing-card-meta">Ещё не запускалась</span>
                  <?php endif; ?>
                </td>
                <td><div class="mailing-card-actions">
                  <a class="mailing-icon-btn" href="<?= htmlspecialchars($basePath) ?>/profile/report-mailings?edit=<?= $savedMailingId ?>" title="Редактировать" aria-label="Редактировать">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                  </a>
                  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/profile/report-mailings/run" data-mailing-run-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= $savedMailingId ?>">
                    <button class="mailing-icon-btn mailing-icon-btn--run mailing-run-button" type="submit" title="Сформировать сейчас" aria-label="Сформировать сейчас" data-mailing-run-button><span class="mailing-run-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7Z"/></svg></span><span class="mailing-run-spinner" aria-hidden="true"></span><span class="mailing-run-label">Запуск…</span></button>
                  </form>
                  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/profile/report-mailings/active">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= $savedMailingId ?>"><input type="hidden" name="is_active" value="<?= $savedMailingActive ? 0 : 1 ?>">
                    <button class="mailing-icon-btn" type="submit" title="<?= $savedMailingActive ? 'Отключить' : 'Включить' ?>" aria-label="<?= $savedMailingActive ? 'Отключить' : 'Включить' ?>">
                      <?php if ($savedMailingActive): ?><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg><?php else: ?><svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7Z"/></svg><?php endif; ?>
                    </button>
                  </form>
                  <form method="POST" action="<?= htmlspecialchars($basePath) ?>/profile/report-mailings/delete" onsubmit="return confirm('Удалить рассылку?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= $savedMailingId ?>">
                    <button class="mailing-icon-btn mailing-icon-btn--delete" type="submit" title="Удалить" aria-label="Удалить"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 10v6M14 10v6"/></svg></button>
                  </form>
                </div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($mailingsPages > 1): ?>
          <?php $firstMailingsPage = max(1, $mailingsPage - 2); $lastMailingsPage = min($mailingsPages, $mailingsPage + 2); ?>
          <?php
            $paginationPage = $mailingsPage;
            $paginationPages = $mailingsPages;
            $paginationUrl = static fn(int $page): string => $basePath . '/profile/report-mailings?page=' . $page;
            $paginationLabel = 'Страницы списка рассылок';
            include __DIR__ . '/../partials/pagination.php';
          ?>
        <?php endif; ?>
      <?php endif; ?>
    </section>
    <?php else: ?>
    <form id="mailingForm" method="POST" action="<?= htmlspecialchars($basePath) ?>/profile/report-mailings/save" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) ($editingMailing['id'] ?? 0) ?>">
      <div class="mailing-settings">
          <section class="mailing-panel mailing-panel--main">
            <div class="mailing-panel-body mailing-fields">
              <div class="mailing-field">
                <label for="mailingName">Название рассылки</label>
                <input id="mailingName" name="name" maxlength="150" required value="<?= htmlspecialchars($editingMailing['name'] ?? '') ?>" placeholder="Например: Ежедневный отчёт">
              </div>
              <div class="mailing-field">
                <label for="mailingSubject">Тема письма</label>
                <input id="mailingSubject" name="subject" maxlength="250" value="<?= htmlspecialchars($editingMailing['subject'] ?? '') ?>" placeholder="Отчёты РЖД">
              </div>
              <div class="mailing-field">
                <label for="mailingRecipients">Получатели (через ; или ,)</label>
                <textarea id="mailingRecipients" name="recipients" required placeholder="ivanov@example.ru; petrov@example.ru"><?= htmlspecialchars($editingMailing['recipients'] ?? ($currentUserEmail ?? '')) ?></textarea>
              </div>
              <div class="mailing-field">
                <label for="mailingBody">Текст письма</label>
                <textarea id="mailingBody" name="message_body" maxlength="4000" placeholder="Введите текст письма"><?= htmlspecialchars($editingMailing['message_body'] ?? 'Во вложении находятся сформированные отчёты.') ?></textarea>
              </div>
            </div>
          </section>

          <section class="mailing-panel">
            <div class="mailing-panel-head"><div class="mailing-panel-title">Расписание</div></div>
            <div class="mailing-panel-body mailing-fields mailing-schedule">
              <div class="mailing-field">
                <label for="scheduleType">Периодичность</label>
                <select id="scheduleType" name="schedule_type"><option value="daily" <?= ($editingMailing['schedule_type'] ?? 'daily') === 'daily' ? 'selected' : '' ?>>Каждый день</option><option value="weekly" <?= ($editingMailing['schedule_type'] ?? '') === 'weekly' ? 'selected' : '' ?>>Еженедельно</option><option value="monthly" <?= ($editingMailing['schedule_type'] ?? '') === 'monthly' ? 'selected' : '' ?>>Ежемесячно</option></select>
              </div>
              <div class="mailing-field"><label for="sendTime">Время отправки</label><input id="sendTime" name="send_time" type="time" value="<?= htmlspecialchars($editingMailing['send_time'] ?? '08:00') ?>"></div>
              <div class="mailing-field mailing-field--wide" id="weekDaysField" hidden>
                <label>Дни недели</label>
                <div class="schedule-days">
                  <?php foreach ($weekDayLabels as $weekDayNumber => $weekDayLabel): ?><label class="schedule-day"><input type="checkbox" name="week_days[]" value="<?= $weekDayNumber ?>" <?= in_array((string) $weekDayNumber, $editingWeekDays, true) ? 'checked' : '' ?>><span><?= $weekDayLabel ?></span></label><?php endforeach; ?>
                </div>
              </div>
              <div class="mailing-field" id="monthDayField" hidden>
                <label for="monthDay">День отправки</label>
                <select id="monthDay" name="month_day">
                  <?php $selectedMonthDay = (int) ($editingMailing['month_day'] ?? 1); ?>
                  <?php for ($monthDay = 1; $monthDay <= 31; $monthDay++): ?>
                    <option value="<?= $monthDay ?>" <?= $selectedMonthDay === $monthDay ? 'selected' : '' ?>><?= $monthDay ?>-го числа</option>
                  <?php endfor; ?>
                </select>
                <div class="mailing-help">Если такого числа нет, отправим в последний день месяца</div>
              </div>
            </div>
          </section>

          <section class="mailing-panel mailing-panel--reports">
            <div class="mailing-panel-head"><div class="mailing-panel-title">Отчёты</div><div class="mailing-panel-note">Можно добавить несколько отчётов в одну рассылку</div></div>
            <div class="mailing-panel-body">
              <div class="report-picker" id="reportPicker"></div>
              <div class="report-group">
                <div class="report-group-title">Справки РЖД</div>
                <div class="report-picker" id="rjdReportPicker"></div>
              </div>
              <div id="selectedReports"></div>
            </div>
          </section>

        <div class="mailing-actions">
          <a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/profile/report-mailings">Отмена</a>
          <button type="submit" class="btn btn-primary" id="saveMailing"><?= $editingMailing ? 'Сохранить изменения' : 'Сохранить' ?></button>
        </div>
      </div>
    </form>
    <?php endif; ?>
  </main>
</div>

<?php if ($showMailingSettings): ?>
<script>
  var MAILING_REPORTS = <?= json_encode($mailingReports, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var MAILING_EDIT_FILTERS = <?= json_encode($editingReportFilters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var MAILING_BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var mailingFilterOptions = {}
  var mailingFilterRequests = {}
  var selectedReportCodes = Object.keys(MAILING_EDIT_FILTERS)

  function mailingInitialDate(initialValue) {
    if (!initialValue) return ''
    var currentDate = new Date()
    var month = String(currentDate.getMonth() + 1).padStart(2, '0')
    var day = initialValue === 'month_start' ? '01' : String(currentDate.getDate()).padStart(2, '0')
    return currentDate.getFullYear() + '-' + month + '-' + day
  }

  function buildMailingFilter(reportCode, filterDefinition) {
    var field = document.createElement('div')
    field.className = 'mailing-field'
    var label = document.createElement('label')
    label.textContent = filterDefinition.label + (filterDefinition.required ? ' *' : '')
    var input
    if (filterDefinition.choices && filterDefinition.choices.length) {
      input = document.createElement('select')
      input.append(new Option('— Не выбрано —', ''))
      filterDefinition.choices.forEach(function (choice) { input.append(new Option(choice.label, choice.value)) })
    } else {
      input = document.createElement('input')
      input.type = filterDefinition.value_type === 'date' ? 'date' : 'text'
      if (filterDefinition.value_type === 'select') {
        input.placeholder = 'Начните вводить значение'
        if (filterDefinition.options_key) {
          field.classList.add('mailing-field--suggestions')
          var listId = 'mailing-options-' + reportCode + '-' + filterDefinition.key
          var list = document.createElement('div')
          list.id = listId
          list.className = 'mailing-suggestions'
          list.hidden = true
          list.setAttribute('role', 'listbox')
          input.setAttribute('role', 'combobox')
          input.setAttribute('aria-autocomplete', 'list')
          input.setAttribute('aria-controls', listId)
          input.setAttribute('aria-expanded', 'false')
          input.addEventListener('input', function () { fillMailingFilterOptions(list, reportCode, filterDefinition.options_key, input) })
          input.addEventListener('focus', function () { fillMailingFilterOptions(list, reportCode, filterDefinition.options_key, input) })
          input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
              list.hidden = true
              input.setAttribute('aria-expanded', 'false')
            }
          })
          field.append(list)
        }
      }
    }
    input.name = 'reports[' + reportCode + '][filters][' + filterDefinition.key + ']'
    input.required = Boolean(filterDefinition.required)
    var savedFilters = MAILING_EDIT_FILTERS[reportCode] || {}
    input.value = savedFilters[filterDefinition.key] !== undefined
      ? savedFilters[filterDefinition.key]
      : mailingInitialDate(filterDefinition.initial_value)
    field.append(label, input)
    return field
  }

  function optionValue(option) {
    if (option === null || option === undefined) return ''
    if (typeof option !== 'object') return String(option)
    return String(option.value || option.station_code || option.code || option.id || '')
  }

  function optionLabel(option) {
    if (option === null || option === undefined || typeof option !== 'object') return ''
    return String(option.label || option.station_name || option.name || '')
  }

  function fillMailingFilterOptions(list, reportCode, optionsKey, input) {
    var options = (mailingFilterOptions[reportCode] || {})[optionsKey] || []
    var query = String(input ? input.value : '').trim().toLocaleLowerCase('ru-RU')
    list.innerHTML = ''
    var matches = options.filter(function (option) {
      var value = optionValue(option)
      var label = optionLabel(option)
      return !query || (value + ' ' + label).toLocaleLowerCase('ru-RU').indexOf(query) !== -1
    }).slice(0, 12)
    matches.forEach(function (option) {
      var value = optionValue(option)
      if (!value) return
      var item = document.createElement('button')
      item.type = 'button'
      item.className = 'mailing-suggestion'
      item.setAttribute('role', 'option')
      var label = optionLabel(option)
      item.textContent = label && label !== value ? label + ' — ' + value : value
      item.addEventListener('mousedown', function (event) {
        event.preventDefault()
        input.value = value
        list.hidden = true
        input.setAttribute('aria-expanded', 'false')
        input.dispatchEvent(new Event('change', { bubbles: true }))
      })
      list.append(item)
    })
    var show = Boolean(input && document.activeElement === input && matches.length)
    list.hidden = !show
    if (input) input.setAttribute('aria-expanded', show ? 'true' : 'false')
  }

  function loadMailingFilterOptions(reportCode) {
    var reportDefinition = MAILING_REPORTS[reportCode]
    if (!reportDefinition || !reportDefinition.filters_endpoint || mailingFilterRequests[reportCode]) return
    mailingFilterRequests[reportCode] = fetch(MAILING_BASE_PATH + reportDefinition.filters_endpoint, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }).then(function (response) {
      if (!response.ok) throw new Error('Не удалось загрузить значения фильтров')
      return response.json()
    }).then(function (data) {
      mailingFilterOptions[reportCode] = data || {}
      reportDefinition.filters.forEach(function (filterDefinition) {
        if (!filterDefinition.options_key) return
        var list = document.getElementById('mailing-options-' + reportCode + '-' + filterDefinition.key)
        var input = list ? list.parentElement.querySelector('input[role="combobox"]') : null
        if (list && input) fillMailingFilterOptions(list, reportCode, filterDefinition.options_key, input)
      })
    }).catch(function () {
      mailingFilterOptions[reportCode] = {}
    })
  }

  function showSelectedReports() {
    var container = document.getElementById('selectedReports')
    container.innerHTML = ''
    selectedReportCodes.forEach(function (reportCode) {
      var reportDefinition = MAILING_REPORTS[reportCode]
      var selected = document.createElement('input')
      selected.type = 'hidden'
      selected.name = 'reports[' + reportCode + '][selected]'
      selected.value = '1'
      if (!reportDefinition.filters.length) {
        container.append(selected)
        return
      }
      var report = document.createElement('div')
      report.className = 'selected-report'
      var head = document.createElement('div')
      head.className = 'selected-report-head'
      var name = document.createElement('span')
      name.className = 'selected-report-name'
      name.textContent = reportDefinition.name
      var remove = document.createElement('button')
      remove.type = 'button'
      remove.className = 'selected-report-remove'
      remove.textContent = 'Убрать'
      remove.addEventListener('click', function () {
        selectedReportCodes = selectedReportCodes.filter(function (selectedCode) { return selectedCode !== reportCode })
        document.querySelector('[data-report-code="' + reportCode + '"]').checked = false
        showSelectedReports()
      })
      head.append(name, remove)
      var filters = document.createElement('div')
      filters.className = 'selected-report-filters'
      reportDefinition.filters.forEach(function (filterDefinition) { filters.append(buildMailingFilter(reportCode, filterDefinition)) })
      report.append(selected, head, filters)
      container.append(report)
      loadMailingFilterOptions(reportCode)
    })
  }

  function showScheduleFields() {
    var scheduleType = document.getElementById('scheduleType').value
    document.getElementById('weekDaysField').hidden = scheduleType !== 'weekly'
    document.getElementById('monthDayField').hidden = scheduleType !== 'monthly'
  }

  Object.keys(MAILING_REPORTS).forEach(function (reportCode) {
    if (MAILING_REPORTS[reportCode].selectable === false) return
    var option = document.createElement('label')
    option.className = 'report-option'
    var checkbox = document.createElement('input')
    checkbox.type = 'checkbox'
    checkbox.dataset.reportCode = reportCode
    checkbox.checked = selectedReportCodes.indexOf(reportCode) !== -1
    checkbox.addEventListener('change', function () {
      if (this.checked) selectedReportCodes.push(reportCode)
      else selectedReportCodes = selectedReportCodes.filter(function (selectedCode) { return selectedCode !== reportCode })
      showSelectedReports()
    })
    var name = document.createElement('span')
    var isRjdReference = reportCode.indexOf('source-') === 0
    name.textContent = isRjdReference
      ? MAILING_REPORTS[reportCode].name.replace(/^Справка РЖД\s*-\s*/, '')
      : MAILING_REPORTS[reportCode].name
    option.append(checkbox, name)
    document.getElementById(isRjdReference ? 'rjdReportPicker' : 'reportPicker').append(option)
  })

  document.getElementById('scheduleType').addEventListener('change', showScheduleFields)
  document.addEventListener('mousedown', function (event) {
    document.querySelectorAll('.mailing-suggestions').forEach(function (list) {
      if (!list.parentElement.contains(event.target)) {
        list.hidden = true
        var input = list.parentElement.querySelector('input[role="combobox"]')
        if (input) input.setAttribute('aria-expanded', 'false')
      }
    })
  })
  document.getElementById('mailingForm').addEventListener('submit', function (event) {
    if (!selectedReportCodes.length) {
      event.preventDefault()
      alert('Добавьте отчёт в рассылку')
    }
  })
  showSelectedReports()
  showScheduleFields()
</script>
<?php endif; ?>
<script src="<?= htmlspecialchars($basePath) ?>/assets/js/mailing-run.js"></script>
</body>
</html>
