<?php
/** Общая разметка отчётных вкладок. Поля и идентификаторы берутся из ReportConfig. */
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pageSettings = static function (array $definition): array {
    return is_array($definition['page'] ?? null) ? $definition['page'] : [];
};
$kpiForPage = static function (string $page) use ($pageLayouts): ?string {
    $type = (string) (($pageLayouts[$page] ?? [])['kpi_type'] ?? '');
    return $type !== '' ? $type : null;
};
?>
<div id="pageLoadOverlay" aria-hidden="true"></div>
<div id="panel-dashboard" class="tab-panel"></div>
<?php foreach ($reportDefinitions as $page => $definition):
    if (!in_array('page', $definition['contexts'] ?? [], true)) continue;
    $settings = $pageSettings($definition);
    $hasSummary = !empty($definition['summary_endpoint']) || !empty($settings['summary_table_id']);
    $summaryId = (string) ($settings['summary_table_id'] ?? $page . 'SumTable');
    $detailId = (string) ($settings['detail_table_id'] ?? $page . 'DetTable');
    $summarySub = (string) ($settings['summary_subtitle_id'] ?? $page . 'SumSub');
    $detailSub = (string) ($settings['detail_subtitle_id'] ?? $page . 'DetSub');
    $detailPanel = (string) ($settings['detail_panel_id'] ?? $page . '-detail');
    $name = preg_replace('/[^a-z0-9]/i', '', ucfirst($page));
    $applyId = (string) ($settings['apply_button_id'] ?? 'btn' . $name . 'Apply');
    $resetId = (string) ($settings['reset_button_id'] ?? 'btn' . $name . 'Reset');
    $summaryPanel = $page === 'dislocation' ? 'disl-summary' : $page . '-summary';
    $kpiType = $kpiForPage($page);
    $title = (string) ($definition['name'] ?? $page);
    if ($page === 'approach' && $isMtf) $title .= ' к ст. Углеуральская';
?>
<div id="panel-<?= $escape($page) ?>" class="tab-panel">
  <?php if ($page === 'dislocation' && isset($kpiTypes['dashboard_kpi'])): ?><div class="kpi-grid" data-kpi-container="<?= $escape($kpiTypes['dashboard_kpi']['container_key']) ?>" style="margin-bottom:16px"></div><?php endif; ?>
  <?php if ($kpiType !== null): ?><div class="kpi-board-wrap">
    <?php if (!empty($userKpiManagementEnabled)): ?><div class="kpi-settings-link"><a class="kpi-hidden-open" href="<?= $escape($basePath) ?>/kpi/hidden" aria-label="Скрытые KPI" title="Скрытые KPI"><img src="<?= $escape($basePath) ?>/assets/img/icon/icon-hide.svg" class="user-menu-item-icon"></a></div><?php endif; ?>
    <div class="kpi-grid" data-kpi-container="<?= $escape($kpiTypes[$kpiType]['container_key'] ?? $kpiType) ?>" style="margin-bottom:16px"></div>
  </div><?php endif; ?>
  <?php if (!empty($settings['metrics_id'])): ?><div class="kpi-grid" id="<?= $escape($settings['metrics_id']) ?>" style="margin-bottom:16px"></div><?php endif; ?>
  <?php if ($hasSummary && !empty($definition['detail_endpoint'])): ?><div class="inner-tabs"><button class="inner-tab active" data-inner="<?= $escape($summaryPanel) ?>">Сводная</button><button class="inner-tab" data-inner="<?= $escape($detailPanel) ?>">Подробная</button></div><?php endif; ?>
  <div class="filters-bar"><div class="filters-inner"><div class="filter-actions" style="flex-basis:100%"><button class="btn btn-primary btn-sm" id="<?= $escape($applyId) ?>">Применить</button><button class="btn btn-ghost btn-sm" id="<?= $escape($resetId) ?>">Сбросить</button></div></div></div>
  <?php if ($hasSummary): ?><div id="<?= $escape($summaryPanel) ?>" class="inner-panel active"><section class="table-section"><div class="table-toolbar"><div class="table-info"><span class="table-title"><?= $escape($title) ?> — сводная</span><span class="table-sub" id="<?= $escape($summarySub) ?>"></span></div><div class="table-acts"><button class="btn btn-ghost btn-sm" data-toggle-table="<?= $escape($summaryId) ?>">Свернуть все</button></div></div><div class="table-scroll"><table class="data-table" id="<?= $escape($summaryId) ?>"></table></div></section></div><?php endif; ?>
  <?php if (!empty($definition['detail_endpoint'])): ?><div id="<?= $escape($detailPanel) ?>" class="inner-panel<?= $hasSummary ? '' : ' active' ?>"><section class="table-section"><div class="table-toolbar"><div class="table-info"><span class="table-title"><?= $escape($title) ?> — подробная</span><span class="table-sub" id="<?= $escape($detailSub) ?>"></span></div><div class="table-acts"></div></div><div id="<?= $escape($detailId) ?>"></div></section></div><?php endif; ?>
</div>
<?php endforeach; ?>
<?php if (!empty($userKpiManagementEnabled)): ?><dialog class="kpi-hidden-dialog" id="kpiHiddenDialog" aria-labelledby="kpiHiddenTitle"><div class="kpi-hidden-head"><h2 id="kpiHiddenTitle">Скрытые KPI</h2><button type="button" class="kpi-hidden-close" aria-label="Закрыть">×</button></div><div class="kpi-hidden-list" id="kpiHiddenList">Загрузка…</div></dialog><dialog class="kpi-form-dialog" id="kpiFormDialog" aria-label="Создание KPI"><button type="button" class="kpi-form-close" aria-label="Закрыть">×</button><iframe id="kpiFormFrame" title="Создание KPI"></iframe></dialog><?php endif; ?>
