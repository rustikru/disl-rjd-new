<?php
$basePath = $basePath ?? '';
$currentCode = (string) ($mailing['report_code'] ?? 'dislocation');
$currentReport = $catalog[$currentCode] ?? reset($catalog);
$currentFilters = $mailing['filters'] ?? [];
$currentRecipients = $mailing['recipients'] ?? [];
$selectedDays = array_map('intval', explode(',', (string) ($mailing['week_days'] ?? '')));
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= (int) ($mailing['id'] ?? 0) > 0 ? 'Изменение' : 'Новая' ?> рассылка — <?= htmlspecialchars($appName) ?></title>
  <link rel="icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/mailings.css">
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Настройка рассылки</div>';
$headerLeft = '<a href="' . htmlspecialchars($basePath) . '/mailings" class="btn-nav-back" title="К списку">←</a>';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<main class="mailing-page">
  <div class="mailing-wrap">
    <div class="mailing-head"><div><h1><?= (int) ($mailing['id'] ?? 0) > 0 ? 'Изменить рассылку' : 'Новая рассылка' ?></h1></div></div>
    <?php if ($queryError): ?><div class="mailing-alert is-error"><?= htmlspecialchars($queryError) ?></div><?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($basePath) ?>/mailings/save" id="mailingForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) ($mailing['id'] ?? 0) ?>">
      <div class="mailing-form-grid">
        <div>
          <section class="mailing-card">
            <div class="mailing-card-head"><span class="mailing-card-title">1. Отчёт и данные</span></div>
            <div class="mailing-card-body mailing-fields">
              <div class="mailing-field is-full"><label for="mailingName">Название рассылки</label><input id="mailingName" name="name" maxlength="200" required value="<?= htmlspecialchars($mailing['name'] ?? '') ?>"></div>
              <div class="mailing-field"><label for="reportCode">Отчёт</label><select id="reportCode" name="report_code" required><?php foreach ($catalog as $code => $report): ?><option value="<?= htmlspecialchars($code) ?>" <?= $code === $currentCode ? 'selected' : '' ?>><?= htmlspecialchars($report['name']) ?></option><?php endforeach; ?></select></div>
              <div class="mailing-field" id="organizationField"><label for="organizationId">Организация</label><select id="organizationId" name="organization_id"><?php foreach ($availableOrganizations as $organization): ?><option value="<?= (int) $organization['id'] ?>" <?= (int) ($mailing['organization_id'] ?? 0) === (int) $organization['id'] ? 'selected' : '' ?>><?= htmlspecialchars($organization['short_name'] ?: $organization['name']) ?></option><?php endforeach; ?></select></div>
              <div class="mailing-field"><label for="reportView">Вид отчёта</label><select id="reportView" name="report_view"></select></div>
              <div class="mailing-field"><label for="fileFormat">Формат файла</label><select id="fileFormat" name="file_format"><option value="XLSX" selected>Excel (.xlsx)</option></select></div>
              <div class="mailing-field is-full mailing-filter-box">
                <div class="mailing-filter-head"><span class="mailing-card-title">Фильтры отчёта</span><button type="button" class="mailing-link-button" id="resetMailingFilters">Сбросить</button></div>
                <div class="mailing-fields" id="mailingFilters"></div>
              </div>
            </div>
          </section>

          <section class="mailing-card">
            <div class="mailing-card-head"><span class="mailing-card-title">2. Получатели и письмо</span></div>
            <div class="mailing-card-body">
              <div class="mailing-label">Получатели</div>
              <div id="mailingRecipients">
                <?php foreach ($currentRecipients ?: [['email' => '', 'send_type' => 'TO']] as $recipient): ?>
                  <div class="mailing-recipient-row"><input type="email" name="recipient_email[]" placeholder="mail@example.ru" value="<?= htmlspecialchars($recipient['email'] ?? '') ?>"><select name="recipient_type[]"><?php foreach (['TO' => 'Кому', 'CC' => 'Копия'] as $type => $label): ?><option value="<?= $type ?>" <?= (($recipient['send_type'] ?? 'TO') === $type || ($type === 'CC' && ($recipient['send_type'] ?? '') === 'BCC')) ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select><button type="button" class="mailing-remove-recipient" title="Удалить">×</button></div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="mailing-link-button" id="addMailingRecipient">+ Добавить получателя</button>
              <div class="mailing-fields" style="margin-top:15px">
                <div class="mailing-field is-full"><label for="subjectText">Тема письма</label><input id="subjectText" name="subject_text" maxlength="500" value="<?= htmlspecialchars($mailing['subject_text'] ?? '') ?>"><div class="mailing-note">Можно использовать {report_date} и {organization}.</div></div>
                <div class="mailing-field is-full"><label for="bodyText">Текст письма</label><textarea id="bodyText" name="body_text" maxlength="2000"><?= htmlspecialchars($mailing['body_text'] ?? '') ?></textarea></div>
              </div>
            </div>
          </section>

          <section class="mailing-card">
            <div class="mailing-card-head"><span class="mailing-card-title">3. Расписание</span></div>
            <div class="mailing-card-body mailing-fields">
              <div class="mailing-field"><label for="scheduleType">Периодичность</label><select id="scheduleType" name="schedule_type"><option value="MANUAL" <?= ($mailing['schedule_type'] ?? '') === 'MANUAL' ? 'selected' : '' ?>>Только вручную</option><option value="DAILY" <?= ($mailing['schedule_type'] ?? 'DAILY') === 'DAILY' ? 'selected' : '' ?>>Ежедневно</option><option value="WEEKLY" <?= ($mailing['schedule_type'] ?? '') === 'WEEKLY' ? 'selected' : '' ?>>По дням недели</option><option value="HOURLY" <?= ($mailing['schedule_type'] ?? '') === 'HOURLY' ? 'selected' : '' ?>>Каждые несколько часов</option><option value="MONTHLY" <?= ($mailing['schedule_type'] ?? '') === 'MONTHLY' ? 'selected' : '' ?>>Ежемесячно</option></select></div>
              <div class="mailing-field" id="runTimeField"><label for="runTime">Время отправки</label><input id="runTime" name="run_time" type="time" value="<?= htmlspecialchars($mailing['run_time'] ?? '08:00') ?>"></div>
              <div class="mailing-field is-full" id="weekDaysField"><span class="mailing-label">Дни недели</span><div class="mailing-days"><?php foreach ([1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб', 7 => 'Вс'] as $day => $label): ?><label class="mailing-day"><input type="checkbox" name="week_days[]" value="<?= $day ?>" <?= in_array($day, $selectedDays, true) ? 'checked' : '' ?>><span><?= $label ?></span></label><?php endforeach; ?></div></div>
              <div class="mailing-field" id="intervalHoursField"><label for="intervalHours">Интервал, часов</label><input id="intervalHours" name="interval_hours" type="number" min="1" max="24" value="<?= (int) ($mailing['interval_hours'] ?? 8) ?>"></div>
              <div class="mailing-field" id="monthDayField"><label for="monthDay">День месяца</label><input id="monthDay" name="month_day" type="number" min="1" max="31" value="<?= (int) ($mailing['month_day'] ?? 1) ?>"></div>
              <div class="mailing-field is-full mailing-check"><input id="skipEmpty" name="skip_empty" type="checkbox" value="1" <?= !empty($mailing['skip_empty']) ? 'checked' : '' ?>><label for="skipEmpty">Не отправлять пустой отчёт</label></div>
              <div class="mailing-field is-full mailing-check"><input id="isActive" name="is_active" type="checkbox" value="1" <?= !empty($mailing['is_active']) ? 'checked' : '' ?>><label for="isActive">Рассылка активна</label></div>
            </div>
            <div class="mailing-form-actions"><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/mailings">Отмена</a><button class="btn btn-primary" type="submit">Сохранить рассылку</button></div>
          </section>
        </div>

        <aside class="mailing-card mailing-summary">
          <div class="mailing-card-head"><span class="mailing-card-title">Что будет отправлено</span></div>
          <div class="mailing-card-body">
            <?php foreach (['name' => 'Название', 'report' => 'Отчёт', 'organization' => 'Организация', 'format' => 'Формат', 'schedule' => 'Расписание', 'recipients' => 'Получатели'] as $key => $label): ?><div class="mailing-summary-row"><div class="mailing-summary-label"><?= $label ?></div><div class="mailing-summary-value" data-mailing-summary="<?= $key ?>">—</div></div><?php endforeach; ?>
          </div>
        </aside>
      </div>
    </form>
  </div>
</main>
<script>
window.MAILING_CATALOG = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.MAILING_VALUES = <?= json_encode(['report_view' => $mailing['report_view'] ?? 'DETAIL', 'filters' => $currentFilters], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.APP_BASE = <?= json_encode($basePath, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= htmlspecialchars($basePath) ?>/assets/js/mailings.js"></script>
</body>
</html>
