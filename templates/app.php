<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $user  ['username', 'display_name', 'email', 'auth_source'] */
$basePath = $basePath ?? '';
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
    window.APP_IS_ADMIN = <?= !empty($user['is_admin']) ? 'true' : 'false' ?>;
    window.APP_ALLOWED_PAGES = <?= json_encode($allowedPages ?? [], JSON_UNESCAPED_UNICODE) ?>;
  </script>
</head>

<body>

<?php
  $headerSub   = '<div id="brandDateSub" class="brand-date-sub"></div>';
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
            <div class="filter-item" style="flex-basis:100%">
              <label class="filter-label" for="fDislocationWagonNo">№ вагона</label>
              <input class="filter-input" type="text" id="fDislocationWagonNo"
                placeholder="Номер вагона1; Номер вагона2; ...">
            </div>
            <div class="filter-item">
              <label class="filter-label" for="fDislocationCargo">Груз</label>
              <select class="filter-input" id="fDislocationCargo">
                <option value="">— Все —</option>
              </select>
            </div>
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="btnDislocationApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnDislocationReset">Сбросить</button>
            </div>
          </div>
        </div>

        <div id="disl-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Сводная дислокация <?= htmlspecialchars($appName) ?></span>
                <span class="table-sub" id="mainTableSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="mainTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="mainTable"></table>
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
            <div id="dislExtTable"></div>
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
            <div class="filter-item" style="flex-basis:100%">
              <label class="filter-label" for="fApproachWagonNo">№ вагона</label>
              <input class="filter-input" type="text" id="fApproachWagonNo"
                placeholder="Номер вагона1; Номер вагона2; ...">
            </div>
            <div class="filter-item">
              <label class="filter-label" for="fApproachCargo">Груз</label>
              <select class="filter-input" id="fApproachCargo">
                <option value="">— Все —</option>
              </select>
            </div>
            <!-- <div class="filter-item">
              <label class="filter-label" for="fApproachPrevCargo">Ранее выгружен</label>
              <select class="filter-input" id="fApproachPrevCargo">
                <option value="">— Все —</option>
              </select>
            </div> -->
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="btnApproachApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnApproachReset">Сбросить</button>
            </div>
          </div>
        </div>

        <div id="approach-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Подход вагонов к ст.Углеуральская (сводная)</span>
                <span class="table-sub" id="approachSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="approachSumTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="approachSumTable"></table>
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
            <div id="approachDetTable"></div>
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
            <div class="filter-item" style="flex-basis:100%">
              <label class="filter-label" for="fDepartureWagonNo">№ вагона</label>
              <input class="filter-input" type="text" id="fDepartureWagonNo"
                placeholder="Номер вагона1; Номер вагона2; ...">
            </div>
            <div class="filter-item">
              <label class="filter-label" for="fDepartureCargo">Груз</label>
              <select class="filter-input" id="fDepartureCargo">
                <option value="">— Все —</option>
              </select>
            </div>
            <div class="filter-item">
              <label class="filter-label" for="fDestStation">Ст. назначения</label>
              <select class="filter-input" id="fDestStation">
                <option value="">— Все —</option>
              </select>
            </div>
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="btnDepartureApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnDepartureReset">Сбросить</button>
            </div>
          </div>
        </div>
        <div id="departure-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Отправление вагонов со ст.Углеуральская — сводная</span>
                <span class="table-sub" id="departureSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-toggle-table="departureSumTable">Свернуть все</button>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="departureSumTable"></table>
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
            <div id="departureDetTable"></div>
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
            <div class="filter-item" style="flex-basis:100%">
              <label class="filter-label" for="fLoadingWagonNo">№ вагона</label>
              <input class="filter-input" type="text" id="fLoadingWagonNo"
                placeholder="Номер вагона1; Номер вагона2; ...">
            </div>
            <div class="filter-item">
              <label class="filter-label" for="fLoadingCargo">Груз</label>
              <select class="filter-input" id="fLoadingCargo">
                <option value="">— Все —</option>
              </select>
            </div>
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="btnLoadingApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnLoadingReset">Сбросить</button>
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
              <table class="data-table" id="loadingSumTable"></table>
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
            <div id="loadingDetTable"></div>
          </section>
        </div>
      </div>

      <!-- Простои -->
      <div id="panel-downtime" class="tab-panel">
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="downtime-summary">Сводная по станциям</button>
          <button class="inner-tab" data-inner="downtime-detail">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-item" style="flex-basis:100%">
              <label class="filter-label" for="fDowntimeWagonNo">№ вагона</label>
              <input class="filter-input" type="text" id="fDowntimeWagonNo"
                placeholder="Номер вагона1; Номер вагона2; ...">
            </div>
            <div class="filter-item">
              <label class="filter-label">Ст. назначения</label>
              <select class="filter-select" id="fDowntimeDestStation" style="width:220px">
                <option value="">— Все —</option>
              </select>
            </div>
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="btnDowntimeApply">Применить</button>
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
              <table class="data-table" id="downtimeSumTable"></table>
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
            <div id="downtimeDetTable"></div>
          </section>
        </div>
      </div>

      <!-- Сырьё -->
      <div id="panel-raw-material" class="tab-panel">
        <div class="kpi-grid" id="rawMetrics" style="margin-bottom:16px"></div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="raw-summary">Сводная по грузам</button>
          <button class="inner-tab" data-inner="raw-detail">Подробная</button>
        </div>
        <div class="filters-bar">
          <div class="filters-inner">
            <div class="filter-item" style="flex-basis:100%">
              <label class="filter-label" for="fRawWagonNo">№ вагона</label>
              <input class="filter-input" type="text" id="fRawWagonNo" placeholder="Номер вагона1; Номер вагона2; ...">
            </div>
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="btnRawApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnRawReset">Сбросить</button>
            </div>
          </div>
        </div>
        <div id="raw-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Сырьё — гружёные вагоны</span>
                <span class="table-sub" id="rawSumSub"></span>
              </div>
              <div class="table-acts"></div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="rawSumTable"></table>
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
            <div id="rawDetTable"></div>
          </section>
        </div>
      </div>

      <!-- Анализ за период -->
      <div id="panel-analysis-period" class="tab-panel">
        <div class="filters-bar">
          <div class="filters-inner" style="flex-wrap:wrap;gap:8px 16px">
            <div class="filter-item" style="flex-basis:100%">
              <label class="filter-label">№ вагона</label>
              <input class="filter-input" type="text" id="fAnalysisPeriodWagonNo"
                placeholder="Номер вагона1; Номер вагона2; ..."">
            </div>
            <div class=" filter-item" style="flex-basis:100%">
              <label class="filter-label" for="fAnalysisPeriodCargo">Груз</label>
              <select class="filter-input" id="fAnalysisPeriodCargo">
                <option value="">— Все —</option>
              </select>
            </div>
            <div class=" filter-item">
              <label class="filter-label">Дата операции с</label>
              <input class="filter-input" type="date" id="fAnalysisPeriodDateFrom" value="<?= date('Y-m-01') ?>"
                style="width:145px">
            </div>
            <div class="filter-item">
              <label class="filter-label">по</label>
              <input class="filter-input" type="date" id="fAnalysisPeriodDateTo" value="<?= date('Y-m-d') ?>"
                style="width:145px">
            </div>
            <div class="filter-actions" style="flex-basis:100%">
              <button class="btn btn-primary btn-sm" id="btnAnalysisPeriodApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnAnalysisPeriodReset">Сбросить</button>
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
            <div id="analysisPeriodDetTable"></div>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/detail-contexts.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/app.js"></script>
</body>

</html>