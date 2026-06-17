<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($appName) ?> — Карта</title>
<link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>
<script>window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';</script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ink:    #0e1117;
    --paper:  #f7f6f2;
    --rail:   #1a3a6b;
    --accent: #d4400a;
    --loaded: #2e6e3e;
    --empty:  #7a5c1a;
    --muted:  #8a8a8a;
    --border: #d0cec8;
    --panel:  #ffffff;
    --mono: 'JetBrains Mono', monospace;
    --sans: 'Inter', sans-serif;
  }

  html, body { height: 100%; font-family: var(--sans); background: var(--paper); color: var(--ink); }

  header {
    display: flex; align-items: center; gap: 18px;
    padding: 12px 20px;
    background: var(--rail);
    border-bottom: 3px solid var(--accent);
    position: relative; z-index: 1000;
  }
  .logo {
    font-family: var(--mono);
    font-size: 11px; font-weight: 600; letter-spacing: .12em;
    color: #fff; opacity: .55; text-transform: uppercase;
    border: 1px solid rgba(255,255,255,.2);
    padding: 3px 8px; border-radius: 2px;
  }
  header h1 { font-size: 15px; font-weight: 500; color: #fff; letter-spacing: .02em; }
  .header-right { margin-left: auto; display: flex; align-items: center; gap: 24px; }
  .header-stats { display: flex; gap: 24px; }
  .stat-pill { text-align: right; font-family: var(--mono); }
  .stat-pill .val { font-size: 18px; font-weight: 600; color: #fff; line-height: 1; }
  .stat-pill .lbl { font-size: 9px; color: rgba(255,255,255,.5); letter-spacing: .1em; text-transform: uppercase; }
  .btn-back {
    font-size: 12px; padding: 6px 14px;
    border: 1px solid rgba(255,255,255,.3);
    border-radius: 4px; background: transparent;
    color: #fff; cursor: pointer; font-family: var(--sans);
    text-decoration: none; display: flex; align-items: center; gap: 6px;
    transition: background .15s;
  }
  .btn-back:hover { background: rgba(255,255,255,.1); }

  .app { display: flex; height: calc(100vh - 57px); }

  .sidebar {
    width: 320px; flex-shrink: 0;
    background: var(--panel);
    border-right: 1px solid var(--border);
    display: flex; flex-direction: column;
    overflow: hidden;
  }
  .sidebar-search { padding: 12px 14px; border-bottom: 1px solid var(--border); }
  .sidebar-search input {
    width: 100%; padding: 8px 12px;
    border: 1px solid var(--border); border-radius: 3px;
    font-family: var(--sans); font-size: 13px;
    background: var(--paper); outline: none; color: var(--ink);
  }
  .sidebar-search input:focus { border-color: var(--rail); }

  .sidebar-filters {
    padding: 8px 14px; border-bottom: 1px solid var(--border);
    display: flex; gap: 6px; flex-wrap: wrap;
  }
  .filter-btn {
    font-size: 11px; padding: 3px 9px;
    border: 1px solid var(--border); border-radius: 20px;
    background: transparent; cursor: pointer;
    font-family: var(--sans); color: var(--muted);
    transition: all .15s;
  }
  .filter-btn:hover { border-color: var(--rail); color: var(--rail); }
  .filter-btn.active { background: var(--rail); color: #fff; border-color: var(--rail); }

  .station-list { flex: 1; overflow-y: auto; padding: 4px 0; }
  .station-item {
    padding: 10px 14px; border-bottom: 1px solid #f0ede8;
    cursor: pointer; transition: background .12s;
    display: flex; align-items: center; gap: 10px;
  }
  .station-item:hover { background: #f5f3ef; }
  .station-item.active { background: #edf0f8; border-left: 3px solid var(--rail); padding-left: 11px; }
  .station-count {
    min-width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--mono); font-size: 12px; font-weight: 600;
    flex-shrink: 0;
  }
  .station-count.big   { background: var(--rail); color: #fff; }
  .station-count.mid   { background: #d4e0f5; color: var(--rail); }
  .station-count.small { background: #e8e6e0; color: var(--muted); }
  .station-info { flex: 1; min-width: 0; }
  .station-name  { font-size: 13px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .station-meta  { font-size: 11px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .station-badges { display: flex; gap: 4px; margin-top: 3px; }
  .badge { font-size: 10px; padding: 1px 5px; border-radius: 2px; font-family: var(--mono); }
  .badge.loaded { background: #d4edda; color: var(--loaded); }
  .badge.empty  { background: #fef3cd; color: var(--empty); }

  #map { flex: 1; }

  .detail-panel {
    position: absolute; right: 0; top: 0; bottom: 0;
    width: 380px; background: var(--panel);
    border-left: 1px solid var(--border);
    z-index: 500; display: none; flex-direction: column;
    transform: translateX(100%);
    transition: transform .25s ease;
    box-shadow: -4px 0 16px rgba(0,0,0,.08);
  }
  .detail-panel.open { display: flex; transform: translateX(0); }

  .detail-header { padding: 16px 16px 12px; background: var(--rail); color: #fff; position: relative; }
  .detail-close {
    position: absolute; top: 12px; right: 12px;
    width: 28px; height: 28px;
    border: 1px solid rgba(255,255,255,.3);
    background: transparent; color: #fff;
    border-radius: 50%; cursor: pointer;
    font-size: 16px; line-height: 26px; text-align: center;
  }
  .detail-station { font-size: 16px; font-weight: 600; margin-bottom: 2px; }
  .detail-road    { font-size: 12px; opacity: .65; }
  .detail-summary { display: flex; gap: 12px; margin-top: 10px; }
  .detail-sum-item { font-family: var(--mono); }
  .detail-sum-item .v { font-size: 20px; font-weight: 600; }
  .detail-sum-item .l { font-size: 9px; opacity: .6; text-transform: uppercase; letter-spacing: .1em; }

  .wagon-search { padding: 10px 12px; border-bottom: 1px solid var(--border); }
  .wagon-search input {
    width: 100%; padding: 6px 10px;
    border: 1px solid var(--border); border-radius: 3px;
    font-size: 12px; font-family: var(--sans);
    background: var(--paper); outline: none;
  }
  .wagon-search input:focus { border-color: var(--rail); }

  .wagon-list { flex: 1; overflow-y: auto; }
  .wagon-row { padding: 8px 12px; border-bottom: 1px solid #f0ede8; font-size: 12px; }
  .wagon-row:hover { background: #fafaf8; }
  .wagon-num   { font-family: var(--mono); font-weight: 600; font-size: 13px; color: var(--rail); }
  .wagon-type  { color: var(--muted); font-size: 11px; margin-top: 1px; }
  .wagon-cargo { margin-top: 3px; font-size: 11px; color: var(--ink); opacity: .75; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .wagon-tags  { display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap; }
  .tag { font-size: 10px; padding: 1px 5px; border-radius: 2px; font-family: var(--mono); }
  .tag.ld   { background: #d4edda; color: var(--loaded); }
  .tag.emp  { background: #fef3cd; color: var(--empty); }
  .tag.rp   { background: #e8e8e8; color: #555; }
  .tag.days { background: #f0e8d8; color: #7a4a00; }

  .wagon-marker {
    background: var(--rail); border: 2px solid #fff; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--mono); font-weight: 600; color: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,.3); cursor: pointer;
  }
  .wagon-marker.accent { background: var(--accent); }
  .wagon-marker.large  { background: #0d2b54; }

  .leaflet-popup-content { min-width: 180px; font-family: var(--sans); }
  .popup-title  { font-weight: 600; font-size: 14px; color: var(--rail); margin-bottom: 4px; }
  .popup-sub    { font-size: 12px; color: var(--muted); }
  .popup-count  { font-family: var(--mono); font-size: 22px; font-weight: 700; color: var(--rail); }
  .popup-btn {
    display: block; width: 100%; margin-top: 10px; padding: 7px;
    background: var(--rail); color: #fff; border: none;
    border-radius: 3px; cursor: pointer; font-size: 12px;
    font-family: var(--sans); text-align: center;
  }
  .popup-btn:hover { background: #0d2b54; }

  .no-results { padding: 20px; text-align: center; color: var(--muted); font-size: 13px; }

  /* Загрузка */
  .loading-overlay {
    position: absolute; inset: 0; background: rgba(247,246,242,.85);
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    z-index: 2000; gap: 12px;
  }
  .loading-overlay.hidden { display: none; }
  .spinner {
    width: 40px; height: 40px; border-radius: 50%;
    border: 3px solid #ddd; border-top-color: var(--rail);
    animation: spin .8s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
  .loading-text { font-size: 13px; color: var(--muted); }
</style>
</head>
<body>

<header>
  <div class="logo">РЖД</div>
  <h1><?= htmlspecialchars($appName) ?> — Карта</h1>
  <div class="header-right">
    <div class="header-stats">
      <div class="stat-pill">
        <div class="val" id="total-wagons">—</div>
        <div class="lbl">вагонов</div>
      </div>
      <div class="stat-pill">
        <div class="val" id="total-stations">—</div>
        <div class="lbl">станций</div>
      </div>
    </div>
    <a href="<?= htmlspecialchars($basePath) ?>/" class="btn-back">← Назад</a>
  </div>
</header>

<div class="loading-overlay" id="loading-overlay">
  <div class="spinner"></div>
  <div class="loading-text">Загрузка данных…</div>
</div>

<div class="app">
  <div class="sidebar">
    <div class="sidebar-search">
      <input type="text" id="station-search" placeholder="Поиск станции…">
    </div>
    <div class="sidebar-filters">
      <button class="filter-btn active" data-filter="all">Все</button>
      <button class="filter-btn" data-filter="loaded">Гружёные</button>
      <button class="filter-btn" data-filter="empty">Порожние</button>
      <button class="filter-btn" data-filter="idle">Простой &gt;5 дней</button>
    </div>
    <div class="station-list" id="station-list"></div>
  </div>

  <div id="map"></div>

  <div class="detail-panel" id="detail-panel">
    <div class="detail-header">
      <button class="detail-close" onclick="closeDetail()">✕</button>
      <div class="detail-station" id="dp-name"></div>
      <div class="detail-road"    id="dp-road"></div>
      <div class="detail-summary">
        <div class="detail-sum-item">
          <div class="v" id="dp-total"></div>
          <div class="l">вагонов</div>
        </div>
        <div class="detail-sum-item">
          <div class="v" id="dp-loaded" style="color:#90cca0"></div>
          <div class="l">гружёных</div>
        </div>
        <div class="detail-sum-item">
          <div class="v" id="dp-empty" style="color:#ffd87a"></div>
          <div class="l">порожних</div>
        </div>
      </div>
    </div>
    <div class="wagon-search">
      <input type="text" id="wagon-search" placeholder="Поиск вагона по номеру…">
    </div>
    <div class="wagon-list" id="wagon-list"></div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
var STATIONS = [];
var activeFilter  = 'all';
var activeStation = null;
var markers       = {};
var markerGroup;
var map;

// ── MAP INIT ──
map = L.map('map', { center: [57.5, 60.0], zoom: 4, zoomControl: true });

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
  attribution: '© OpenStreetMap, © CartoDB',
  subdomains: 'abcd', maxZoom: 19
}).addTo(map);

markerGroup = L.markerClusterGroup({
  maxClusterRadius: 50,
  iconCreateFunction: function (cluster) {
    var n    = cluster.getChildCount();
    var size = n > 500 ? 52 : n > 50 ? 42 : 34;
    return L.divIcon({
      html: '<div class="wagon-marker large" style="width:' + size + 'px;height:' + size + 'px;font-size:' + (n > 99 ? 10 : 12) + 'px">' + n + '</div>',
      iconSize: [size, size], iconAnchor: [size / 2, size / 2], className: ''
    });
  }
});

// ── HELPERS ──
function getFilteredWagons(station) {
  return station.wagons.filter(function (w) {
    if (activeFilter === 'loaded') return w.ld;
    if (activeFilter === 'empty')  return !w.ld;
    if (activeFilter === 'idle')   return w.dm > 5;
    return true;
  });
}

function countByFilter(station) {
  return getFilteredWagons(station).length;
}

// ── SIDEBAR ──
function renderSidebar() {
  var search = document.getElementById('station-search').value.toLowerCase();
  var list   = document.getElementById('station-list');
  var html   = '';
  var shown  = 0;

  STATIONS.forEach(function (s) {
    var cnt = countByFilter(s);
    if (cnt === 0) return;
    if (search && !s.name.toLowerCase().includes(search)) return;
    shown++;

    var cls      = cnt >= 100 ? 'big' : cnt >= 10 ? 'mid' : 'small';
    var isActive = activeStation && activeStation.code === s.code;
    var loaded   = s.wagons.filter(function (w) { return w.ld; }).length;
    var empty    = s.wagons.filter(function (w) { return !w.ld; }).length;

    html += '<div class="station-item' + (isActive ? ' active' : '') + '" onclick="selectStation(\'' + s.code + '\')">' +
      '<div class="station-count ' + cls + '">' + cnt + '</div>' +
      '<div class="station-info">' +
        '<div class="station-name">' + s.name + '</div>' +
        '<div class="station-meta">' + s.road + '</div>' +
        '<div class="station-badges">' +
          (loaded > 0 ? '<span class="badge loaded">▲ ' + loaded + ' груж</span>' : '') +
          (empty  > 0 ? '<span class="badge empty">○ '  + empty  + ' пор</span>'  : '') +
        '</div>' +
      '</div></div>';
  });

  list.innerHTML = shown ? html : '<div class="no-results">Нет станций</div>';
}

// ── MARKERS ──
function buildMarkers() {
  markerGroup.clearLayers();
  markers = {};

  STATIONS.forEach(function (s) {
    var cnt = countByFilter(s);
    if (cnt === 0) return;

    var size = cnt >= 200 ? 48 : cnt >= 50 ? 40 : cnt >= 10 ? 34 : 28;
    var cls  = cnt >= 200 ? 'large' : cnt >= 10 ? '' : 'accent';

    var icon = L.divIcon({
      html: '<div class="wagon-marker ' + cls + '" style="width:' + size + 'px;height:' + size + 'px;font-size:' + (cnt > 99 ? 10 : 12) + 'px">' + cnt + '</div>',
      iconSize: [size, size], iconAnchor: [size / 2, size / 2], className: ''
    });

    var marker = L.marker([s.lat, s.lng], { icon: icon });
    marker.bindPopup(
      '<div class="popup-title">' + s.name + '</div>' +
      '<div class="popup-sub">' + s.road + '</div>' +
      '<div class="popup-count">' + cnt + '</div>' +
      '<div class="popup-sub">вагонов на станции</div>' +
      '<button class="popup-btn" onclick="selectStation(\'' + s.code + '\'); map.closePopup()">Показать вагоны →</button>',
      { maxWidth: 220 }
    );

    marker.on('click', (function (code) {
      return function () { selectStation(code); };
    })(s.code));

    markers[s.code] = marker;
    markerGroup.addLayer(marker);
  });

  map.addLayer(markerGroup);
}

// ── DETAIL PANEL ──
function selectStation(code) {
  var s = null;
  STATIONS.forEach(function (x) { if (x.code === code) s = x; });
  if (!s) return;
  activeStation = s;

  document.getElementById('dp-name').textContent  = s.name;
  document.getElementById('dp-road').textContent  = s.road + ' железная дорога · ' + s.code;
  document.getElementById('dp-total').textContent  = countByFilter(s);
  document.getElementById('dp-loaded').textContent = s.wagons.filter(function (w) { return  w.ld; }).length;
  document.getElementById('dp-empty').textContent  = s.wagons.filter(function (w) { return !w.ld; }).length;
  document.getElementById('wagon-search').value = '';
  renderWagonList(s, '');

  var panel = document.getElementById('detail-panel');
  panel.style.display = 'flex';
  setTimeout(function () { panel.classList.add('open'); }, 10);

  renderSidebar();
  map.setView([s.lat, s.lng], Math.max(map.getZoom(), 8), { animate: true });
}

function renderWagonList(s, search) {
  var wagons = getFilteredWagons(s).filter(function (w) {
    return !search || w.n.includes(search);
  });
  var list = document.getElementById('wagon-list');

  if (!wagons.length) {
    list.innerHTML = '<div class="no-results">Вагоны не найдены</div>';
    return;
  }

  list.innerHTML = wagons.map(function (w) {
    var tags = [];
    if (w.ld)    tags.push('<span class="tag ld">Гружёный</span>');
    else         tags.push('<span class="tag emp">Порожний</span>');
    if (w.ws)    tags.push('<span class="tag rp">' + w.ws + '</span>');
    if (w.dm > 0) tags.push('<span class="tag days">' + w.dm + ' дн. без движения</span>');

    return '<div class="wagon-row">' +
      '<div class="wagon-num">'  + w.n  + '</div>' +
      '<div class="wagon-type">' + w.wt + '</div>' +
      (w.cg ? '<div class="wagon-cargo" title="' + w.cg + '">' + w.cg + '</div>' : '') +
      (w.ds ? '<div class="wagon-cargo" style="color:#555">→ ' + w.ds + '</div>' : '') +
      '<div class="wagon-tags">' + tags.join('') + '</div>' +
    '</div>';
  }).join('');
}

function closeDetail() {
  var panel = document.getElementById('detail-panel');
  panel.classList.remove('open');
  setTimeout(function () { panel.style.display = 'none'; }, 250);
  activeStation = null;
  renderSidebar();
}

// ── STATS ──
function updateStats() {
  var total            = STATIONS.reduce(function (a, s) { return a + countByFilter(s); }, 0);
  var stationsWithWagons = STATIONS.filter(function (s) { return countByFilter(s) > 0; }).length;
  document.getElementById('total-wagons').textContent   = total.toLocaleString('ru');
  document.getElementById('total-stations').textContent = stationsWithWagons;
}

// ── EVENTS ──
document.getElementById('station-search').addEventListener('input', renderSidebar);

document.getElementById('wagon-search').addEventListener('input', function (e) {
  if (activeStation) renderWagonList(activeStation, e.target.value.trim());
});

document.querySelectorAll('.filter-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.filter-btn').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    activeFilter = btn.dataset.filter;
    buildMarkers();
    renderSidebar();
    updateStats();
    if (activeStation) {
      document.getElementById('dp-total').textContent = countByFilter(activeStation);
      renderWagonList(activeStation, document.getElementById('wagon-search').value.trim());
    }
  });
});

// ── LOAD DATA ──
fetch(window.APP_BASE + '/api/map/stations')
  .then(function (r) { return r.json(); })
  .then(function (data) {
    STATIONS = data;
    document.getElementById('loading-overlay').classList.add('hidden');
    updateStats();
    renderSidebar();
    buildMarkers();
  })
  .catch(function () {
    document.querySelector('.loading-text').textContent = 'Ошибка загрузки данных';
  });
</script>
</body>
</html>
