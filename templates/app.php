<?php
/** @var string $appName */
/** @var array  $user  ['username', 'display_name', 'email', 'auth_source'] */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Дислокация</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=PT+Sans:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <div class="brand">
      <div class="brand-icon">
        <svg width="28" height="28" viewBox="0 0 30 30" fill="none">
          <rect x="1" y="10" width="28" height="12" rx="2" fill="currentColor" opacity=".9"/>
          <circle cx="7.5"  cy="24" r="3" fill="currentColor"/>
          <circle cx="22.5" cy="24" r="3" fill="currentColor"/>
          <rect x="6"  y="7" width="7" height="5" rx="1" fill="currentColor" opacity=".5"/>
          <rect x="17" y="7" width="7" height="5" rx="1" fill="currentColor" opacity=".5"/>
        </svg>
      </div>
      <div class="brand-text">
        <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
        <div class="brand-sub">Дислокация парка вагонов</div>
      </div>
    </div>
    <div class="header-meta">
      <span class="meta-update">
        <span class="meta-label">Обновлено: </span>
        <span id="headerDate">—</span>
      </span>
      <span class="meta-badge">РЖД</span>
      <div class="user-info">
        <span class="user-name" title="<?= htmlspecialchars($user['auth_source'] ?? '') ?>">
          <?= htmlspecialchars($user['display_name'] ?? $user['username']) ?>
        </span>
        <form method="POST" action="/logout" style="display:inline">
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
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:6px;padding:10px 16px;margin-bottom:12px">
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

    <?php
    $placeholders = [
      'arrived'      => 'Прибыло за сутки',
      'trains'       => 'Бросание поездов',
      'analysis'     => 'Анализ за период',
      'recipients'   => 'Вагоны у получателя',
      'downtime'     => 'Простои',
      'downtime-sum' => 'Простои (Сводный)',
      'turnover'     => 'Оборот',
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
<script src="/assets/js/app.js"></script>
</body>
</html>
