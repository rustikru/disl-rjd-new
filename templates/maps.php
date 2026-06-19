<?php

$appName = $appName ?? 'АО Метафракс Кемикалс';
$basePath = $basePath ?? '';
$user = $user ?? ['display_name' => 'Пользователь'];
$reportDtLabel = $reportDtLabel ?? '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($appName) ?> — Дислокация
    </title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">

    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">

    <!-- Подключение библиотек карты Leaflet -->
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/Leaflet/leaflet.css" />
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/Leaflet/MarkerCluster.css" />

    <script>window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';</script>
    <style>
        :root {
            --ink: #1f2024;
            --paper: #f4f3f8;
            --primary: #4f328e;
            --primary-light: #f1ecf9;
            --loaded: #2e6e3e;
            --empty: #7a5c1a;
            --muted: #7c7e86;
            --border: #e2e1e7;
            --panel: #ffffff;
            --mono: 'JetBrains Mono', monospace;
            --sans: 'Inter', sans-serif;
        }

        body {
            display: flex;
            flex-direction: column;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            font-family: var(--sans);
            background: var(--paper);
        }

        .app-container {
            display: flex;
            flex: 1;
            height: calc(100vh - 60px);
        }

        .leaflet-control-attribution,
        .leaflet-control-zoom {
            display: none !important;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 320px;
            flex-shrink: 0;
            background: var(--panel);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 1000;
        }

        .sidebar-search {
            padding: 16px 14px 10px;
        }

        .sidebar-search input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: var(--sans);
            font-size: 13px;
            background: #faf9fc;
            outline: none;
            color: var(--ink);
            transition: all 0.15s ease;
        }

        .sidebar-search input:focus {
            border-color: var(--primary);
            background: #fff;
        }

        .sidebar-filters {
            padding: 4px 14px 14px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-btn {
            font-size: 11px;
            font-weight: 500;
            padding: 5px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: #fff;
            cursor: pointer;
            color: var(--muted);
            transition: all .15s;
        }

        .filter-btn:hover,
        .filter-btn.active {
            border-color: var(--primary);
            color: var(--primary);
        }

        .filter-btn.active {
            background: var(--primary);
            color: #fff;
        }

        .station-list {
            flex: 1;
            overflow-y: auto;
            padding: 6px;
        }

        .station-item {
            padding: 8px 10px;
            border-radius: 6px;
            margin-bottom: 2px;
            cursor: pointer;
            transition: background .1s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .station-item:hover {
            background: #f0eef4;
        }

        .station-item.active {
            background: var(--primary-light);
        }

        .station-info {
            flex: 1;
            min-width: 0;
        }

        .station-name {
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--ink);
        }

        .station-meta {
            font-size: 11px;
            color: var(--muted);
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .station-count {
            font-family: var(--mono);
            font-size: 13px;
            font-weight: 600;
            flex-shrink: 0;
            color: var(--primary);
        }

        /* ── MAP & FIXES FOR MARKERS ── */
        #map {
            flex: 1;
            height: 100%;
        }

        /* Жестко фиксируем круглую форму и убираем дефолтные стили кластеризатора */
        .leaflet-data-marker,
        .wagon-marker {
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-family: var(--mono) !important;
            font-weight: 600 !important;
            color: #fff !important;
            border: 2px solid #fff !important;
            box-shadow: 0 3px 8px rgba(79, 50, 142, 0.3) !important;
            box-sizing: border-box !important;
        }

        .wagon-marker {
            background: var(--primary) !important;
        }

        .wagon-marker.accent {
            background: #3a226b !important;
        }

        .wagon-marker.large {
            background: #251249 !important;
            box-shadow: 0 4px 12px rgba(37, 18, 73, 0.4) !important;
        }

        /* Стилизация всплывающего окна (Popup) */
        .leaflet-popup-content-wrapper {
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            font-family: var(--sans);
        }

        .leaflet-popup-content {
            margin: 14px;
            min-width: 240px;
        }

        .popup-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
            color: var(--ink);
        }

        .popup-sub {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 8px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 6px;
        }

        .sidebar-summary {
            padding: 2px 16px 10px;
            font-size: 12px;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }

        .sidebar-summary strong {
            color: var(--primary);
            font-family: var(--mono);
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .sidebar-cargo {
            padding: 0 14px 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-cargo select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: var(--sans);
            font-size: 12px;
            background: #faf9fc;
            color: var(--ink);
            outline: none;
            cursor: pointer;
            transition: border-color .15s;
            box-sizing: border-box;
        }

        .sidebar-cargo select:focus {
            border-color: var(--primary);
            background: #fff;
        }

        .btn-reset {
            align-self: flex-end;
            padding: 5px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            font-family: var(--sans);
            font-size: 11px;
            color: var(--muted);
            cursor: pointer;
            white-space: nowrap;
            transition: all .15s;
        }

        .btn-reset:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .popup-scroll::-webkit-scrollbar {
            width: 4px;
        }

        .popup-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .popup-scroll::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }
    </style>
