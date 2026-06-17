<?php
// Задаем дефолтные значения, если переменные не пришли из Slim-фреймворка, чтобы не было варнингов
$appName = $appName ?? 'АО Метафракс Кемикалс';
$basePath = $basePath ?? '';
$user = $user ?? ['display_name' => 'Пользователь'];

$data_file = __DIR__ . '/data.json';
$wagon_data = file_exists($data_file) ? file_get_contents($data_file) : '[]';
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

    <!-- Стили приложения -->
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">

    <!-- Подключение библиотек карты Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />

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

        /* Исправление высоты под шапку сайта */
        .app-container {
            display: flex;
            flex: 1;
            height: calc(100vh - 60px);
        }

        /* Жесткое скрытие дефолтных элементов управления Leaflet */
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
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 4px;
            cursor: pointer;
            transition: all .12s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid transparent;
        }

        .station-item:hover {
            background: #faf9fc;
        }

        .station-item.active {
            background: var(--primary-light);
            border-color: rgba(79, 50, 142, 0.15);
        }

        .station-count {
            min-width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--mono);
            font-size: 13px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .station-count.big {
            background: var(--primary);
            color: #fff;
        }

        .station-count.mid {
            background: var(--primary-light);
            color: var(--primary);
        }

        .station-count.small {
            background: #f0eef4;
            color: var(--muted);
        }

        .station-info {
            flex: 1;
            min-width: 0;
        }

        .station-name {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .station-meta {
            font-size: 11px;
            color: var(--muted);
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .station-badges {
            display: flex;
            gap: 4px;
            margin-top: 5px;
        }

        .badge {
            font-size: 10px;
            font-weight: 500;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .badge.loaded {
            background: #e6f4ea;
            color: var(--loaded);
        }

        .badge.empty {
            background: #fef7e0;
            color: var(--empty);
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

        .no-results {
            padding: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
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

    <header class="site-header">
        <div class="header-inner">
            <div class="brand">
                <div class="brand-icon">
                    <img src="<?= htmlspecialchars($basePath) ?>/assets/img/meta-logo.png" alt="" class="brand-logo">
                </div>
                <div class="brand-text">
                    <div class="brand-name"><?= htmlspecialchars($appName) ?></div>
                    <div id="brandDateSub" class="brand-date-sub"></div>
                </div>
            </div>
            <div class="header-meta">
                <div class="user-info">
                    <span class="user-name" title="<?= htmlspecialchars($user['auth_source'] ?? '') ?>">
                        <?= htmlspecialchars($user['display_name'] ?? $user['username'] ?? '') ?>
                    </span>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="window.history.back()">← Назад</button>
                </div>
            </div>
        </div>
    </header>

    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-search">
                <input type="text" id="station-search" placeholder="Поиск станции">
            </div>
            <!-- <div class="sidebar-filters">
                <button class="filter-btn active" data-filter="all">Все</button>
                <button class="filter-btn" data-filter="loaded">Гружёные</button>
                <button class="filter-btn" data-filter="empty">Порожние</button>
                <button class="filter-btn" data-filter="idle">Простой &gt;5 дней</button>
            </div> -->
            <div class="station-list" id="station-list"></div>
        </div>

        <div id="map"></div>
    </div>

    <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script>
        'use strict';

        const STATIONS = <?= $wagon_data ?>;
        let activeFilter = 'all', activeStation = null, markerGroup = null;

        const map = L.map('map', { center: [57.5, 60.0], zoom: 4, zoomControl: true, attributionControl: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

        function getFilteredWagons(station) {
            return station.wagons.filter(w => {
                if (activeFilter === 'loaded') return w.ld;
                if (activeFilter === 'empty') return !w.ld;
                if (activeFilter === 'idle') return w.dm > 5;
                return true;
            });
        }

        function isStationMatchSearch(station, query) {
            if (!query) return true;
            if (station.name.toLowerCase().includes(query) || station.code.includes(query)) return true;
            return getFilteredWagons(station).some(w => w.n && w.n.toString().includes(query));
        }

        function renderSidebar() {
            const search = document.getElementById('station-search').value.toLowerCase().trim();
            const list = document.getElementById('station-list');

            const html = STATIONS.map(s => {
                const wagons = getFilteredWagons(s);
                const cnt = wagons.length;

                if (cnt === 0 || !isStationMatchSearch(s, search)) return '';

                const cls = cnt >= 100 ? 'big' : cnt >= 10 ? 'mid' : 'small';
                const isActive = activeStation && activeStation.code === s.code;
                const loaded = wagons.filter(w => w.ld).length;

                return `<div class="station-item${isActive ? ' active' : ''}" onclick="selectStation('${s.code}')">
                <div class="station-count ${cls}">${cnt}</div>
                <div class="station-info">
                    <div class="station-name">${s.name}</div>
                    <div class="station-meta">${s.road} ж.д. · ${s.code}</div>
                    <div class="station-badges">
                        ${loaded > 0 ? `<span class="badge loaded">${loaded} груж</span>` : ''}
                        ${cnt - loaded > 0 ? `<span class="badge empty">${cnt - loaded} пор</span>` : ''}
                    </div>
                </div>
            </div>`;
            }).join('');

            list.innerHTML = html || '<div class="no-results">Ничего не найдено</div>';
        }

        function buildMarkers() {
            if (markerGroup) map.removeLayer(markerGroup);

            markerGroup = L.markerClusterGroup({
                maxClusterRadius: 50,
                iconCreateFunction(cluster) {
                    const n = cluster.getChildCount();
                    const size = n > 500 ? 52 : n > 50 ? 42 : 34;
                    // Добавляем класс кумулятивного маркера кластера напрямую, убирая дефолтный квадратный стиль
                    return L.divIcon({
                        html: `<div class="leaflet-data-marker" style="width:${size}px;height:${size}px;font-size:${n > 99 ? 10 : 12}px;background:#251249">${n}</div>`,
                        iconSize: [size, size], iconAnchor: [size / 2, size / 2], className: ''
                    });
                }
            }).addTo(map);

            const search = document.getElementById('station-search').value.toLowerCase().trim();

            STATIONS.forEach(s => {
                const wagons = getFilteredWagons(s);
                const cnt = wagons.length;

                if (cnt === 0 || !isStationMatchSearch(s, search)) return;

                const size = cnt >= 200 ? 48 : cnt >= 50 ? 40 : cnt >= 10 ? 34 : 28;
                const cls = cnt >= 200 ? 'large' : cnt >= 10 ? '' : 'accent';

                const icon = L.divIcon({
                    html: `<div class="wagon-marker ${cls}" style="width:${size}px;height:${size}px;font-size:${cnt > 99 ? 10 : 12}px">${cnt}</div>`,
                    iconSize: [size, size], iconAnchor: [size / 2, size / 2], className: ''
                });

                const wagonsListHtml = wagons.map(w => `
                    <div style="border-bottom: 1px solid #e2e1e7; padding: 5px 0; font-size: 11px; line-height: 1.4;">
                        <strong style="color: var(--primary); font-family: var(--mono); font-size: 12px;">№ ${w.wagon_num}</strong> — ${w.wagon_type}<br>
                        <span style="color: ${w.ld ? 'var(--loaded)' : 'var(--empty)'}; font-weight: 600;">
                            ${w.ld ? 'Гружёный' : 'Порожний'}
                        </span>
                        ${w.cargo ? `<span style="color: #555;"> (${w.cargo})</span>` : ''}
                        ${w.dest_station ? `<br><span style="color: #7c7e86; font-size: 10px;">→ Назначение: ${w.dest_station}</span>` : ''}
                        ${w.days_no_move > 0 ? `<br><small style="color: var(--empty);">Без движения: ${w.days_no_move} дн.</small>` : ''}
                    </div>
                `).join('');

                const marker = L.marker([s.lat, s.lng], { icon });

                marker.bindPopup(`
                    < div >
                    <div class="popup-title">${s.name}</div>
                    <div class="popup-sub">${s.road} ж.д. · Вагонов: <strong>${cnt}</strong></div>
                    <div class="popup-scroll" style="max-height: 160px; overflow-y: auto; padding-right: 4px;">
                        ${wagonsListHtml}
                    </div>
                </div >
            `, { maxWidth: 300 });

                marker.on('click', () => { selectStation(s.code); });
                markerGroup.addLayer(marker);
            });
        }

        function selectStation(code) {
            const s = STATIONS.find(x => x.code === code);
            if (!s) return;
            activeStation = s;
            renderSidebar();
            map.setView([s.lat, s.lng], Math.max(map.getZoom(), 8), { animate: true });
        }

        document.getElementById('station-search').addEventListener('input', () => {
            renderSidebar();
            buildMarkers();
        });

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                activeFilter = e.target.dataset.filter;
                buildMarkers();
                renderSidebar();
            });
        });

        $(function () {
            renderSidebar();
            buildMarkers();
        });
    </script>
</body>

</html>