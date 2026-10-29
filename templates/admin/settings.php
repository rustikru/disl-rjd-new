<?php require_once __DIR__ . '/../partials/ui.php'; $title = $visibility ? 'Видимость страниц' : 'Настройки приложения'; ?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars($title) ?></title><link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
<style>
.settings-layout{max-width:1250px}.settings-layout .ui-page-head h1{font-size:22px;margin:0}.settings-layout .ui-card{max-width:none}.settings-layout .ui-card-body{padding:12px}.settings-layout .ui-fields{grid-template-columns:repeat(3,minmax(0,1fr));gap:8px 12px;align-items:start}.settings-layout .ui-field{min-width:0;margin:0}.settings-layout .ui-field>label{display:flex;align-items:flex-end;height:22px;font-size:10px;line-height:12px;letter-spacing:0;text-transform:none}.settings-layout .ui-field input,.settings-layout .ui-field select{box-sizing:border-box;width:100%;height:34px;padding:6px 9px;font-size:12px;min-width:0;border-radius:6px}.settings-section-title{grid-column:1/-1;margin:5px 0 0;padding:7px 0 5px;border-bottom:1px solid var(--border);font-size:12px;color:var(--accent)}.settings-section-title:first-child{margin-top:0}.settings-layout .option{display:flex;align-items:center;gap:9px;box-sizing:border-box;height:34px;font-size:12px;padding:6px 9px;border:1px solid var(--border-lt);border-radius:6px;margin:0;cursor:pointer}.settings-layout .option:has(input:checked){background:var(--accent-lt);border-color:var(--accent)}.settings-layout .option input{width:auto;height:auto;accent-color:var(--accent);margin:0}.settings-layout .ui-form-actions{margin-top:10px}.settings-layout .ui-fields>p{margin:0;font-size:12px;color:var(--text-3)}.settings-layout textarea{min-height:52px;font-size:12px}.settings-layout .ui-help{margin-top:3px;font-size:10px;line-height:12px}@media(max-width:1000px){.settings-layout .ui-fields{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.settings-layout .ui-fields{grid-template-columns:1fr}}
</style></head><body>
<?php $headerSub = '<div class="brand-sub">Администрирование</div>'; $headerLeft = ''; $headerRight = ''; include __DIR__ . '/../partials/header.php'; ?>
<div class="app-body"><?php $activeAdminPage = $visibility ? 'visibility' : 'settings'; include __DIR__ . '/../partials/admin-sidebar.php'; ?><main class="main-content settings-layout">
<div class="ui-page-head"><h1><?= htmlspecialchars($title) ?></h1></div>
<?php uiNotices($success, $error); ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
<section class="ui-card"><div class="ui-card-body"><div class="ui-fields">
<?php if ($visibility): ?>
<p class="ui-field--full">Отмеченные страницы доступны. Снимите галочку, чтобы отключить страницу для всех пользователей.</p>
<?php $visibilityAvailability = new \App\Services\PageAvailability((array) ($values['disabled_pages'] ?? [])); $pageGroups = []; foreach (\App\Services\PageRegistry::all() as $key => $definition) { $pageGroups[$definition['visibility_group'] ?? 'Дополнительно'][] = $key; } foreach ($pageGroups as $section => $keys): ?>
<h2 class="settings-section-title"><?= htmlspecialchars($section) ?></h2>
<?php foreach ($keys as $key): $label = \App\Services\PageRegistry::labels()[$key]; ?>
<label class="option"><input type="checkbox" name="enabled[]" value="<?= htmlspecialchars($key) ?>" <?= !$visibilityAvailability->isDisabled($key) ? 'checked' : '' ?>> <?= htmlspecialchars($label) ?></label>
<?php endforeach; endforeach; ?>
<div class="ui-field ui-field--full"><label>Дополнительно отключённые пути, по одному на строку</label><textarea name="custom"><?= htmlspecialchars(implode("\n", array_diff($values['disabled_pages'] ?? [], array_keys(\App\Services\PageRegistry::labels()), \App\Services\PageRegistry::legacyVisibilityAliases()))) ?></textarea></div>
<?php else:
$grouped = []; foreach ($definitions as $key => $definition) { if ($key === 'disabled_pages') continue; $grouped[$definition['section']][$key] = $values[$key]; }
foreach ($grouped as $section => $settings): ?>
<h2 class="settings-section-title"><?= htmlspecialchars($section) ?></h2>
<?php foreach ($settings as $key => $value): $definition = $definitions[$key]; ?>
<div class="ui-field"><label><?= htmlspecialchars($key) ?></label>
<?php if ($definition['type'] === 'bool'): ?><select name="settings[<?= htmlspecialchars($key) ?>]"><option value="1" <?= $value ? 'selected' : '' ?>>Включено</option><option value="0" <?= !$value ? 'selected' : '' ?>>Выключено</option></select>
<?php elseif ($definition['type'] === 'enum'): ?><select name="settings[<?= htmlspecialchars($key) ?>]"><?php foreach($definition['choices'] as $choice): ?><option value="<?= htmlspecialchars($choice) ?>" <?= $value === $choice ? 'selected' : '' ?>><?= htmlspecialchars($choice) ?></option><?php endforeach; ?></select>
<?php else: $secret = $definition['type'] === 'secret'; ?><input type="<?= $secret ? 'password' : ($definition['type'] === 'int' ? 'number' : 'text') ?>" name="settings[<?= htmlspecialchars($key) ?>]" value="<?= $secret ? '' : htmlspecialchars((string) $value) ?>" <?= $definition['min'] !== null ? 'min="'.(int)$definition['min'].'"' : '' ?> <?= $definition['max'] !== null ? 'max="'.(int)$definition['max'].'"' : '' ?> <?= $secret ? 'placeholder="Оставьте пустым, чтобы сохранить" autocomplete="new-password"' : '' ?> <?= $definition['type'] === 'path' ? 'title="Путь от корня проекта. Для журнала укажите также имя файла."' : '' ?>><?php endif; ?></div>
<?php endforeach; endforeach; endif; ?>
</div></div></section><div class="ui-form-actions"><button class="btn btn-primary" type="submit">Сохранить</button></div></form></main></div></body></html>