</head>

<body>

<?php
    $headerSub   = '<div id="brandDateSub" class="brand-date-sub">'
      . ($reportDtLabel ? 'Дислокация РЖД на ' . htmlspecialchars($reportDtLabel) : '')
      . '</div>';
    $headerRight = '<button type="button" class="btn btn-ghost btn-sm" onclick="goBack()">← Назад</button>';
    include __DIR__ . '/partials/header.php';
?>

    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-search">
                <input type="text" id="station-search" placeholder="Станция или № вагона">
            </div>
            <div class="sidebar-cargo">
                <select id="cargo-filter">
                    <option value="">— Все грузы —</option>
                </select>
                <button class="btn-reset" id="btn-reset">Сбросить</button>
            </div>
            <div class="sidebar-summary" id="sidebar-summary"></div>
            <div class="station-list" id="station-list"></div>
        </div>

        <div id="map"></div>
    </div>
    <!-- Подключение библиотек карты Leaflet -->
    <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        'use strict';

        function goBack() {
          if (window.history.length > 1) {
            window.history.back();
          } else {
            window.close();
            setTimeout(function () { window.location.href = window.APP_BASE + '/'; }, 200);
          }
        }

        var STATIONS = <?= $stationsJson ?? '[]' ?>;
        var CARGOS   = <?= $cargosJson   ?? '[]' ?>;
        var activeFilter = 'all', activeCargo = '', activeStation = null, markerGroup = null;

        // Заполняем список грузов
        var cargoSel = document.getElementById('cargo-filter');
        CARGOS.forEach(function (c) {
            var opt = document.createElement('option');
            opt.value = c;
            // Убираем числовой код в конце: "Аммиак (488161)" → "Аммиак"
            var label = c.replace(/\s*\(\d+\)\s*$/, '').trim();
            if (label.length > 42) label = label.slice(0, 40) + '…';
            opt.textContent = label;
            cargoSel.appendChild(opt);
        });

        var map = L.map('map', { center: [57.5, 60.0], zoom: 4, zoomControl: true, attributionControl: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

        function getFilteredWagons(station) {
            return station.wagons.filter(function (w) {
                if (activeCargo && w.cargo !== activeCargo) return false;
                if (activeFilter === 'loaded') return w.ld;
                if (activeFilter === 'empty') return !w.ld;
                if (activeFilter === 'idle') return w.days_no_move > 5;
                return true;
            });
        }

        function isStationMatchSearch(station, query) {
            if (!query) return true;
            if (station.name.toLowerCase().includes(query) || station.code.includes(query)) return true;
            return getFilteredWagons(station).some(function (w) { return w.wagon_num && w.wagon_num.toString().includes(query); });
        }

        function renderSidebar() {
            var search = document.getElementById('station-search').value.toLowerCase().trim();
            var list = document.getElementById('station-list');
            var summary = document.getElementById('sidebar-summary');

            var visible = STATIONS.map(function (s) {
                return { s: s, wagons: getFilteredWagons(s) };
            }).filter(function (x) {
                return x.wagons.length > 0 && isStationMatchSearch(x.s, search);
            });

            visible.sort(function (a, b) { return b.wagons.length - a.wagons.length; });

            var totalWagons = visible.reduce(function (acc, x) { return acc + x.wagons.length; }, 0);
            //summary.innerHTML = 'Станций: <strong>' + visible.length + '</strong> · Вагонов: <strong>' + totalWagons + '</strong>';
            summary.innerHTML = 'всего вагонов: <strong>' + totalWagons + '</strong>';

            var html = visible.map(function (x) {
                var s = x.s, cnt = x.wagons.length;
                var isActive = activeStation && activeStation.code === s.code;
                return '<div class="station-item' + (isActive ? ' active' : '') + '" onclick="selectStation(\'' + s.code + '\')">' +
                    '<div class="station-info">' +
                    '<div class="station-name">' + s.name + '</div>' +
                    '<div class="station-meta">' + s.road + '</div>' +
                    '</div>' +
                    '<div class="station-count">' + cnt + '</div>' +
                    '</div>';
            }).join('');

            list.innerHTML = html || '<div class="no-results">Ничего не найдено</div>';
        }

        function buildMarkers() {
            if (markerGroup) map.removeLayer(markerGroup);

            markerGroup = L.markerClusterGroup({
                maxClusterRadius: 50,
                showCoverageOnHover: false,
                iconCreateFunction: function (cluster) {
                    var n = 0;
                    cluster.getAllChildMarkers().forEach(function (m) { n += (m._wagonCount || 1); });
                    var size = n > 500 ? 52 : n > 50 ? 42 : 34;
                    return L.divIcon({
                        html: '<div class="leaflet-data-marker" style="width:' + size + 'px;height:' + size + 'px;font-size:' + (n > 99 ? 10 : 12) + 'px;background:#251249">' + n + '</div>',
                        iconSize: [size, size], iconAnchor: [size / 2, size / 2], className: ''
                    });
                }
            }).addTo(map);

            var search = document.getElementById('station-search').value.toLowerCase().trim();

            STATIONS.forEach(function (s) {
                var wagons = getFilteredWagons(s);
                var cnt = wagons.length;

                if (cnt === 0 || !isStationMatchSearch(s, search)) return;

                var size = cnt >= 200 ? 48 : cnt >= 50 ? 40 : cnt >= 10 ? 34 : 28;
                var cls = cnt >= 200 ? 'large' : cnt >= 10 ? '' : 'accent';

                var icon = L.divIcon({
                    html: '<div class="wagon-marker ' + cls + '" style="width:' + size + 'px;height:' + size + 'px;font-size:' + (cnt > 99 ? 10 : 12) + 'px">' + cnt + '</div>',
                    iconSize: [size, size], iconAnchor: [size / 2, size / 2], className: ''
                });

                var wagonsListHtml = wagons.map(function (w) {
                    return '<div style="border-bottom: 1px solid #e2e1e7; padding: 5px 0; font-size: 11px; line-height: 1.4;">' +
                        '<strong style="color: var(--primary); font-family: var(--mono); font-size: 12px;">' + w.wagon_num + '</strong> — ' + w.wagon_type + '<br>' +
                        '<span style="color: ' + (w.ld ? 'var(--loaded)' : 'var(--empty)') + '; font-weight: 600;">' +
                        (w.ld ? 'Гружёный' : 'Порожний') +
                        '</span>' +
                        (w.cargo ? '<span style="color: #555;"> (' + w.cargo + ')</span>' : '') +
                        (w.dest_station ? '<br><span style="color: #7c7e86; font-size: 10px;">→ Назначение: ' + w.dest_station + '</span>' : '') +
                        (w.days_no_move > 0 ? '<br><small style="color: var(--empty);">Без движения: ' + w.days_no_move + ' дн.</small>' : '') +
                        (w.days_no_oper > 0 ? '<br><small style="color: var(--empty);">Дней без операций: ' + w.days_no_oper + ' дн.</small>' : '') +
                        '</div>';
                }).join('');

                var marker = L.marker([s.lat, s.lng], { icon: icon });
                marker._wagonCount = cnt;

                marker.bindPopup(
                    '<div>' +
                    '<div class="popup-title">' + s.name + '</div>' +
                    '<div class="popup-sub">' + s.road + ' Вагонов: <strong>' + cnt + '</strong></div>' +
                    '<div class="popup-scroll" style="max-height: 160px; overflow-y: auto; padding-right: 4px;">' +
                    wagonsListHtml +
                    '</div>' +
                    '</div>',
                    { maxWidth: 300 }
                );

                marker.on('click', function () { selectStation(s.code); });
                markerGroup.addLayer(marker);
            });
        }

        function selectStation(code) {
            var s = null;
            STATIONS.forEach(function (x) { if (x.code === code) s = x; });
            if (!s) return;
            activeStation = s;
            renderSidebar();
            map.setView([s.lat, s.lng], Math.max(map.getZoom(), 8), { animate: true });
        }

        document.getElementById('station-search').addEventListener('input', function () {
            renderSidebar();
            buildMarkers();
        });

        cargoSel.addEventListener('change', function () {
            activeCargo = this.value;
            renderSidebar();
            buildMarkers();
        });

        document.getElementById('btn-reset').addEventListener('click', function () {
            activeCargo = '';
            activeFilter = 'all';
            activeStation = null;
            document.getElementById('station-search').value = '';
            cargoSel.value = '';
            document.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
            var allBtn = document.querySelector('.filter-btn[data-filter="all"]');
            if (allBtn) allBtn.classList.add('active');
            renderSidebar();
            buildMarkers();
        });

        document.querySelectorAll('.filter-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                document.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
                e.target.classList.add('active');
                activeFilter = e.target.dataset.filter;
                buildMarkers();
                renderSidebar();
            });
        });

        renderSidebar();
        buildMarkers();
    </script>
</body>

</html>