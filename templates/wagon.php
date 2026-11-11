<?php $e = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'); ?>
<!DOCTYPE html><html lang="ru"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($appName) ?> — Карточка вагона</title>
<link rel="icon" href="<?= $e($basePath) ?>/assets/img/favicon.ico">
<link rel="stylesheet" href="<?= $e($basePath) ?>/assets/css/app.css">
<link rel="stylesheet" href="<?= $e($basePath) ?>/assets/css/wagon.css?v=<?= filemtime(__DIR__ . '/../public/assets/css/wagon.css') ?>">
<script>window.APP_WAGON_CONFIG = <?= json_encode($wagonConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script></head><body>
<?php
$headerSub = '<div class="brand-sub">Карточка вагона</div>';
$headerLeft = '<a href="' . $e($basePath) . '/" class="btn-nav-back" id="wagonBack" title="Назад" aria-label="Назад"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 5-7 7 7 7M5 12h14"/></svg></a>';
include __DIR__ . '/partials/header.php';
?>
<main class="wagon-page">
<div id="wagonMessage" class="wagon-message" role="status">Откройте карточку по номеру вагона из таблицы.</div>
<div id="wagonContent" data-number="<?= $e($number) ?>" data-base="<?= $e($basePath) ?>" hidden><section class="wagon-panel wagon-heading"><h1 id="wagonTitle"></h1><div class="wagon-caption" id="wagonCaption"></div></section><section class="wagon-panel wagon-shell"><nav class="wagon-tabs" aria-label="Разделы карточки"><?php foreach ($wagonConfig['tabs'] as $index => $tab): ?><button class="wagon-tab<?= $index === 0 ? ' active' : '' ?>" data-tab="<?= $e($tab['key']) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"><?= $e($tab['label']) ?></button><?php endforeach; ?></nav><div class="wagon-body" id="wagonSections"></div></section></div>
</main>
<script src="<?= $e($basePath) ?>/assets/js/wagon.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/wagon.js') ?>" defer></script></body></html>
