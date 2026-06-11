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
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=PT+Sans:ital,wght@0,400;0,700;1,400&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <script>window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';</script>
</head>

<body>

  <header class="site-header">
    <div class="header-inner">
      <div class="brand">
        <div class="brand-icon">
        </div>
        <div class="brand-text">
          <div class="brand-name"><?= htmlspecialchars($appName) ?></div>

        </div>
      </div>
      <div class="header-meta">
        <span class="meta-update">
          <span class="meta-label">Обновлено: </span>
          <span id="headerDate">—</span>
        </span>
        <div class="user-info">
          <span class="user-name" title="<?= htmlspecialchars($user['auth_source'] ?? '') ?>">
            <?= htmlspecialchars($user['display_name'] ?? $user['username']) ?>
          </span>
          <form method="POST" action="<?= htmlspecialchars($basePath) ?>/logout" style="display:inline">
            <button type="submit" class="btn btn-ghost btn-sm">Выйти</button>
          </form>
        </div>
      </div>
    </div>
  </header>

  <div class="app-body">

    <aside class="sidebar" id="sidebar"></aside>

    <main class="main-content">

      <!-- Dashboard -->
      <div id="panel-dashboard" class="tab-panel active">
        <div style="margin-bottom:16px">
          <div style="font-size:20px;font-weight:700;letter-spacing:-.02em">Dashboard</div>
          <div style="font-size:12px;color:#9DA5B0;margin-top:3px" id="dashboardSub">Загрузка...</div>
        </div>
        <div class="kpi-grid" id="kpiGrid"></div>
        <section class="analytics-grid">
          <div class="chart-card">
            <div class="chart-title">Распределение по типам парка</div>
            <div id="sectionsChart"></div>
          </div>
          <div class="chart-card">
            <div class="chart-title">Цистерны / Прочие</div>
            <div id="typesChart"></div>
          </div>
        </section>
      </div>

      <!-- Дислокация -->
      <div id="panel-dislocation" class="tab-panel">
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="disl-summary">Сводная дислокация</button>
          <button class="inner-tab" data-inner="disl-extended">Расширенная</button>
        </div>

        <div id="disl-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Сводная дислокация <?= htmlspecialchars($appName) ?></span>
                <span class="table-sub" id="mainTableSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-collapse-table="mainTable">Свернуть все</button>
                <button class="btn btn-ghost btn-sm" data-expand-table="mainTable">Отобразить все</button>
                <button class="btn btn-ghost btn-sm" id="btnExportCSV">↓ CSV</button>
              </div>
            </div>
            <div style="background:var(--surface);border-bottom:1px solid var(--border);padding:10px 16px">
              <div class="filters-inner">
                <div class="filter-item">
                  <label class="filter-label" for="fReportDt">Справка</label>
                  <select class="filter-input" id="fReportDt" style="min-width:200px">
                    <option value="">— Последняя —</option>
                  </select>
                </div>
                <div class="filter-actions">
                  <button class="btn btn-primary btn-sm" id="btnApply">Применить</button>
                  <button class="btn btn-ghost btn-sm" id="btnReset">Сбросить</button>
                </div>
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
              <div class="table-info"><span class="table-title">Расширенная дислокация</span></div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="dislExtTable"></table>
            </div>
          </section>
        </div>
      </div>

      <!-- Подход -->
      <div id="panel-approach" class="tab-panel">

        <!-- Метрики по дорогам -->
        <div class="kpi-grid" id="approachMetrics" style="margin-bottom:16px"></div>

        <!-- Фильтры -->
        <div
          style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:10px 16px;margin-bottom:12px">
          <div class="filters-inner">
            <div class="filter-item">
              <label class="filter-label" for="fApproachCargo">Груз</label>
              <select class="filter-input" id="fApproachCargo" style="min-width:200px">
                <option value="">— Все грузы —</option>
              </select>
            </div>
            <div class="filter-item">
              <label class="filter-label" for="fApproachPrevCargo">Ранее выгруженный</label>
              <select class="filter-input" id="fApproachPrevCargo" style="min-width:200px">
                <option value="">— Все —</option>
              </select>
            </div>
            <div class="filter-actions">
              <button class="btn btn-primary btn-sm" id="btnApproachApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnApproachReset">Сбросить</button>
            </div>
          </div>
        </div>

        <!-- Внутренние вкладки -->
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="approach-summary">Сводная (по дорогам)</button>
          <button class="inner-tab" data-inner="approach-detail">Список вагонов</button>
        </div>

        <div id="approach-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Подход вагонов — сводная</span>
                <span class="table-sub" id="approachSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-collapse-table="approachSumTable">Свернуть все</button>
                <button class="btn btn-ghost btn-sm" data-expand-table="approachSumTable">Отобразить все</button>
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
                <span class="table-title">Список вагонов в подходе</span>
                <span class="table-sub" id="approachDetSub"></span>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="approachDetTable"></table>
            </div>
          </section>
        </div>

      </div>

      <!-- Отправление вагонов -->
      <div id="panel-departure" class="tab-panel">
        <div class="kpi-grid" id="departureMetrics" style="margin-bottom:16px"></div>
        <div
          style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:10px 16px;margin-bottom:12px">
          <div class="filters-inner">
            <div class="filter-item">
              <label class="filter-label" for="fDepartureCargo">Груз</label>
              <select class="filter-input" id="fDepartureCargo" style="min-width:200px">
                <option value="">— Все грузы —</option>
              </select>
            </div>
            <div class="filter-actions">
              <button class="btn btn-primary btn-sm" id="btnDepartureApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnDepartureReset">Сбросить</button>
            </div>
          </div>
        </div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="departure-summary">Сводная (по дорогам)</button>
          <button class="inner-tab" data-inner="departure-detail">Список вагонов</button>
        </div>
        <div id="departure-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Отправление вагонов — сводная</span>
                <span class="table-sub" id="departureSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-collapse-table="departureSumTable">Свернуть все</button>
                <button class="btn btn-ghost btn-sm" data-expand-table="departureSumTable">Отобразить все</button>
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
            </div>
            <div class="table-scroll">
              <table class="data-table" id="departureDetTable"></table>
            </div>
          </section>
        </div>
      </div>

      <!-- Погрузка -->
      <div id="panel-loading" class="tab-panel">
        <div class="kpi-grid" id="loadingMetrics" style="margin-bottom:16px"></div>
        <div
          style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:10px 16px;margin-bottom:12px">
          <div class="filters-inner">
            <div class="filter-item">
              <label class="filter-label" for="fLoadingCargo">Груз</label>
              <select class="filter-input" id="fLoadingCargo" style="min-width:200px">
                <option value="">— Все грузы —</option>
              </select>
            </div>
            <div class="filter-actions">
              <button class="btn btn-primary btn-sm" id="btnLoadingApply">Применить</button>
              <button class="btn btn-ghost btn-sm" id="btnLoadingReset">Сбросить</button>
            </div>
          </div>
        </div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="loading-summary">Сводная (по станциям)</button>
          <button class="inner-tab" data-inner="loading-detail">Список вагонов</button>
        </div>
        <div id="loading-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Погруженные вагоны — сводная</span>
                <span class="table-sub" id="loadingSumSub"></span>
              </div>
              <div class="table-acts">
                <button class="btn btn-ghost btn-sm" data-collapse-table="loadingSumTable">Свернуть все</button>
                <button class="btn btn-ghost btn-sm" data-expand-table="loadingSumTable">Отобразить все</button>
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
            </div>
            <div class="table-scroll">
              <table class="data-table" id="loadingDetTable"></table>
            </div>
          </section>
        </div>
      </div>

      <!-- Простои -->
      <div id="panel-downtime" class="tab-panel">
        <div
          style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:10px 16px;margin-bottom:12px">
          <div class="filters-inner">
            <div class="filter-item">
              <label class="filter-label" for="fDowntimeMinDays">Минимум суток</label>
              <select class="filter-input" id="fDowntimeMinDays">
                <option value="1">≥ 1 сут.</option>
                <option value="2">≥ 2 сут.</option>
                <option value="3">≥ 3 сут.</option>
                <option value="5">≥ 5 сут.</option>
                <option value="7">≥ 7 сут.</option>
              </select>
            </div>
            <div class="filter-actions">
              <button class="btn btn-primary btn-sm" id="btnDowntimeApply">Применить</button>
            </div>
          </div>
        </div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="downtime-summary">Сводная по станциям</button>
          <button class="inner-tab" data-inner="downtime-detail">Список вагонов</button>
        </div>
        <div id="downtime-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Простои — сводная</span>
                <span class="table-sub" id="downtimeSumSub"></span>
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
            </div>
            <div class="table-scroll">
              <table class="data-table" id="downtimeDetTable"></table>
            </div>
          </section>
        </div>
      </div>

      <!-- Сырьё -->
      <div id="panel-raw-material" class="tab-panel">
        <div class="kpi-grid" id="rawMetrics" style="margin-bottom:16px"></div>
        <div class="inner-tabs">
          <button class="inner-tab active" data-inner="raw-summary">По грузам</button>
          <button class="inner-tab" data-inner="raw-detail">Список вагонов</button>
        </div>
        <div id="raw-summary" class="inner-panel active">
          <section class="table-section">
            <div class="table-toolbar">
              <div class="table-info">
                <span class="table-title">Сырьё — гружёные вагоны</span>
                <span class="table-sub" id="rawSumSub"></span>
              </div>
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
                <span class="table-title">Список вагонов с сырьём</span>
                <span class="table-sub" id="rawDetSub"></span>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table" id="rawDetTable"></table>
            </div>
          </section>
        </div>
      </div>

      <?php
      $placeholders = [
        'arrived' => 'Прибыло за сутки',
        'trains' => 'Бросание поездов',
        'analysis' => 'Анализ за период',
        'recipients' => 'Вагоны у получателя',
        'downtime-sum' => 'Простои (Сводный)',
        'turnover' => 'Оборот',
      ];
      foreach ($placeholders as $id => $title):
        ?>
        <div id="panel-<?= htmlspecialchars($id) ?>" class="tab-panel">
          <div class="placeholder-panel">
            <div class="placeholder-icon">&#9881;</div>
            <div class="placeholder-title"><?= htmlspecialchars($title) ?></div>
            <div class="placeholder-sub">Раздел в разработке</div>
          </div>
        </div>
      <?php endforeach; ?>

    </main>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/app.js"></script>
</body>

</html>