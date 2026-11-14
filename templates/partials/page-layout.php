<?php
$layoutType = (string) (($pageLayout['type'] ?? '') ?: 'report');
$template = $layoutType === 'custom'
    ? (string) ($pageLayout['template'] ?? '')
    : 'partials/wagon-report.php';

if ($template !== '' && str_starts_with($template, 'partials/')) {
    include __DIR__ . '/../' . $template;
}
