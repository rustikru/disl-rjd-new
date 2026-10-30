<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $user  ['username', 'display_name', 'email', 'auth_source'] */
$basePath = $basePath ?? '';
$activeOrganizationCode = '';
$activeOrganizationName = '';
foreach ($_SESSION['organizations'] ?? [] as $organization) {
  if ((int) ($organization['id'] ?? 0) === (int) ($_SESSION['organization_id'] ?? 0)) {
    $activeOrganizationCode = (string) ($organization['code'] ?? '');
    $activeOrganizationName = (string) (
      ($organization['short_name'] ?? '') ?: ($organization['name'] ?? '')
    );
    break;
  }
}
$isMtf = strtoupper($activeOrganizationCode) === 'MTF';
$reportElementId = static fn(string $report,string $key,string $fallback): string => htmlspecialchars((string)($reportDefinitions[$report]['page'][$key]??$fallback));
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Дислокация</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <script>
    window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';
    window.APP_ALLOWED_PAGES = <?= json_encode($allowedPages ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.APP_DISABLED_PAGES = <?= json_encode($disabledPages ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.APP_NAV_CONFIG = <?= json_encode($navConfig ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.APP_ORGANIZATION_CODE = <?= json_encode($activeOrganizationCode, JSON_UNESCAPED_UNICODE) ?>;
    window.APP_REPORT_DEFINITIONS = <?= json_encode($reportDefinitions ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.DISLOCATION_STALE_HOURS = <?= (int) ($dislocationStaleHours ?? 8) ?>;
    window.APP_TIMEZONE_OFFSET_SECONDS = <?= (new DateTimeImmutable('now'))->getOffset() ?>;
  </script>
</head>

<body>

  <?php
  $headerSub = '<div id="brandDateSub" class="brand-date-sub"></div>';
  $headerRight = '';
  include __DIR__ . '/partials/header.php';
  ?>

  <div class="app-body">

    <aside class="sidebar" id="sidebar"></aside>

    <main class="main-content">
      <div id="pageLoadOverlay" aria-hidden="true"></div>

      <div id="panel-dashboard" class="tab-panel"></div>

      <!-- Дислокация -->
      <div id="panel-dislocation" class="tab-panel active">
        <div class="kpi-grid" id="kpiGrid" style="margin-bottom:16px"></div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="disl-summary">Сводная дислокация</button>
          <button class="inner-tab" data-inner="disl-extended">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('dislocation','apply_button_id','btnDislocationApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('dislocation','reset_button_id','btnDislocationReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>

        <div id="disl-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">
                  Сводная
                  дислокация<?= $activeOrganizationName !== '' ? ' ' . htmlspecialchars($activeOrganizationName) : '' ?>
                </span>
                <span class="table-sub" id="mainTableSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="mainTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="<?= $reportElementId('dislocation','summary_table_id','mainTable') ?>"></table>
            </div>
          </section>
        </div>

        <div id="disl-extended" class="inner-panel">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info"><span class="table-title">Подробная</span><span class="table-sub"
                  id="dislDetSub"></span></div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('dislocation','detail_table_id','dislExtTable') ?>"></div>
          </section>
        </div>
      </div>

      <!-- Подход -->
      <div id="panel-approach" class="tab-panel">

        <div class="kpi-grid" id="approachMetrics" style="margin-bottom:16px"></div>

        <!-- Внутренние вкладки -->
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="approach-summary">Сводная</button>
          <button class="inner-tab" data-inner="approach-detail">Подробная</button>
        </div>

        <!-- Фильтры -->
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('approach','apply_button_id','btnApproachApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('approach','reset_button_id','btnApproachReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>

        <div id="approach-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span
                  class="table-title"><?= $isMtf ? 'Подход вагонов к ст. Углеуральская (сводная)' : 'Подход вагонов (сводная)' ?></span>
                <span class="table-sub" id="approachSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="approachSumTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="<?= $reportElementId('approach','summary_table_id','approachSumTable') ?>"></table>
            </div>
          </section>
        </div>

        <div id="approach-detail" class="inner-panel">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Подробная в подходе</span>
                <span class="table-sub" id="approachDetSub"></span>
              </div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('approach','detail_table_id','approachDetTable') ?>"></div>
          </section>
        </div>

      </div>

      <!-- Отправление вагонов -->
      <div id="panel-departure" class="tab-panel">
        <div class="kpi-grid" id="departureMetrics" style="margin-bottom:16px"></div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="departure-summary">Сводная</button>
          <button class="inner-tab" data-inner="departure-detail">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('departure','apply_button_id','btnDepartureApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('departure','reset_button_id','btnDepartureReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>
        <div id="departure-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span
                  class="table-title"><?= $isMtf ? 'Отправление вагонов со ст. Углеуральская — сводная' : 'Отправление вагонов — сводная' ?></span>
                <span class="table-sub" id="departureSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="departureSumTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="<?= $reportElementId('departure','summary_table_id','departureSumTable') ?>"></table>
            </div>
          </section>
        </div>
        <div id="departure-detail" class="inner-panel">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Список отправленных вагонов</span>
                <span class="table-sub" id="departureDetSub"></span>
              </div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('departure','detail_table_id','departureDetTable') ?>"></div>
          </section>
        </div>
      </div>

      <!-- Погрузка -->
      <div id="panel-loading" class="tab-panel">
        <div class="kpi-grid" id="loadingMetrics" style="margin-bottom:16px"></div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="loading-summary">Сводная</button>
          <button class="inner-tab" data-inner="loading-detail">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('loading','apply_button_id','btnLoadingApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('loading','reset_button_id','btnLoadingReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>
        <div id="loading-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Погруженные вагоны — сводная</span>
                <span class="table-sub" id="loadingSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="loadingSumTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="<?= $reportElementId('loading','summary_table_id','loadingSumTable') ?>"></table>
            </div>
          </section>
        </div>
        <div id="loading-detail" class="inner-panel">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Список погруженных вагонов</span>
                <span class="table-sub" id="loadingDetSub"></span>
              </div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('loading','detail_table_id','loadingDetTable') ?>"></div>
          </section>
        </div>
      </div>

      <!-- Простои -->
      <div id="panel-downtime" class="tab-panel">
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="downtime-summary">Сводная</button>
          <button class="inner-tab" data-inner="downtime-detail">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('downtime','apply_button_id','btnDowntimeApply') ?>">Применить</button>
            </div>
          </div>
        </div>
        <div id="downtime-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Простои — сводная</span>
                <span class="table-sub" id="downtimeSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="downtimeSumTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="<?= $reportElementId('downtime','summary_table_id','downtimeSumTable') ?>"></table>
            </div>
          </section>
        </div>
        <div id="downtime-detail" class="inner-panel">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Простаивающие вагоны</span>
                <span class="table-sub" id="downtimeDetSub"></span>
              </div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('downtime','detail_table_id','downtimeDetTable') ?>"></div>
          </section>
        </div>
      </div>

      <div id="panel-technical-state" class="tab-panel">
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('technical-state','apply_button_id','btnTechnicalApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('technical-state','reset_button_id','btnTechnicalReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>
        <section class="table-section">
          <div class="table-toolbar">
            <div class="table-info"><span class="table-title">Техническое состояние</span><span class="table-sub"
                id="technicalDetSub"></span></div>
            <div class="table-acts"></div>
          </div>
          <div id="<?= $reportElementId('technical-state','detail_table_id','technicalDetTable') ?>"></div>
        </section>
      </div>

      <div id="panel-claims" class="tab-panel">
        <div class="kpi-grid" id="claimsMetrics" style="margin-bottom:16px"></div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="claims-summary">Сводная</button>
          <button class="inner-tab" data-inner="claims-detail">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('claims','apply_button_id','btnClaimsApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('claims','reset_button_id','btnClaimsReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>
        <div id="claims-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Претензии — сводная</span>
                <span class="table-sub" id="claimsSumSub"></span>

              </div>
              <div class="table-acts"><button class="btn btn-ghost btn-sm" id="btnClaimsExportSummary">Выгрузить в
                  Excel</button></div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="<?= $reportElementId('claims','summary_table_id','claimsSumTable') ?>"></table>
            </div>
          </section>
        </div>
        <div id="claims-detail" class="inner-panel">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info"><span class="table-title">Претензии — подробная</span><span class="table-sub"
                  id="claimsDetSub"></span></div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('claims','detail_table_id','claimsDetTable') ?>"></div>
          </section>
        </div>
      </div>

      <div id="panel-raw-material" class="tab-panel">
        <div class="kpi-grid" id="rawMetrics" style="margin-bottom:16px"></div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="raw-summary">Сводная</button>
          <button class="inner-tab" data-inner="raw-detail">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('raw-material','apply_button_id','btnRawApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('raw-material','reset_button_id','btnRawReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>
        <div id="raw-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Сырьё - простои</span>
                <span class="table-sub" id="rawSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="rawSumTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="<?= $reportElementId('raw-material','summary_table_id','rawSumTable') ?>"></table>
            </div>
          </section>
        </div>
        <div id="raw-detail" class="inner-panel">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Подробная с сырьём</span>
                <span class="table-sub" id="rawDetSub"></span>
              </div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('raw-material','detail_table_id','rawDetTable') ?>"></div>
          </section>
        </div>
      </div>

      <!-- Анализ за период -->
      <div id="panel-analysis-period" class="tab-panel">
        <div class="filters-bar">
          <div class="filters-inner" style="flex-wrap:wrap;gap:8px 16px">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('analysis-period','apply_button_id','btnAnalysisPeriodApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('analysis-period','reset_button_id','btnAnalysisPeriodReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>
        <div id="analysisPeriod-detail" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Анализ за период</span>
                <span class="table-sub" id="analysisPeriodDetSub"></span>
              </div>
              <div class="table-acts"></div>
            </div>
            <div id="<?= $reportElementId('analysis-period','detail_table_id','analysisPeriodDetTable') ?>"></div>
          </section>
        </div>
      </div>

      <!-- Контроль простоев -->
      <div id="panel-downtime-control" class="tab-panel">
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="<?= $reportElementId('downtime-control','apply_button_id','btnDowntimeControlApply') ?>">Применить</button>
              <button class="btn btn-ghost btn-sm" id="<?= $reportElementId('downtime-control','reset_button_id','btnDowntimeControlReset') ?>">Сбросить</button>
            </div>
          </div>
        </div>
        <section class="table-section">
          <div class="table-toolbar">
            <div class="table-info">
              <span class="table-title">Контроль простоев</span>
              <span class="table-sub" id="idleControlSub"></span>
            </div>
            <div class="table-acts"></div>
          </div>
          <div id="<?= $reportElementId('downtime-control','detail_table_id','downtimeControlTable') ?>"></div>
        </section>
      </div>
    </main>
  </div>

  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
  <script
    src="<?= htmlspecialchars($basePath) ?>/assets/js/detail-contexts.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/detail-contexts.js') ?>"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/csv-export.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/excel-export.js"></script>
  <script
    src="<?= htmlspecialchars($basePath) ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/app.js') ?>"></script>
</body>

</html>
