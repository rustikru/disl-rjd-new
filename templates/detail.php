<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $user  ['username', 'display_name', 'auth_source'] */
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Детализация</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <script>
    window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';
    window.APP_CAN_OPEN_WAGON = <?= \App\Services\Auth\Authorization::isSuper($user) || in_array('wagon-card', (array) ($user['allowed_pages'] ?? []), true) ? 'true' : 'false' ?>;
    window.APP_CAN_OPEN_MAP = <?= \App\Services\Auth\Authorization::isSuper($user) || in_array('maps', (array) ($user['allowed_pages'] ?? []), true) ? 'true' : 'false' ?>;
    window.APP_CSRF = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';
    window.APP_REPORT_DEFINITIONS = <?= json_encode($reportDefinitions ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <style>
    .detail-page-body {
      padding: 16px 20px 40px;
      max-width: 100%;
    }

    #detailTable {
      overflow: hidden;
    }

    .detail-tabs-bar {
      display: flex;
      gap: 2px;
      border-bottom: 2px solid var(--border);
      margin-bottom: 12px;
    }

    .detail-map-actions {
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 10px;
    }

    .detail-map-link {
      display: flex;
      align-items: center;
      justify-content: center;
      box-sizing: border-box;
      width: 100%;
      min-height: 54px;
      border: 1px solid var(--border);
      border-radius: 10px;
      background: var(--surface);
      cursor: pointer;
      transition: border-color .15s, background .15s;
    }

    .detail-map-link:hover {
      border-color: var(--accent);
      background: var(--hover-green);
    }

    .detail-map-link img {
      width: 34px;
      height: 34px;
      object-fit: contain;
    }

    .detail-tab-btn {
      padding: 8px 16px;
      font-size: 12px;
      font-weight: 500;
      border: none;
      background: none;
      color: var(--text-2);
      cursor: pointer;
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      border-radius: 6px 6px 0 0;
      transition: color .15s, border-color .15s;
      white-space: nowrap;
    }

    .detail-tab-btn:hover {
      color: var(--text-1);
      background: var(--hover-green);
    }

    .detail-tab-btn.active {
      color: var(--accent);
      border-bottom-color: var(--accent);
      font-weight: 600;
    }
  </style>
</head>

