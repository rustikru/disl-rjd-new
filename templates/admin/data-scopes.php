<?php require_once __DIR__ . '/../partials/ui.php'; ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($appName) ?> — Области данных</title>
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/data-scopes.js" defer></script>
</head>
<body>
<?php
$headerSub = '<div class="brand-sub">Администрирование</div>';
$headerLeft = '';
$headerRight = '';
include __DIR__ . '/../partials/header.php';
?>
<div class="app-body">
  <?php $activeAdminPage = 'data-scopes'; include __DIR__ . '/../partials/admin-sidebar.php'; ?>
  <main class="main-content <?= $isForm ? 'ui-form-page data-scope-form' : '' ?>">
    <?php uiNotices($flashOk, $flashErr); ?>
    <?php if (!$isForm): ?>
      <div class="ui-page-head">
        <div class="ui-page-title">Области данных<small>Правила видимости данных пользователей</small></div>
        <a class="btn btn-primary" href="<?= htmlspecialchars($basePath) ?>/admin/data-scopes/new">+ Область данных</a>
      </div>
      <section class="ui-panel">
        <div class="ui-panel-head"><span class="ui-panel-title">Области (<?= count($scopes) ?>)</span><input class="ui-search-input" id="scopeSearch" type="search" placeholder="Поиск по названию"></div>
        <div class="ui-table-wrap"><table class="ui-table">
          <thead><tr><th>Название</th><th>Описание</th><th>Правила</th><th>Пользователи</th><th>Статус</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($scopes as $item): ?>
            <tr data-scope-row>
              <td><a class="ui-table-link" href="<?= htmlspecialchars($basePath) ?>/admin/data-scopes/<?= (int) $item['id'] ?>/edit"><?= htmlspecialchars($item['name']) ?></a></td>
              <td><?= !empty($item['description']) ? htmlspecialchars($item['description']) : '<span class="ui-muted">—</span>' ?></td>
              <td><?= (int) $item['rule_count'] ?></td>
              <td><?= (int) $item['user_count'] ?></td>
              <td><?= (int) $item['is_active'] === 1 ? 'Активна' : 'Отключена' ?></td>
              <td><div class="ui-table-actions"><a class="ui-icon-button ui-icon-button--edit" title="Редактировать" aria-label="Редактировать" href="<?= htmlspecialchars($basePath) ?>/admin/data-scopes/<?= (int) $item['id'] ?>/edit"><?= uiEditIcon() ?></a></div></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$scopes): ?><tr><td colspan="6" class="ui-empty">Области данных ещё не созданы</td></tr><?php endif; ?>
          </tbody>
        </table></div>
      </section>
    <?php else: ?>
      <div class="ui-page-head ui-page-head--form"><a class="ui-back-link" href="<?= htmlspecialchars($basePath) ?>/admin/data-scopes">← К списку</a><h1><?= $id ? 'Редактирование области данных' : 'Новая область данных' ?></h1></div>
      <form method="post" action="<?= htmlspecialchars($basePath) ?>/admin/data-scopes">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $id ?>">
        <section class="ui-card">
          <div class="ui-card-head">Область данных</div>
          <div class="ui-card-body ui-fields">
            <div class="ui-field"><label>Название *</label><input name="name" maxlength="120" required value="<?= htmlspecialchars($scope['name']) ?>"></div>
            <div class="ui-field"><label>Статус</label><select name="is_active"><option value="1">Активна</option><option value="0" <?= !(int) $scope['is_active'] ? 'selected' : '' ?>>Отключена</option></select></div>
            <div class="ui-field"><label>Действует с</label><input type="date" name="start_date" value="<?= htmlspecialchars($scope['start_date'] ?? '') ?>"></div>
            <div class="ui-field"><label>Действует по</label><input type="date" name="end_date" value="<?= htmlspecialchars($scope['end_date'] ?? '') ?>"></div>
            <div class="ui-field ui-field--full"><label>Описание</label><input name="description" maxlength="500" value="<?= htmlspecialchars($scope['description'] ?? '') ?>"></div>
          </div>
        </section>
        <section class="ui-card">
          <div class="ui-card-head">Правила доступа</div>
          <div class="ui-card-body">
            <p class="ui-help">Условия внутри правила — «И», разрешающие правила — «ИЛИ». Запреты имеют приоритет во всех назначенных областях. Пустое условие означает любое значение. Пустые условия разрешающего правила открывают всю организацию.</p>
            <div id="scopeRules">
            <?php if (!$rules) $rules = [['organization_id' => 0, 'effect' => 'ALLOW']]; ?>
            <?php foreach ($rules as $index => $rule): ?>
              <div class="scope-rule ui-fields">
                <div class="ui-field"><label>Организация *</label><select name="rules[<?= $index ?>][organization_id]" required><option value="">Выберите</option><?php foreach ($organizations as $organization): ?><option value="<?= (int) $organization['id'] ?>" <?= (int) ($rule['organization_id'] ?? 0) === (int) $organization['id'] ? 'selected' : '' ?>><?= htmlspecialchars($organization['short_name'] ?: $organization['name']) ?></option><?php endforeach; ?></select></div>
                <div class="ui-field"><label>Действие</label><select name="rules[<?= $index ?>][effect]"><option value="ALLOW">Разрешить</option><option value="DENY" <?= ($rule['effect'] ?? '') === 'DENY' ? 'selected' : '' ?>>Запретить</option></select></div>
                <div class="ui-field"><label>Груз</label><input name="rules[<?= $index ?>][cargo_code]" list="scopeCargoes" placeholder="Все грузы" pattern="[0-9]*" maxlength="50" value="<?= htmlspecialchars($rule['cargo_code'] ?? '') ?>"></div>
                <div class="ui-field"><label>Станция назначения</label><input name="rules[<?= $index ?>][station_code]" list="scopeStations" placeholder="Все станции" pattern="[0-9]*" maxlength="20" value="<?= htmlspecialchars($rule['station_code'] ?? '') ?>"></div>
                <button class="btn btn-ghost scope-rule-remove" type="button" aria-label="Убрать правило" title="Убрать правило">×</button>
              </div>
            <?php endforeach; ?>
            </div>
            <datalist id="scopeCargoes"><?php foreach ($cargoes as $cargo): ?><?php $code = rtrim((string) ($cargo['raw_code'] ?? ''), ')'); if ($code === '') continue; ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($cargo['cargo_name']) ?></option><?php endforeach; ?></datalist>
            <datalist id="scopeStations"><?php foreach ($stations as $station): ?><option value="<?= htmlspecialchars($station['esr_code']) ?>"><?= htmlspecialchars($station['station_name']) ?></option><?php endforeach; ?></datalist>
            <button class="btn btn-ghost" id="addScopeRule" type="button">+ Правило</button>
            <p class="ui-help">Области назначаются в форме пользователя. Настроенные ограничения будут применяться после подключения к отчётным выборкам.</p>
          </div>
        </section>
        <div class="ui-form-actions scope-form-actions"><?php if ($id): ?><button class="btn btn-ghost scope-delete-button" type="submit" form="deleteScopeForm">Удалить область</button><?php endif; ?><a class="btn btn-ghost" href="<?= htmlspecialchars($basePath) ?>/admin/data-scopes">Отмена</a><button class="btn btn-primary" type="submit">Сохранить</button></div>
      </form>
      <?php if ($id): ?>
        <form id="deleteScopeForm" method="post" action="<?= htmlspecialchars($basePath) ?>/admin/data-scopes/delete" onsubmit="return confirm('Удалить область и её назначения пользователям?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $id ?>"></form>
      <?php endif; ?>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
