<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Активные сессии</title><link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css"></head><body>
<?php $headerSub = '<div class="brand-sub">Администрирование</div>'; $headerLeft = ''; $headerRight = ''; include __DIR__ . '/../partials/header.php'; ?>
<div class="app-body"><?php $activeAdminPage = 'sessions'; include __DIR__ . '/../partials/admin-sidebar.php'; ?><main class="main-content">
<div class="ui-page-head"><div class="ui-page-title">Активные сессии<small>Входы с активностью за последние <?= htmlspecialchars(rtrim(rtrim(number_format($sessionLifetime / 3600, 2, '.', ''), '0'), '.')) ?> ч. Завершение применяется при следующем запросе пользователя.</small></div></div>
<form method="post" id="sessionsForm"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<section class="ui-panel"><div class="ui-panel-head"><span class="ui-panel-title">Пользователи (<?= count($users) ?>) · Входы (<?= count($rows) ?>)</span><button class="btn btn-primary" id="terminate" type="submit" disabled>Завершить сессии выбранных</button></div><div class="ui-table-wrap"><table class="ui-table"><thead><tr><th><input type="checkbox" id="selectAll" aria-label="Выбрать всех пользователей"></th><th>Пользователь</th><th>IP</th><th>Браузер / устройство</th><th>Дата входа</th><th>Последняя активность</th></tr></thead><tbody>
<?php foreach ($users as $id => $row): foreach ($row['entries'] as $index => $entry): ?><tr>
<?php if ($index === 0): ?><td rowspan="<?= $row['sessions'] ?>"><input type="checkbox" name="users[]" value="<?= htmlspecialchars((string) $id) ?>" aria-label="Выбрать <?= htmlspecialchars($row['username']) ?>" data-current="<?= $row['current'] ? '1' : '0' ?>"></td><td rowspan="<?= $row['sessions'] ?>"><?= htmlspecialchars($row['username']) ?><?= $row['current'] ? ' · ваш вход' : '' ?><div class="ui-muted"><?= htmlspecialchars($row['name']) ?></div><div class="ui-muted">Входов: <?= $row['sessions'] ?></div></td><?php endif; ?>
<td><?= htmlspecialchars($entry['ip']) ?></td><td style="max-width:300px;overflow-wrap:anywhere;font-size:11px"><?= htmlspecialchars($entry['agent']) ?></td><td style="white-space:nowrap"><?= date('d.m.Y H:i', $entry['started']) ?></td><td style="white-space:nowrap"><?= date('d.m.Y H:i', $entry['seen']) ?></td></tr><?php endforeach; endforeach; ?>
<?php if (!$users): ?><tr><td colspan="6" class="ui-empty">Нет активных сессий</td></tr><?php endif; ?>
</tbody></table></div></section></form>
<script>
(function(){
 const form=document.getElementById('sessionsForm'), all=document.getElementById('selectAll'), button=document.getElementById('terminate'), boxes=Array.from(form.querySelectorAll('[name="users[]"]'));
 function update(){const count=boxes.filter(box=>box.checked).length;button.disabled=count===0;button.textContent='Завершить сессии выбранных'+(count?' ('+count+')':'');all.checked=boxes.length>0&&count===boxes.length;all.indeterminate=count>0&&count<boxes.length;all.disabled=boxes.length===0;}
 all.addEventListener('change',()=>{boxes.forEach(box=>box.checked=all.checked);update();});boxes.forEach(box=>box.addEventListener('change',update));
 form.addEventListener('submit',event=>{const selected=boxes.filter(box=>box.checked);if(!selected.length||!confirm('Завершить все сессии выбранных пользователей ('+selected.length+')?'+(selected.some(box=>box.dataset.current==='1')?' Вы также выйдете из своей учётной записи.':'')))event.preventDefault();});update();
})();
</script></main></div></body></html>
