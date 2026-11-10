<?php require_once __DIR__ . '/../partials/ui.php'; $title = $visibility ? 'Видимость страниц' : 'Настройки приложения'; ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($title) ?></title><link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
<style>
.settings-layout{max-width:1250px}.settings-layout .ui-page-head h1{font-size:22px;margin:0}.settings-layout .ui-card{max-width:none}.settings-layout .ui-card-body{padding:12px}.settings-layout .ui-form-actions{margin-top:10px;max-width:none}.settings-layout textarea{min-height:52px;font-size:12px}.settings-tabs{display:flex;gap:4px;padding:6px;border-bottom:1px solid var(--border);overflow-x:auto;background:var(--surface-2)}.settings-tab{flex:0 0 auto;border:0;border-radius:7px;padding:8px 12px;background:transparent;color:var(--text-3);font:inherit;font-size:12px;font-weight:650;cursor:pointer;white-space:nowrap}.settings-tab:hover{background:var(--surface);color:var(--text-1)}.settings-tab.is-active{background:var(--accent-lt);color:var(--accent)}.settings-panel{display:none;padding:4px 12px 8px}.settings-panel.is-active{display:block}.settings-row{display:grid;grid-template-columns:minmax(210px,38%) minmax(260px,1fr);gap:14px;align-items:center;min-height:43px;padding:4px 0;border-bottom:1px solid var(--border-lt)}.settings-row:last-child{border-bottom:0}.settings-name{min-width:0}.settings-name label{display:block;color:var(--text-2);font-size:12px;font-weight:650;line-height:16px;overflow-wrap:anywhere}.settings-name code{display:block;margin-top:1px;color:var(--text-3);font-size:10px;font-weight:500}.settings-control{min-width:0}.settings-control input,.settings-control select{box-sizing:border-box;width:100%;height:32px;min-width:0;padding:5px 9px;border-radius:6px;font-size:12px}.settings-control .ui-help{margin-top:2px;font-size:10px;line-height:13px;color:var(--text-3)}@media(max-width:700px){.settings-row{grid-template-columns:1fr;gap:3px;padding:7px 0}.settings-control input,.settings-control select{height:34px}}
</style>
<style>
.settings-control input,.settings-control select{border:1px solid var(--border);background:var(--surface);color:var(--text-1);font-family:inherit;outline:none;box-shadow:none}
.settings-control input:hover,.settings-control select:hover{border-color:var(--border)}
.settings-control input:focus,.settings-control select:focus{border-color:#c8bedb;box-shadow:0 0 0 2px var(--accent-lt)}
.settings-layout .ui-form-actions{max-width:none}
</style></head><body>
<?php $headerSub = '<div class="brand-sub">Администрирование</div>'; $headerLeft = ''; $headerRight = ''; include __DIR__ . '/../partials/header.php'; ?>
<div class="app-body"><?php $activeAdminPage = $visibility ? 'visibility' : 'settings'; include __DIR__ . '/../partials/admin-sidebar.php'; ?><main class="main-content settings-layout">
<div class="ui-page-head"><h1><?= htmlspecialchars($title) ?></h1></div>
<?php uiNotices($success, $error); ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
<section class="ui-card"><div class="ui-card-body">
<?php if ($visibility): ?>
<?php
$visibilityAvailability = new \App\Services\PageAvailability((array) ($values['disabled_pages'] ?? []));
$pageGroups = [];
foreach (\App\Services\PageRegistry::all() as $key => $definition) {
    $pageGroups[$definition['visibility_group'] ?? 'Дополнительно'][$key] = $definition;
}
foreach ($pageGroups as &$groupPages) {
    uasort($groupPages, static fn(array $a, array $b): int =>
        mb_strtolower((string) ($a['label'] ?? '')) <=> mb_strtolower((string) ($b['label'] ?? ''))
    );
}
unset($groupPages);
$enabledCount = 0;
foreach (array_keys(\App\Services\PageRegistry::labels()) as $key) {
    if (!$visibilityAvailability->isDisabled($key)) $enabledCount++;
}
?>
<div class="visibility-tools">
  <div><strong id="visibilityTotal" data-total="<?= count(\App\Services\PageRegistry::labels()) ?>"><?= $enabledCount ?> из <?= count(\App\Services\PageRegistry::labels()) ?></strong> страниц доступно</div>
  <label class="visibility-search"><input type="search" id="visibilitySearch" aria-label="Найти страницу" placeholder="Найти страницу…" autocomplete="off"></label>
</div>
<p class="visibility-help">Отмеченные страницы доступны. Отключённые скрыты и заблокированы для всех, кроме SUPER.</p>
<div class="visibility-groups">
<?php foreach ($pageGroups as $section => $groupPages): ?>
  <details class="visibility-group">
    <summary><span><?= htmlspecialchars($section) ?></span><span class="visibility-count"><?= count($groupPages) ?></span></summary>
    <div class="visibility-list">
    <?php foreach ($groupPages as $key => $definition): $isEnabled = !$visibilityAvailability->isDisabled($key); ?>
      <label class="visibility-item" data-search="<?= htmlspecialchars(mb_strtolower(($definition['label'] ?? $key) . ' ' . $key)) ?>">
        <input type="checkbox" name="enabled[]" value="<?= htmlspecialchars($key) ?>" <?= $isEnabled ? 'checked' : '' ?>>
        <span class="visibility-name"><strong><?= htmlspecialchars($definition['label'] ?? $key) ?></strong><small><?= htmlspecialchars($key) ?></small></span>
        <span class="visibility-status"><?= $isEnabled ? 'Включено' : 'Отключено' ?></span>
      </label>
    <?php endforeach; ?>
    </div>
  </details>
<?php endforeach; ?>
</div>
<details class="visibility-custom"><summary>Дополнительные пути</summary><div class="ui-field"><label>По одному пути на строку</label><textarea name="custom"><?= htmlspecialchars(implode("\n", array_diff($values['disabled_pages'] ?? [], array_keys(\App\Services\PageRegistry::labels()), \App\Services\PageRegistry::legacyVisibilityAliases()))) ?></textarea></div></details>
<?php else:
$grouped = [];
foreach ($definitions as $key => $definition) {
    if ($key === 'disabled_pages' || $definition['section'] === 'Технические работы') continue;
    $grouped[$definition['section']][$key] = $values[$key];
}
?>
<div class="settings-tabs" role="tablist" aria-label="Разделы настроек">
<?php foreach (array_keys($grouped) as $index => $section): $tabId = 'settings-tab-' . $index; ?>
  <button class="settings-tab<?= $index === 0 ? ' is-active' : '' ?>" type="button" role="tab" id="<?= $tabId ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="settings-panel-<?= $index ?>" data-settings-tab="<?= $index ?>"><?= htmlspecialchars($section) ?></button>
<?php endforeach; ?>
</div>
<?php foreach ($grouped as $section => $settings): $index = array_search($section, array_keys($grouped), true); ?>
<div class="settings-panel<?= $index === 0 ? ' is-active' : '' ?>" id="settings-panel-<?= $index ?>" role="tabpanel" aria-labelledby="settings-tab-<?= $index ?>" data-settings-panel="<?= $index ?>">
<?php foreach ($settings as $key => $value): $definition = $definitions[$key]; $label = (string) ($definition['label'] ?? $key); ?>
  <div class="settings-row">
    <div class="settings-name"><label for="setting-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label><?php if ($label !== $key): ?><code><?= htmlspecialchars($key) ?></code><?php endif; ?></div>
    <div class="settings-control">
    <?php if ($definition['type'] === 'bool'): ?><select id="setting-<?= htmlspecialchars($key) ?>" name="settings[<?= htmlspecialchars($key) ?>]"><option value="1" <?= $value ? 'selected' : '' ?>>Включено</option><option value="0" <?= !$value ? 'selected' : '' ?>>Выключено</option></select>
    <?php elseif ($definition['type'] === 'enum'): ?><select id="setting-<?= htmlspecialchars($key) ?>" name="settings[<?= htmlspecialchars($key) ?>]"><?php foreach($definition['choices'] as $choice): ?><option value="<?= htmlspecialchars($choice) ?>" <?= $value === $choice ? 'selected' : '' ?>><?= htmlspecialchars($choice) ?></option><?php endforeach; ?></select>
    <?php else: $secret = $definition['type'] === 'secret'; ?><input id="setting-<?= htmlspecialchars($key) ?>" type="<?= $secret ? 'password' : ($definition['type'] === 'int' ? 'number' : ($definition['type'] === 'datetime' ? 'datetime-local' : 'text')) ?>" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= $secret ? '' : htmlspecialchars((string) $value) ?>" <?= $definition['min'] !== null ? 'min="'.(int)$definition['min'].'"' : '' ?> <?= $definition['max'] !== null ? 'max="'.(int)$definition['max'].'"' : '' ?> <?= $secret ? 'placeholder="Оставьте пустым, чтобы сохранить" autocomplete="new-password"' : '' ?> <?= $definition['type'] === 'path' ? 'title="Путь от корня проекта. Для журнала укажите также имя файла."' : '' ?>><?php endif; ?>
    <?php if (!empty($definition['help'])): ?><div class="ui-help"><?= htmlspecialchars($definition['help']) ?></div><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div></section><div class="ui-form-actions"><button class="btn btn-primary" type="submit">Сохранить</button></div></form></main></div>
<?php if ($visibility): ?><script src="<?= htmlspecialchars($basePath) ?>/assets/js/page-visibility.js"></script><?php endif; ?>
<?php if (!$visibility): ?><script>
(function () {
  var tabs = Array.from(document.querySelectorAll('[data-settings-tab]'));
  var panels = Array.from(document.querySelectorAll('[data-settings-panel]'));
  var storageKey = 'app-settings-section';
  function activate(index, focus) {
    if (!tabs[index]) index = 0;
    tabs.forEach(function (tab, tabIndex) {
      var active = tabIndex === index;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.tabIndex = active ? 0 : -1;
    });
    panels.forEach(function (panel, panelIndex) {
      panel.classList.toggle('is-active', panelIndex === index);
    });
    sessionStorage.setItem(storageKey, String(index));
    if (focus) tabs[index].focus();
  }
  tabs.forEach(function (tab, index) {
    tab.addEventListener('click', function () { activate(index, false); });
    tab.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
      event.preventDefault();
      activate((index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length, true);
    });
  });
  var saved = Number(sessionStorage.getItem(storageKey));
  activate(Number.isInteger(saved) && saved >= 0 && saved < tabs.length ? saved : 0, false);
})();
</script><?php endif; ?>
</body></html>