<body>

  <?php
  $headerSub = '<div id="brandDateSub" class="brand-date-sub"></div>';
  $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" id="backBtn" title="Назад" aria-label="Назад"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></a>';
  $headerRight = '';
  include __DIR__ . '/partials/header.php';
  ?>

  <div class="detail-page-body">

    <section class="table-section">
      <div class="table-toolbar">
        <div class="table-info">
          <span class="table-title" id="detailTitle">Загрузка...</span>
          <span class="table-sub" id="detailSub"></span>
        </div>
        <div class="table-acts">
          <button class="btn btn-ghost btn-sm" id="btnResetLayout" type="button" hidden>Сбросить настройки таблицы</button>
          <button class="btn btn-ghost btn-sm" id="btnDetailCSV">Выгрузить в Excel</button>
        </div>
      </div>
      <div class="detail-tabs-bar" id="detailTabsBar" style="display:none"></div>
      <div id="detailTable"></div>
    </section>

  </div>

  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
  <script
    src="<?= htmlspecialchars($basePath) ?>/assets/js/detail-contexts.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/detail-contexts.js') ?>"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/csv-export.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/excel-export.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/sortable.min.js"></script>
  <script>
    'use strict';

    var BASE = window.APP_BASE || '';

    // Вложенная детализация возвращается к предыдущей; первая закрывает вкладку.
    document.getElementById('backBtn').addEventListener('click', function (event) {
      event.preventDefault();
      try {
        var previous = new URL(document.referrer);
        if (previous.origin === window.location.origin && previous.pathname === window.location.pathname) {
          window.history.back();
          return;
        }
      } catch (error) { }
      window.close();
      setTimeout(function () {
        if (!window.closed) window.location.replace(BASE + '/');
      }, 200);
    });

    function esc(str) {
      if (!str && str !== 0) return '';
      return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* Данные CSV обновляются при каждом showTable. */
    var _vtAllData = [], _vtFiltered = [], _vtCols = [], _vtAllCols = [], _vtInline = {};
    var _vtLayout = null, _vtReportCode = '';
    var _vtSortState = { key: null, dir: null };

    function orderColumns(cols, layout) {
      if (!layout || !Array.isArray(layout.columns)) return cols.slice();
      var byKey = {};
      cols.forEach(function (column) { byKey[column.key] = column; });
      var ordered = [];
      layout.columns.forEach(function (item) {
        if (byKey[item.key]) {
          if (Number(item.width) >= 70 && Number(item.width) <= 800) byKey[item.key].width = Number(item.width);
          else delete byKey[item.key].width;
          ordered.push(byKey[item.key]);
          delete byKey[item.key];
        }
      });
      return ordered.concat(Object.keys(byKey).map(function (key) { return byKey[key]; }));
    }

    function loadTableLayout(reportCode) {
      return $.getJSON(BASE + '/api/table-layout/' + encodeURIComponent(reportCode))
        .done(function (layout) {
          _vtLayout = layout;
          _vtAllCols = orderColumns(_vtAllCols, layout);
          $('#btnResetLayout').prop('hidden', false);
        })
        .fail(function () { _vtLayout = null; });
    }

    function saveTableLayout(reportCode, cols) {
      $.ajax({
        url: BASE + '/api/table-layout/' + encodeURIComponent(reportCode),
        method: 'POST',
        data: {
          csrf_token: window.APP_CSRF || '',
          columns: JSON.stringify(cols.map(function (column) { return column.key; })),
        },
      }).done(function (layout) {
        _vtLayout = layout;
        _vtAllCols = orderColumns(_vtAllCols, layout);
      });
    }

    function saveTableColumnWidths(reportCode, widths) {
      $.ajax({
        url: BASE + '/api/table-layout/' + encodeURIComponent(reportCode),
        method: 'POST',
        data: { csrf_token: window.APP_CSRF || '', widths: JSON.stringify(widths) },
      }).done(function (layout) {
        _vtLayout = layout;
        _vtAllCols = orderColumns(_vtAllCols, layout);
        $('#btnResetLayout').prop('hidden', false);
      }).fail(function () {
        $('#detailSub').text('Не удалось сохранить ширину столбца');
      });
    }

    $(function () {
      var params = new URLSearchParams(window.location.search);

      function renderGroupedKpi(data, countLinks) {
        var config = data.group_config || {};
        var summary = data.summary || { total: 0, loaded: 0, empty: 0 };
        var rows = data.rows || [];
        var summaryItems = [
          { label: 'Всего вагонов', value: summary.total, filter: '' },
          { label: 'Гружёные', value: summary.loaded, filter: 'гр.' },
          { label: 'Порожние', value: summary.empty, filter: 'пор.' }
        ];
        if (config.profile === 'abandoned') {
          summaryItems.unshift({ label: 'Брошенные поезда', value: summary.trains || 0, filter: '' });
        }
        var mapHref = detailMapUrl(config);
        var html = '<div class="train-groups-layout user-kpi-groups-layout"><aside class="train-summary-panel">';
        html += '<div class="train-section-heading"><strong>Итоги</strong><span>' + Number(summary.total || 0).toLocaleString('ru-RU') + ' вагонов</span></div>';
        if (mapHref) {
          html += '<div class="detail-map-actions"><a class="detail-map-link" href="' + esc(mapHref) + '" target="_blank" rel="noopener noreferrer" title="Показать на карте" aria-label="Показать на карте"><img src="' + esc(BASE + '/assets/img/map-route.png') + '" alt=""></a></div>';
        }
        html += '<div class="train-summary">';
        summaryItems.forEach(function (item) {
          var href = countLinks(null, { label: item.label, key: item.filter ? (item.filter === 'гр.' ? 'loaded' : 'empty_count') : 'total', filters: item.filter ? { cargo_state: item.filter } : {} });
          html += '<a class="train-summary-card" href="' + esc(href) + '"><span class="train-summary-label">' + esc(item.label) + '</span><strong class="train-summary-value">' + Number(item.value || 0).toLocaleString('ru-RU') + '</strong><span class="train-summary-action">Открыть</span></a>';
        });
        html += '</div>';
        html += '</aside><section class="train-groups-panel">';
        html += '<div class="train-section-heading"><strong>' + esc(config.title || 'Группы') + '</strong></div>';
        if (config.profile === 'abandoned') {
          var filterOptions = data.filter_options || {};
          var selectedSource = params.get('abandoned_source') || '';
          var selectedStations = [];
          try { selectedStations = JSON.parse(params.get('abandoned_station') || '[]'); } catch (e) { selectedStations = []; }
          if (!Array.isArray(selectedStations)) selectedStations = [];
          var selectedIdle = params.get('abandoned_idle_days') || '';
          var selectedSearch = params.get('abandoned_search') || '';
          html += '<form class="filters-bar user-kpi-detail-filters" id="abandonedFilters"><div class="filters-inner">';
          html += '<div class="filter-item filter-item--multi"><span class="filter-label">Станция</span><select name="abandoned_station" multiple>';
          (filterOptions.stations || []).forEach(function (station) {
            html += '<option value="' + esc(station) + '"' + (selectedStations.indexOf(station) >= 0 ? ' selected' : '') + '>' + esc(station) + '</option>';
          });
          html += '</select></div>';
          html += '<div class="filter-item"><span class="filter-label">Источник</span><select class="filter-select" name="abandoned_source"><option value="">Подход и отправление</option><option value="Подход"' + (selectedSource === 'Подход' ? ' selected' : '') + '>Подход</option><option value="Отправка"' + (selectedSource === 'Отправка' ? ' selected' : '') + '>Отправление</option></select></div>';
          html += '<div class="filter-item"><span class="filter-label">Простой не менее, сут.</span><input class="filter-input" name="abandoned_idle_days" type="number" min="0" max="3650" value="' + esc(selectedIdle) + '"></div>';
          html += '<div class="filter-item filter-item--wagon"><span class="filter-label">Поезд или вагон</span><input class="filter-input" name="abandoned_search" type="text" value="' + esc(selectedSearch) + '" placeholder="Индекс, номер поезда или вагона"></div>';
          html += '<div class="filter-actions"><button class="btn btn-primary btn-sm" type="submit">Применить</button><a class="btn btn-ghost btn-sm" href="' + esc(window.location.pathname + '?' + (function(){var q=new URLSearchParams(params); ['abandoned_source','abandoned_station','abandoned_idle_days','abandoned_search'].forEach(function(k){q.delete(k);}); return q.toString();})()) + '">Сбросить</a></div></div></form>';
          html += '<div class="train-groups-toggle-row"><button type="button" class="btn btn-ghost btn-sm js-train-groups-toggle">Отобразить все</button></div>';
        }
        html += '<div class="user-kpi-index-list">';
        rows.forEach(function (row, index) {
          var value = row.group_value == null || row.group_value === '' ? 'Не указано' : row.group_value;
          var groupTitle = (config.group_prefix || '') + value;
          var routeFrom = Number(row.route_depart_station_count || 0) > 1 ? 'Несколько записей' : (row.route_depart_station || '');
          var routeTo = Number(row.route_dest_station_count || 0) > 1 ? 'Несколько записей' : (row.route_dest_station || '');
          var route = routeFrom && routeTo ? 'Маршрут: ' + routeFrom + ' → ' + routeTo : '';
          if (String(config.group_field || '').toLowerCase() === 'train_no' && row.group_index) {
            groupTitle += ' (' + row.group_index + ')';
          }
          var sections = config.sections || [{ label: config.count_label || 'вагонов', key: 'total', filters: {} }];
          html += '<article class="user-kpi-index-card"><div class="user-kpi-index-top">';
          var detailsOpen = false;
          html += '<button type="button" class="user-kpi-index-info' + (detailsOpen ? ' is-open' : '') + '" data-index="' + index + '" aria-expanded="' + (detailsOpen ? 'true' : 'false') + '">';
          html += '<span class="user-kpi-index-title">' + esc(groupTitle) + '</span><span class="user-kpi-index-route">' + esc(route) + '</span></button>';
          html += '<div class="user-kpi-system-counts">';
          sections.forEach(function (section) {
            if (section.value) {
              var value = row[section.key] == null || row[section.key] === '' ? '—' : row[section.key];
              html += '<div class="user-kpi-index-count"><strong>' + esc(value) + '</strong><span>' + esc(section.label) + '</span></div>';
              return;
            }
            var count = Number(row[section.key] || 0);
            html += '<a class="user-kpi-index-count" href="' + esc(countLinks(row, section)) + '" title="Открыть детализацию"><strong>' + count.toLocaleString('ru-RU') + '</strong><span>' + esc(section.label) + '</span></a>';
          });
          html += '</div></div><div class="user-kpi-index-details" data-index="' + index + '"' + (detailsOpen ? '' : ' hidden') + '>';
          var indexGroups = Array.isArray(row.train_index_groups) ? row.train_index_groups : [];
          if (indexGroups.length > 1) {
            html += '<div class="user-kpi-train-indexes"><span class="user-kpi-train-indexes-label">Группы по индексу поезда</span><div class="user-kpi-train-index-list">';
            indexGroups.forEach(function (group, groupIndex) {
              var indexValue = group.value == null || group.value === '' ? 'Не указан' : group.value;
              html += '<section class="user-kpi-train-index"><button type="button" class="user-kpi-train-index-toggle" data-card-index="' + index + '" data-group-index="' + groupIndex + '" aria-expanded="false"><strong>' + esc(indexValue) + '</strong><span>' + Number(group.total || 0).toLocaleString('ru-RU') + ' ваг.</span></button>';
              html += '<div class="user-kpi-train-index-details" data-card-index="' + index + '" data-group-index="' + groupIndex + '" hidden><div class="user-kpi-group-wagon-grid">';
              (config.fields || []).forEach(function (field) {
                if (field.key === 'train_index') return;
                var multiple = Number(group[field.key + '_count'] || 0) > 1;
                var fieldValue = multiple ? 'Несколько записей' : group[field.key];
                html += '<div class="user-kpi-group-wagon-field"><span>' + esc(field.label) + '</span><strong>' + esc(fieldValue == null || fieldValue === '' ? '—' : fieldValue) + '</strong></div>';
              });
              html += '</div></div></section>';
            });
            html += '</div></div>';
          } else {
            var detailRow = indexGroups.length === 1 ? indexGroups[0] : row;
            html += '<div class="user-kpi-group-wagon-grid">';
            (config.fields || []).forEach(function (field) {
              var fieldValue;
              if (field.key === 'train_index' && indexGroups.length === 1) {
                fieldValue = detailRow.value;
              } else {
                var multiple = Number(detailRow[field.key + '_count'] || 0) > 1;
                fieldValue = multiple ? 'Несколько записей' : detailRow[field.key];
              }
              html += '<div class="user-kpi-group-wagon-field"><span>' + esc(field.label) + '</span><strong>' + esc(fieldValue == null || fieldValue === '' ? '—' : fieldValue) + '</strong></div>';
            });
            html += '</div>';
          }
          html += '</div></article>';
        });
        if (!rows.length) html += '<div class="user-kpi-detail-empty">' + esc(config.empty_label || 'Нет данных') + '</div>';
        html += '</div></section></div>';
        $('#detailTable').html(html);
        $('#detailTable .js-train-groups-toggle').on('click', function () {
          var isCollapsed = $(this).text() === 'Отобразить все';
          $('#detailTable .user-kpi-index-details, #detailTable .user-kpi-train-index-details').prop('hidden', !isCollapsed);
          $('#detailTable .user-kpi-index-info, #detailTable .user-kpi-train-index-toggle').attr('aria-expanded', isCollapsed ? 'true' : 'false').toggleClass('is-open', isCollapsed);
          $(this).text(isCollapsed ? 'Свернуть все' : 'Отобразить все');
        });
        if (config.profile === 'abandoned') {
          var $station = $('#abandonedFilters select[name="abandoned_station"]');
          var $widget = $('<div class="filter-multiselect"><button type="button" class="filter-multiselect-button">— Все —</button><div class="filter-multiselect-options" hidden><input class="filter-multiselect-search" type="search" placeholder="Поиск..."><div class="filter-multiselect-list"></div></div></div>');
          var $list = $widget.find('.filter-multiselect-list');
          $station.find('option').each(function () {
            var option = this;
            var $label = $('<label><input type="checkbox"> <span></span></label>');
            $label.find('input').val(option.value).prop('checked', option.selected);
            $label.find('span').text(option.text);
            $list.append($label);
          });
          $station.after($widget).hide();
          var updateStationLabel = function () {
            var values = $list.find('input:checked').map(function () { return this.value; }).get();
            $station.val(values);
            $widget.find('.filter-multiselect-button').text(values.length ? 'Выбрано: ' + values.length : '— Все —');
          };
          $widget.find('.filter-multiselect-button').on('click', function () { $widget.find('.filter-multiselect-options').prop('hidden', !$widget.find('.filter-multiselect-options').prop('hidden')); });
          $widget.find('input[type="checkbox"]').on('change', updateStationLabel);
          $widget.find('.filter-multiselect-search').on('input', function () { var q = this.value.toLocaleLowerCase('ru-RU'); $list.children('label').each(function () { this.hidden = q && this.textContent.toLocaleLowerCase('ru-RU').indexOf(q) < 0; }); });
          updateStationLabel();
        }
        $('#abandonedFilters').on('submit', function (event) {
          event.preventDefault();
          var query = new URLSearchParams(params);
          var stationValues = $(this).find('select[name="abandoned_station"]').val() || [];
          new FormData(this).forEach(function (value, key) {
            if (key === 'abandoned_station') return;
            if (String(value).trim()) query.set(key, String(value).trim()); else query.delete(key);
          });
          if (stationValues.length) query.set('abandoned_station', JSON.stringify(stationValues)); else query.delete('abandoned_station');
          window.location.search = query.toString();
        });
        $('#detailTable').off('click.groupedKpi').on('click.groupedKpi', '.user-kpi-index-info', function () {
          var index = Number($(this).attr('data-index'));
          var $content = $('#detailTable .user-kpi-index-details[data-index="' + index + '"]');
          var isOpen = !$content.prop('hidden');
          $content.prop('hidden', isOpen);
          $(this).attr('aria-expanded', isOpen ? 'false' : 'true').toggleClass('is-open', !isOpen);
        });
        $('#detailTable').on('click.groupedKpi', '.user-kpi-train-index-toggle', function () {
          var cardIndex = Number($(this).attr('data-card-index'));
          var groupIndex = Number($(this).attr('data-group-index'));
          var $content = $('#detailTable .user-kpi-train-index-details[data-card-index="' + cardIndex + '"][data-group-index="' + groupIndex + '"]');
          var isOpen = !$content.prop('hidden');
          $content.prop('hidden', isOpen);
          $(this).attr('aria-expanded', isOpen ? 'false' : 'true').toggleClass('is-open', !isOpen);
        });
      }

      function detailMapUrl(config) {
        var groupField = String(config.group_field || '').toLowerCase();
        if (!window.APP_CAN_OPEN_MAP || ['train_no', 'train_index', 'wagon_type_code'].indexOf(groupField) < 0) return '';
        var query = new URLSearchParams(params);
        query.delete('train_groups');
        query.delete('kpi_view');
        query.delete('group_by');
        query.set('map_detail', 'Y');
        return BASE + '/maps?' + query.toString();
      }

      var userKpiId = params.get('kpi_id') || '';
      if (userKpiId && params.get('kpi_view') === 'GROUPED' && params.get('kpi_table') !== 'Y') {
        $('#detailTabsBar, #btnResetLayout, #btnDetailCSV').hide();
        $('#detailTitle').text('Детализация KPI');
        $('#detailSub').text('Загрузка...');


        var userKpiParams = new URLSearchParams();
        userKpiParams.set('kpi_id', userKpiId);

        // API проверяет доступ к исходному разделу, где размещена KPI-карточка.
        var kpiTypes = <?= json_encode(\App\Reports\Kpi\KpiTypeRegistry::clientConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        var kpiType = kpiTypes[params.get('kpi_type')] || {};
        var sourcePage = params.get('source_page') || kpiType.page || '';
        if (sourcePage) userKpiParams.set('source_page', sourcePage);

        if (params.has('report_dt')) userKpiParams.set('report_dt', params.get('report_dt'));
        if (params.has('cargo_state')) userKpiParams.set('cargo_state', params.get('cargo_state'));
        $.getJSON(BASE + '/api/dislocation/detail?' + userKpiParams.toString()).done(function (data) {
          $('#detailTitle').text(data.label || 'Детализация KPI');
          $('#detailSub').text('');
          $('.table-section').addClass('is-train-groups');
          var queryBase = new URLSearchParams(params);
          queryBase.delete('kpi_view');
          queryBase.delete('cargo_state');
          queryBase.delete('group_value');
          queryBase.delete('group_value_is_null');
          queryBase.set('ctx', sourcePage);
          queryBase.set('_endpoint', '/api/dislocation/detail');
          queryBase.set('kpi_table', 'Y');
          renderGroupedKpi(data, function (row, section) {
            var query = new URLSearchParams(queryBase);
            Object.keys(section.filters || {}).forEach(function (key) { query.set(key, section.filters[key]); });
            if (row) {
              if (row.group_value == null) query.set('group_value_is_null', 'Y');
              else query.set('group_value', row.group_value);
            }
            return BASE + '/detail?' + query.toString();
          });
        }).fail(function (xhr) {
          var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Не удалось загрузить детализацию KPI';
          $('#detailSub').text('');
          $('#detailTable').html('<div class="user-kpi-detail-empty">' + esc(message) + '</div>');
        });
        return;
      }

      var ctx = params.get('ctx') || '';
      _vtReportCode = ctx;
      var road = params.get('road') || '';
      var station = params.get('station') || '';
      var wagType = params.get('wagon_type') || '';
      var cargoState = params.get('cargo_state') || '';

      var ctxDef = null;
      var def = DETAIL_CONTEXTS[ctx];
      if (def) {
        var endpointOverride = params.get('_endpoint');
        ctxDef = {
          label: def.label,
          endpoint: BASE + (endpointOverride || def.endpoint),
          cols: def.cols,
          sort: def.sort || null,
        };
      }


      var bcpathRaw = params.get('_bcpath') || '';
      var bcpathParts = [];
      try { if (bcpathRaw) bcpathParts = JSON.parse(bcpathRaw); } catch (e) { }
      var titleParts = [];
      if (ctxDef) { titleParts.push(ctxDef.label); }
      if (road) { titleParts.push(road); }
      else if (bcpathParts.length) { bcpathParts.forEach(function (p) { if (p) titleParts.push(p); }); }
      if (station) { titleParts.push(station); }
      if (wagType) { titleParts.push(wagType); }
      if (cargoState) { titleParts.push(cargoState); }
      $('#detailTitle').text(titleParts.join(' › ') || 'Детализация');

      if (!ctxDef) {
        $('#detailTable').html('<div style="text-align:center;padding:40px;color:#9DA5B0">Неизвестный контекст</div>');
        return;
      }

      // Все параметры детализации заданы в WAGON_TABS[ctx].mapDetailParams .
      // Если в URL есть дополнительные параметры, они будут добавлены к запросу.
      var apiParams = new URLSearchParams();
      var skipKeys = { ctx: 1, _bcpath: 1, _endpoint: 1 };
      params.forEach(function (v, k) { if (!skipKeys[k] && v) { apiParams.set(k, v); } });
      apiParams.set('fields', ctxDef.cols.map(function (c) { return c.key; }).join(','));

      if (ctxDef.sort && !apiParams.has('sort')) {
        var sortArr = Array.isArray(ctxDef.sort) ? ctxDef.sort : [ctxDef.sort];
        sortArr = sortArr.filter(function (s) { return s && s.field; });
        if (sortArr.length) {
          apiParams.set('sort', sortArr.map(function (s) { return s.field; }).join(','));
          apiParams.set('sort_dir', sortArr.map(function (s) { return s.dir || 'asc'; }).join(','));
          var types = sortArr.map(function (s) { return s.type || ''; }).join(',');
          if (types.replace(/,/g, '')) apiParams.set('sort_type', types);
        }
      }

      // Вкладки drill-down (только если DETAIL_TABS определён и контекст их поддерживает)
      var allCols = ctxDef.cols
      _vtAllCols = allCols.slice()
      var hasTabs = typeof DETAIL_TABS !== 'undefined' && DETAIL_TABS.length > 0
      // visTabs — только вкладки, у которых есть хотя бы одно поле
      var visTabs = hasTabs ? DETAIL_TABS.filter(function (t) {
        return allCols.some(function (c) { return (c.tab || 'main') === t.key })
      }) : []
      var activeTab = visTabs.length ? visTabs[0].key : null

      function getTabCols(tab) {
        if (!tab) return allCols
        var tabCols = allCols.filter(function (c) { return (c.tab || 'main') === tab })
        if (tab === 'main') return tabCols
        var pinned = allCols.filter(function (c) { return c.pinned && (c.tab || 'main') !== tab })
        return pinned.concat(tabCols)
      }

      function renderTabs() {
        var bar = document.getElementById('detailTabsBar')
        if (!visTabs.length) return
        bar.style.display = 'flex'
        bar.innerHTML = visTabs.map(function (t) {
          return '<button class="detail-tab-btn' + (t.key === activeTab ? ' active' : '') +
            '" data-tab="' + t.key + '">' + esc(t.name) + '</button>'
        }).join('')
        bar.querySelectorAll('.detail-tab-btn').forEach(function (btn) {
          btn.addEventListener('click', function () {
            activeTab = this.getAttribute('data-tab')
            renderTabs()
            showTable(_vtAllData, orderColumns(getTabCols(activeTab), _vtLayout))
          })
        })
      }

      $('#detailSub').text('Загрузка...');

      var groupedRequest = ['train-groups', 'abandoned-trains', 'formed-trains'].indexOf(params.get('train_groups')) >= 0;
      var layoutRequest = groupedRequest ? $.when() : loadTableLayout(ctx);
      layoutRequest.always(function () {
      $.getJSON(ctxDef.endpoint + (apiParams.toString() ? '?' + apiParams.toString() : ''))
        .done(function (data) {
          if (data.group_config) {
            var config = data.group_config;
            $('#detailTitle').text(config.title);
            $('#detailSub').empty().hide();
            $('.table-section').addClass('is-train-groups');
            $('#detailTabsBar, #btnDetailCSV, #btnResetLayout').hide();
            renderGroupedKpi(data, function (row, section) {
              var query = new URLSearchParams(params);
              query.delete('train_groups');
              query.delete('group_by');
              query.delete('cargo_state');
              if (row) {
                query.set('group_by', config.group_field);
                query.set(config.group_field, row.group_value == null ? '' : row.group_value);
              }
              Object.keys(section.filters || {}).forEach(function (key) { query.set(key, section.filters[key]); });
              return BASE + '/detail?' + query.toString();
            });
            return;
          }
          var rows = data.rows || [];
          _vtSortState = { key: null, dir: null };
          $('#detailSub').text('Строк: ' + rows.length.toLocaleString('ru-RU'));
          showTable(rows, orderColumns(getTabCols(activeTab), _vtLayout));
          renderTabs();
        })
        .fail(function (jqXHR) {
          var status = jqXHR.status ? ' (' + jqXHR.status + ')' : '';
          var detail = '';
          try { var j = JSON.parse(jqXHR.responseText); detail = j.error || j.message || ''; }
          catch (e) { detail = jqXHR.responseText || ''; }
          var msg = 'Ошибка загрузки данных' + status + (detail ? ': ' + detail : '');
          $('#detailTable').html('<div style="text-align:center;padding:40px;color:#9DA5B0">' + esc(msg) + '</div>');
          $('#detailSub').text(msg);
        });
      });

      $('#btnResetLayout').on('click', function () {
        $.ajax({
          url: BASE + '/api/table-layout/' + encodeURIComponent(ctx) + '/reset',
          method: 'POST',
          data: { csrf_token: window.APP_CSRF || '' },
        }).always(function () {
          _vtLayout = null;
          _vtAllCols = allCols.slice();
          _vtAllCols.forEach(function (column) { delete column.width; });
          allCols.forEach(function (column) { delete column.width; });
          showTable(_vtAllData, getTabCols(activeTab));
        });
      });
    });

    function oracleMaskFmt(v, mask) {
      if (v == null || v === '') return '';
      var s = String(v);
      var d = null;
      var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2}):?(\d{2})?/);
      if (m) d = { DD: m[3], MM: m[2], YYYY: m[1], HH24: m[4], MI: m[5], SS: m[6] || '00' };
      if (!d) {
        m = s.match(/^(\d{2})\.(\d{2})\.(\d{4})[T ](\d{2}):(\d{2}):?(\d{2})?/);
        if (m) d = { DD: m[1], MM: m[2], YYYY: m[3], HH24: m[4], MI: m[5], SS: m[6] || '00' };
      }
      if (!d) return s;
      return mask.replace('HH24', d.HH24).replace('YYYY', d.YYYY)
        .replace('MM', d.MM).replace('DD', d.DD)
        .replace('MI', d.MI).replace('SS', d.SS);
    }

    function showTable(rows, cols) {
      var host = document.getElementById('detailTable');
      if (host._vtCleanup) host._vtCleanup();
      cols = cols.map(function (c) {
        if (c.formatData && !c.fmt) {
          var mask = c.formatData;
          return Object.assign({}, c, { fmt: function (v) { return oracleMaskFmt(v, mask); } });
        }
        return c;
      });
      _vtAllData = rows || [];
      _vtCols = cols;
      var filterState = {};
      _vtFiltered = sortRows(_vtAllData, cols);
      _vtInline.detailTable = { all: _vtAllData, filtered: _vtFiltered, cols: cols };

      function filterDisplayValue(column, row) {
        var value = row[column.key];
        if (column.fmt) value = column.fmt(value);
        return value == null || value === '' ? '(Пустые)' : String(value);
      }

      var ROW_H = 28;
      var BUFFER = 8;
      var measured = [];

      (function measureCols() {
        var cv = document.createElement('canvas');
        var ctx = cv.getContext('2d');
        var PAD = 20, MIN = 70, MAX = 320;
        ctx.font = 'bold 12px sans-serif';
        measured = cols.map(function (c) {
          return Math.max(MIN, Math.min(MAX, Math.ceil(ctx.measureText(c.label).width) + PAD));
        });
        ctx.font = '12px sans-serif';
        var sample = _vtAllData.length > 300 ? _vtAllData.slice(0, 300) : _vtAllData;
        sample.forEach(function (row) {
          cols.forEach(function (c, i) {
            var v = c.fmt ? c.fmt(row[c.key]) : (row[c.key] == null ? '' : String(row[c.key]));
            var w = Math.ceil(ctx.measureText(String(v == null ? '' : v)).width) + PAD;
            if (w > measured[i]) measured[i] = Math.min(MAX, w);
          });
        });
      })();
      var availW = document.getElementById('detailTable').offsetWidth || (window.innerWidth - 40);
      var savedTotal = 0, defaultTotal = 0;
      cols.forEach(function (column, index) {
        var savedWidth = Number(column.width);
        if (savedWidth >= 70 && savedWidth <= 800) savedTotal += savedWidth;
        else defaultTotal += measured[index];
      });
      var defaultScale = defaultTotal > 0 && availW - savedTotal > defaultTotal
        ? (availW - savedTotal) / defaultTotal : 1;
      var widths = measured.map(function (width, index) {
        var savedWidth = Number(cols[index].width);
        if (savedWidth >= 70 && savedWidth <= 800) return savedWidth;
        return Math.max(70, Math.min(800, Math.floor(width * defaultScale)));
      });
      var template = widths.map(function (width) { return width + 'px'; }).join(' ');
      var totalW = widths.reduce(function (sum, width) { return sum + width; }, 0);

      $('#detailTable').html(
        '<div class="vt-viewport" id="vtVp">' +
        '<div class="vt-content" style="width:' + totalW + 'px">' +
        '<div class="vt-sticky">' +
        '<div class="vt-head"   id="vtHead"   style="grid-template-columns:' + template + ';width:' + totalW + 'px"></div>' +
        '<div class="vt-filter" id="vtFilter" style="grid-template-columns:' + template + ';width:' + totalW + 'px"></div></div>' +
        '<div id="vtRows"></div>' +
        '</div>' +
        '</div>'
      );

      var hHtml = '', fHtml = '';
      cols.forEach(function (c) {
        var canSort = /^[a-zA-Z0-9_]+$/.test(c.key || '');
        var sortIcon = _vtSortState.key === c.key ? (_vtSortState.dir === 'asc' ? '↑' : '↓') : '';
        hHtml += '<div class="vt-th' + (c.pinned ? ' col-pinned' : '') + (c.meta ? ' col-meta' : '') + (canSort ? ' vt-th--sortable' : '') + '" data-key="' + esc(c.key) + '" title="' + (canSort ? 'Нажмите для сортировки' : (c.pinned ? 'Закреплённая колонка' : 'Перетащите, чтобы переместить колонку')) + '"><span class="vt-th-label">' + esc(c.label) + '</span><span class="vt-sort-indicator" aria-hidden="true">' + sortIcon + '</span><span class="vt-resize-handle" data-key="' + esc(c.key) + '" title="Изменить ширину столбца"></span></div>';
        fHtml += '<div class="vt-fc' + (c.pinned ? ' col-pinned' : '') + '"><button type="button" class="vt-filter-button" data-k="' + esc(c.key) + '" title="Фильтр по столбцу">Все ▾</button></div>';
      });
      document.getElementById('vtHead').innerHTML = hHtml;
      document.getElementById('vtFilter').innerHTML = fHtml;

      var head = document.getElementById('vtHead');
      var resizeMove = null;
      var resizeEnd = null;
      var resizeSaveTimer = null;
      head.addEventListener('mousedown', function (event) {
        var handle = event.target.closest('.vt-resize-handle');
        if (!handle || event.button !== 0) return;
        var key = handle.getAttribute('data-key');
        var index = cols.findIndex(function (column) { return column.key === key; });
        if (index < 0) return;
        event.preventDefault();
        event.stopPropagation();
        var startX = event.clientX;
        var startWidth = widths[index];
        head.classList.add('vt-head--resizing');
        resizeMove = function (moveEvent) {
          var nextWidth = Math.max(70, Math.min(800, Math.round(startWidth + moveEvent.clientX - startX)));
          widths[index] = nextWidth;
          template = widths.map(function (width) { return width + 'px'; }).join(' ');
          totalW = widths.reduce(function (sum, width) { return sum + width; }, 0);
          document.querySelectorAll('#vtHead, #vtFilter, #vtRows .vt-row').forEach(function (element) {
            element.style.gridTemplateColumns = template;
            element.style.width = totalW + 'px';
          });
          document.querySelector('#detailTable .vt-content').style.width = totalW + 'px';
        };
        resizeEnd = function () {
          document.removeEventListener('mousemove', resizeMove);
          document.removeEventListener('mouseup', resizeEnd);
          head.classList.remove('vt-head--resizing');
          resizeMove = resizeEnd = null;
          cols[index].width = widths[index];
          cols.forEach(function (column, columnIndex) {
            var allColumn = _vtAllCols.find(function (item) { return item.key === column.key; });
            if (allColumn) allColumn.width = widths[columnIndex];
          });
          var widthData = {};
          cols.forEach(function (column, columnIndex) { widthData[column.key] = widths[columnIndex]; });
          if (resizeSaveTimer) clearTimeout(resizeSaveTimer);
          resizeSaveTimer = setTimeout(function () {
            resizeSaveTimer = null;
            saveTableColumnWidths(_vtReportCode, widthData);
          }, 250);
        };
        document.addEventListener('mousemove', resizeMove);
        document.addEventListener('mouseup', resizeEnd);
      });

      head.addEventListener('dblclick', function (event) {
        var handle = event.target.closest('.vt-resize-handle');
        if (!handle) return;
        event.preventDefault();
        event.stopPropagation();
        var key = handle.getAttribute('data-key');
        var index = cols.findIndex(function (column) { return column.key === key; });
        if (index < 0) return;
        widths[index] = measured[index];
        cols[index].width = measured[index];
        template = widths.map(function (width) { return width + 'px'; }).join(' ');
        totalW = widths.reduce(function (sum, width) { return sum + width; }, 0);
        document.querySelectorAll('#vtHead, #vtFilter, #vtRows .vt-row').forEach(function (element) {
          element.style.gridTemplateColumns = template;
          element.style.width = totalW + 'px';
        });
        document.querySelector('#detailTable .vt-content').style.width = totalW + 'px';
        var allColumn = _vtAllCols.find(function (column) { return column.key === key; });
        if (allColumn) delete allColumn.width;
        delete cols[index].width;
        if (resizeSaveTimer) {
          clearTimeout(resizeSaveTimer);
          resizeSaveTimer = null;
        }
        var resetWidth = {}; resetWidth[key] = null;
        if (_vtLayout && Array.isArray(_vtLayout.columns)) {
          _vtLayout.columns.forEach(function (item) { if (item.key === key) item.width = null; });
        }
        saveTableColumnWidths(_vtReportCode, resetWidth);
      });

      if (typeof Sortable !== 'undefined') {
        head._sortable = Sortable.create(head, {
          animation: 150,
          draggable: '.vt-th:not(.col-pinned)',
          filter: '.col-pinned, .vt-resize-handle',
          preventOnFilter: false,
          forceFallback: false,
          ghostClass: 'vt-th--ghost',
          onStart: function () { head.classList.add('vt-head--dragging'); },
          onEnd: function () {
            head.classList.remove('vt-head--dragging');
            var keys = Array.prototype.map.call(head.querySelectorAll('.vt-th'), function (cell) { return cell.dataset.key; });
            var byKey = {};
            cols.forEach(function (column) { byKey[column.key] = column; });
            var reordered = keys.map(function (key) { return byKey[key]; }).filter(Boolean);
            saveTableLayout(_vtReportCode, reordered);
            setTimeout(function () { showTable(_vtAllData, reordered); }, 0);
          },
        });
      }

      function cellHtml(c, row) {
        var v = row[c.key];
        var display = (v !== null && v !== undefined && v !== '') ? v : '';
        if (c.fmt) display = c.fmt(v);
        var cls = 'vt-cell' + (c.pinned ? ' col-pinned' : '') + (c.meta ? ' col-meta' : '') + (c.right ? ' vt-right' : '');
        var style = '';
        if (c.danger) {
          var d = parseFloat(display) || 0;
          if (d >= 7) style = ' style="color:#E8392A;font-weight:700"';
          else if (d >= 3) style = ' style="color:#E8A530;font-weight:600"';
        }
        var content = esc(String(display));
        if ((c.key === 'wagon_no' || c.key === 'car_number') && /^\d{8}$/.test(String(v)) && window.APP_CAN_OPEN_WAGON) {
          content = '<a class="wagon-link" href="' + BASE + '/wagon?wagon_no=' + encodeURIComponent(v) + '" style="color:var(--accent);text-decoration:none" title="Карточка вагона">' + content + '</a>';
        }
        return '<div class="' + cls + '"' + style + '>' + content + '</div>';
      }

      var vp = document.getElementById('vtVp');
      var rowsEl = document.getElementById('vtRows');
      var lastFirst = -1, lastLast = -1;

      function render(force) {
        var scrollTop = vp.scrollTop;
        var total = _vtFiltered.length;
        var viewRows = Math.ceil(vp.clientHeight / ROW_H);
        var first = Math.max(0, Math.floor(scrollTop / ROW_H) - BUFFER);
        var last = Math.min(total, first + viewRows + BUFFER * 2);
        if (!force && first === lastFirst && last === lastLast) return;
        lastFirst = first; lastLast = last;

        if (!total) {
          rowsEl.style.paddingTop = '0';
          rowsEl.style.paddingBottom = '0';
          rowsEl.innerHTML = '<div class="vt-empty">Нет данных</div>';
          return;
        }
        var html = '';
        for (var i = first; i < last; i++) {
          html += '<div class="vt-row" style="grid-template-columns:' + template + ';width:' + totalW + 'px">';
          cols.forEach(function (c) { html += cellHtml(c, _vtFiltered[i]); });
          html += '</div>';
        }
        rowsEl.style.paddingTop = (first * ROW_H) + 'px';
        rowsEl.style.paddingBottom = ((total - last) * ROW_H) + 'px';
        rowsEl.innerHTML = html;
      }

      function getFilterValues(key, state) {
        var column = cols.find(function (item) { return item.key === key; });
        if (!column) return [];
        state = state || filterState;
        return Array.from(new Set(_vtAllData.filter(function (row) {
          return Object.keys(state).every(function (activeKey) {
            if (activeKey === key) return true;
            var activeColumn = cols.find(function (item) { return item.key === activeKey; });
            return activeColumn && state[activeKey].has(filterDisplayValue(activeColumn, row));
          });
        }).map(function (row) {
          return filterDisplayValue(column, row);
        }))).sort(function (a, b) {
          return a.localeCompare(b, 'ru', { numeric: true, sensitivity: 'base' });
        });
      }

      function updateFilterButtons() {
        document.querySelectorAll('#vtFilter .vt-filter-button').forEach(function (button) {
          var key = button.getAttribute('data-k');
          var selected = filterState[key];
          button.classList.toggle('is-active', !!selected);
          button.textContent = selected ? selected.size + ' выбрано ▾' : 'Все ▾';
        });
      }

      function normalizeColumnFilters(changedKey) {
        var changed = true;
        while (changed) {
          changed = false;
          var snapshot = {};
          Object.keys(filterState).forEach(function (key) { snapshot[key] = new Set(filterState[key]); });
          var nextState = {};
          Object.keys(snapshot).forEach(function (key) {
            if (key === changedKey) {
              nextState[key] = snapshot[key];
              return;
            }
            var available = new Set(getFilterValues(key, snapshot));
            var selected = new Set(Array.from(snapshot[key]).filter(function (value) { return available.has(value); }));
            if (selected.size || snapshot[key].size === 0) nextState[key] = selected;
            if (selected.size !== snapshot[key].size) changed = true;
          });
          if (Object.keys(nextState).length !== Object.keys(filterState).length) changed = true;
          filterState = nextState;
        }
        updateFilterButtons();
      }

      function sortValue(value, column) {
        if (value == null || value === '') return null;
        if (column.type === 'number' || typeof value === 'number') {
          var number = Number(String(value).replace(/\s/g, '').replace(',', '.'));
          return Number.isFinite(number) ? number : String(value).toLocaleLowerCase('ru');
        }
        if (column.type === 'date' || column.formatData) {
          var text = String(value);
          var match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?/);
          if (!match) match = text.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
          if (match) {
            var parts = match.length && match[1].length === 4
              ? [match[1], match[2], match[3], match[4] || '00', match[5] || '00', match[6] || '00']
              : [match[3], match[2], match[1], match[4] || '00', match[5] || '00', match[6] || '00'];
            return Number(parts.join(''));
          }
          var timestamp = Date.parse(text);
          return Number.isNaN(timestamp) ? text.toLocaleLowerCase('ru') : timestamp;
        }
        return String(value).toLocaleLowerCase('ru');
      }

      function sortRows(rows, columns) {
        if (!_vtSortState.key || !_vtSortState.dir) return rows.slice();
        var column = columns.find(function (item) {
          return item.key === _vtSortState.key && /^[a-zA-Z0-9_]+$/.test(item.key || '');
        });
        if (!column) return rows.slice();
        return rows.map(function (row, index) {
          return { row: row, index: index, value: sortValue(row[column.key], column) };
        }).sort(function (a, b) {
          if (a.value === null && b.value === null) return a.index - b.index;
          if (a.value === null) return 1;
          if (b.value === null) return -1;
          var result;
          if (typeof a.value === 'number' && typeof b.value === 'number') result = a.value - b.value;
          else result = String(a.value).localeCompare(String(b.value), 'ru', { numeric: true, sensitivity: 'base' });
          return result === 0 ? a.index - b.index : (_vtSortState.dir === 'desc' ? -result : result);
        }).map(function (item) { return item.row; });
      }

      function updateSortIndicators() {
        head.querySelectorAll('.vt-th').forEach(function (cell) {
          var isActive = cell.dataset.key === _vtSortState.key;
          var indicator = cell.querySelector('.vt-sort-indicator');
          cell.classList.toggle('is-sorted', isActive);
          if (indicator) indicator.textContent = isActive ? (_vtSortState.dir === 'asc' ? '↑' : '↓') : '';
        });
      }

      head.addEventListener('click', function (event) {
        if (event.target.closest('.vt-resize-handle')) return;
        var cell = event.target.closest('.vt-th--sortable');
        if (!cell) return;
        var key = cell.dataset.key;
        if (_vtSortState.key !== key) _vtSortState = { key: key, dir: 'asc' };
        else if (_vtSortState.dir === 'asc') _vtSortState.dir = 'desc';
        else _vtSortState = { key: null, dir: null };
        updateSortIndicators();
        applyColumnFilters();
        vp.scrollTop = 0;
        render(true);
      });

      function applyColumnFilters(changedKey) {
        if (changedKey) normalizeColumnFilters(changedKey);
        var activeKeys = Object.keys(filterState);
        var filteredRows = activeKeys.length ? _vtAllData.filter(function (row) {
          return activeKeys.every(function (key) {
            var column = cols.find(function (item) { return item.key === key; });
            return column && filterState[key].has(filterDisplayValue(column, row));
          });
        }) : _vtAllData.slice();
        _vtFiltered = sortRows(filteredRows, cols);
        _vtInline.detailTable.filtered = _vtFiltered;
        lastFirst = lastLast = -1;
        render(true);
        $('#detailSub').text('Строк: ' + _vtFiltered.length.toLocaleString('ru-RU') +
          (_vtFiltered.length < _vtAllData.length ? ' (отфильтровано из ' + _vtAllData.length.toLocaleString('ru-RU') + ')' : ''));
      }

      var filterPopup = null;
      function closeFilterPopup() {
        if (filterPopup) filterPopup.remove();
        filterPopup = null;
      }

      function openColumnFilter(button) {
        closeFilterPopup();
        var key = button.getAttribute('data-k');
        var column = cols.find(function (item) { return item.key === key; });
        if (!column) return;
        var values = getFilterValues(key);
        var draft = filterState[key]
          ? new Set(Array.from(filterState[key]).filter(function (value) { return values.indexOf(value) !== -1; }))
          : new Set(values);
        var popup = document.createElement('div');
        popup.className = 'vt-column-filter';
        popup.innerHTML = '<input class="vt-column-filter-search" type="search" placeholder="Поиск...">'
          + '<div class="vt-column-filter-tools"><button type="button" data-action="all">Выбрать все</button><button type="button" data-action="none">Снять все</button></div>'
          + '<div class="vt-column-filter-list"></div>'
          + '<div class="vt-column-filter-actions"><button type="button" class="btn btn-ghost btn-sm" data-action="cancel">Отмена</button><button type="button" class="btn btn-primary btn-sm" data-action="apply">Применить</button></div>';
        document.body.appendChild(popup);
        filterPopup = popup;
        var search = popup.querySelector('.vt-column-filter-search');
        var list = popup.querySelector('.vt-column-filter-list');

        function renderOptions() {
          var query = search.value.toLocaleLowerCase('ru-RU').trim();
          list.innerHTML = '';
          values.forEach(function (value) {
            if (query && value.toLocaleLowerCase('ru-RU').indexOf(query) < 0) return;
            var label = document.createElement('label');
            var check = document.createElement('input');
            var text = document.createElement('span');
            check.type = 'checkbox';
            check.checked = draft.has(value);
            check.addEventListener('change', function () {
              if (check.checked) draft.add(value);
              else draft.delete(value);
            });
            text.textContent = value;
            label.append(check, text);
            list.appendChild(label);
          });
        }

        popup.addEventListener('click', function (event) {
          var action = event.target.getAttribute('data-action');
          if (action === 'all') {
            values.forEach(function (value) { draft.add(value); });
            renderOptions();
          } else if (action === 'none') {
            draft.clear();
            renderOptions();
          } else if (action === 'cancel') {
            closeFilterPopup();
          } else if (action === 'apply') {
            if (draft.size === values.length) delete filterState[key];
            else filterState[key] = new Set(draft);
            button.classList.toggle('is-active', !!filterState[key]);
            button.textContent = filterState[key] ? draft.size + ' выбрано ▾' : 'Все ▾';
            closeFilterPopup();
            applyColumnFilters(key);
          }
        });
        search.addEventListener('input', renderOptions);
        renderOptions();
        var rect = button.getBoundingClientRect();
        popup.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - popup.offsetWidth - 8)) + 'px';
        popup.style.top = Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - popup.offsetHeight - 8)) + 'px';
        search.focus();
      }

      document.getElementById('vtFilter').addEventListener('click', function (event) {
        var button = event.target.closest('.vt-filter-button');
        if (button) openColumnFilter(button);
      });

      function filterDocumentClick(event) {
        if (filterPopup && !filterPopup.contains(event.target) && !event.target.closest('.vt-filter-button')) {
          closeFilterPopup();
        }
      }
      document.addEventListener('click', filterDocumentClick);

      var ticking = false;
      vp.addEventListener('scroll', function () {
        if (ticking) return; ticking = true;
        requestAnimationFrame(function () { render(false); ticking = false; });
      });

      function fitVpHeight() {
        var top = vp.getBoundingClientRect().top;
        var h = top > 0 ? window.innerHeight - top - 10 : window.innerHeight - 10;
        vp.style.height = Math.max(300, h) + 'px';
        render(false);
      }

      requestAnimationFrame(function () { fitVpHeight(); render(true); });
      window.addEventListener('resize', fitVpHeight, { passive: true });
      window.addEventListener('scroll', fitVpHeight, { passive: true });

      render(true);
      host._vtCleanup = function () {
        if (resizeMove) document.removeEventListener('mousemove', resizeMove);
        if (resizeEnd) document.removeEventListener('mouseup', resizeEnd);
        if (head._sortable) head._sortable.destroy();
        closeFilterPopup();
        document.removeEventListener('click', filterDocumentClick);
        head.classList.remove('vt-head--dragging');
        window.removeEventListener('resize', fitVpHeight);
        window.removeEventListener('scroll', fitVpHeight);
      };
    }

    function saveCSV(filename) {
      // Экспортируются колонки всех вкладок.
      var csvCols = _vtAllCols.length ? _vtAllCols : _vtCols;
      saveCSVFromData(csvCols, _vtFiltered, filename);
    }

    $('#btnDetailCSV').on('click', function () {
      //var title = $('#detailTitle').text().replace(/[\\/:*?"<>|]/g, '_');
      //saveCSV(title);
      var title = $('#detailTitle').text().replace(/[\\/:*?"<>|]/g, '_');
      saveExcelfromVT('detailTable', title);
    });
  </script>
</body>

</html>
