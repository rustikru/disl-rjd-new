<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $reports  [['report_dt' => ..., 'cnt' => ...], ...] */
$appName = $appName ?? 'Дислокация РЖД';
$basePath = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Загрузка справки РЖД</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
  <style>
    .import-wrap {
      max-width: 720px;
      margin: 20px auto;
      padding: 0 16px 24px;
    }

    .import-wrap .table-section {
      margin-bottom: 16px;
    }

    .import-wrap .table-toolbar {
      min-height: 48px;
      padding: 10px 18px;
    }

    .import-upload-body {
      padding: 14px 18px 16px;
    }

    .import-upload-row {
      display: grid;
      grid-template-columns: minmax(0, 1fr) auto;
      gap: 10px;
      align-items: end;
    }

    .import-file-field {
      min-width: 0;
    }

    .import-upload-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert {
      padding: 12px 18px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-size: 14px;
      line-height: 1.5;
    }

    .alert-error {
      background: #FEECEC;
      color: #B91C1C;
      border: 1px solid #FECACA;
    }

    .alert-warn {
      background: #FFFBEB;
      color: #92400E;
      border: 1px solid #FDE68A;
    }

    .alert-ok {
      background: #ECFDF5;
      color: #065F46;
      border: 1px solid #A7F3D0;
    }

    .upload-hint {
      font-size: 12px;
      color: #9DA5B0;
      margin-top: 6px;
    }

    input[type=file] {
      display: block;
      margin-top: 4px;
      width: 100%;
      font-size: 13px;
    }

    .btn-loading {
      opacity: 0.65;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Список файлов */
    .file-list {
      margin: 10px 0 0;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .file-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 12px;
      border-radius: 6px;
      background: #f8f9fa;
      border: 1px solid #e2e5ea;
      font-size: 13px;
    }

    .file-row .file-name {
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      color: #1c2128;
    }

    .file-row .file-meta {
      font-size: 11px;
      color: #9DA5B0;
      white-space: nowrap;
    }

    .file-row .file-status {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* Статусные бейджи */
    .badge {
      font-size: 11px;
      font-weight: 600;
      padding: 2px 8px;
      border-radius: 99px;
      white-space: nowrap;
    }

    .badge-wait {
      background: #f0f2f5;
      color: #5c6370;
    }

    .badge-upload {
      background: #e0eaff;
      color: #2c5282;
    }

    .badge-process {
      background: #fff8e1;
      color: #92400E;
    }

    .badge-ok {
      background: #d1fae5;
      color: #065F46;
    }

    .badge-warn {
      background: #fef9c3;
      color: #713f12;
    }

    .badge-error {
      background: #fee2e2;
      color: #991b1b;
    }

    /* Спиннер */
    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    .spin {
      display: inline-block;
      width: 13px;
      height: 13px;
      border: 2px solid #CBD5E1;
      border-top-color: #3B82F6;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      flex-shrink: 0;
    }

    .upload-summary {
      font-size: 13px;
      color: #5c6370;
    }
    .report-delete-form { display:flex; justify-content:flex-end; }
    .report-delete {
      display:inline-flex; align-items:center; justify-content:center;
      width:30px; height:30px; padding:0; border:1px solid var(--border);
      border-radius:7px; background:var(--surface); color:var(--brand-neg); cursor:pointer;
    }
    .report-delete:hover { background:#fce8e8; border-color:var(--brand-neg); }
    .report-delete svg { width:14px; height:14px; fill:currentColor; }
    .report-actions { display:flex; justify-content:flex-end; gap:5px; }
    .report-download {
      display:inline-flex; align-items:center; justify-content:center;
      width:30px; height:30px; padding:0; border:1px solid var(--border);
      border-radius:7px; background:var(--surface); color:var(--accent); text-decoration:none;
    }
    .report-download:hover { background:#f4edfc; border-color:var(--accent); }
    .report-download svg { width:14px; height:14px; fill:currentColor; }

    .import-reports .data-table th,
    .import-reports .data-table td {
      height: 38px;
      padding-top: 6px;
      padding-bottom: 6px;
    }

    .import-report-number { width: 40px; }
    .import-report-action { width: 82px; }
    .reports-sentinel { height: 1px; }
    .import-reports .data-table td:nth-child(2) { text-align: left; }
    .import-reports .data-table td:nth-child(3) { text-align: right; }

    @media (max-width: 620px) {
      .import-wrap {
        margin-top: 12px;
        padding-inline: 10px;
      }
      .import-upload-row {
        grid-template-columns: 1fr;
        align-items: stretch;
      }
      .import-upload-actions .btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>

<?php
  $headerSub   = '<div class="brand-sub">Загрузка справки РЖД</div>';
  $headerLeft  = '<button type="button" class="btn-nav-back" onclick="goBack()" title="Назад"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="11 6 5 12 11 18"/></svg></button>';
  $headerRight = '';
  include __DIR__ . '/partials/header.php';
?>

  <div class="import-wrap">
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-error"><?= nl2br(htmlspecialchars((string) $_GET['error'])) ?></div><?php endif; ?>
    <?php if (!empty($_GET['warn'])): ?><div class="alert alert-warn"><?= nl2br(htmlspecialchars((string) $_GET['warn'])) ?></div><?php endif; ?>
    <?php if (!empty($_GET['success'])): ?><div class="alert alert-ok"><?= nl2br(htmlspecialchars((string) $_GET['success'])) ?></div><?php endif; ?>

    <section class="table-section">
      <div class="table-toolbar">
        <div class="table-info">
          <span class="table-title">Загрузка справки РЖД (.xlsx)</span>
        </div>
      </div>
      <div class="import-upload-body">
        <div class="import-upload-row">
          <div class="import-file-field">
            <label class="filter-label" for="xlsx_file">Файлы справок</label>
            <input type="file" id="xlsx_file" accept=".xlsx" multiple>
          </div>
          <div class="import-upload-actions">
            <button id="btnUpload" class="btn btn-primary" disabled>Загрузить</button>
            <div class="upload-summary" id="uploadSummary"></div>
          </div>
        </div>
        <div class="upload-hint">
          Поддерживаются файлы .xlsx. Лимиты PHP: файл до <?= htmlspecialchars($uploadLimit ?? 'не задан') ?>,
          запрос до <?= htmlspecialchars($postLimit ?? 'не задан') ?>.
        </div>

        <div class="file-list" id="fileList"></div>

        <script>
          function goBack() {
            if (window.history.length > 1) {
              window.history.back();
            } else {
              window.close();
              setTimeout(function () { window.location.href = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>/'; }, 200);
            }
          }
          (function () {
            var BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';
            var fileInput = document.getElementById('xlsx_file');
            var fileList = document.getElementById('fileList');
            var btnUpload = document.getElementById('btnUpload');
            var summary = document.getElementById('uploadSummary');
            var rows = [];   // { file, rowEl, badgeEl }

            function fmtSize(bytes) {
              if (bytes < 1024) return bytes + ' Б';
              if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' КБ';
              return (bytes / 1024 / 1024).toFixed(1) + ' МБ';
            }

            function setBadge(badgeEl, type, text) {
              badgeEl.className = 'badge badge-' + type;
              badgeEl.innerHTML = (type === 'upload' || type === 'process')
                ? '<span class="spin"></span> ' + text
                : text;
            }

            fileInput.addEventListener('change', function () {
              rows = [];
              fileList.innerHTML = '';
              summary.textContent = '';
              var files = Array.from(fileInput.files);
              if (!files.length) { btnUpload.disabled = true; return; }

              files.forEach(function (f) {
                var row = document.createElement('div');
                row.className = 'file-row';
                var badge = document.createElement('span');
                setBadge(badge, 'wait', 'Ожидание');
                row.innerHTML =
                  '<span class="file-name" title="' + f.name + '">' + f.name + '</span>' +
                  '<span class="file-meta">' + fmtSize(f.size) + '</span>';
                var statusEl = document.createElement('span');
                statusEl.className = 'file-status';
                statusEl.appendChild(badge);
                row.appendChild(statusEl);
                fileList.appendChild(row);
                rows.push({ file: f, rowEl: row, badgeEl: badge });
              });

              btnUpload.disabled = false;
            });

            btnUpload.addEventListener('click', function () {
              btnUpload.disabled = true;
              btnUpload.textContent = 'Загружается…';
              summary.textContent = '';
              uploadNext(0, { ok: 0, warn: 0, err: 0 });
            });

            function uploadNext(i, counts) {
              if (i >= rows.length) {
                btnUpload.textContent = 'Загрузить ещё';
                btnUpload.disabled = false;
                var parts = [];
                if (counts.ok) parts.push('✓ ' + counts.ok + ' загружено');
                if (counts.warn) parts.push('⚠ ' + counts.warn + ' пропущено');
                if (counts.err) parts.push('✗ ' + counts.err + ' ошибок');
                summary.textContent = parts.join('  ');
                return;
              }

              var item = rows[i];
              setBadge(item.badgeEl, 'upload', 'Загрузка…');
              item.rowEl.scrollIntoView({ block: 'nearest' });

              var fd = new FormData();
              fd.append('file', item.file);

              fetch(BASE + '/api/import/file', { method: 'POST', body: fd })
                .then(function (r) {
                  return r.text().then(function (text) {
                    var data;
                    try {
                      data = text ? JSON.parse(text) : {};
                    } catch (e) {
                      data = { status: 'error', message: text || r.statusText || 'Некорректный ответ сервера' };
                    }
                    if (!r.ok) {
                      data.status = data.status || 'error';
                      data.message = data.message || ('HTTP ' + r.status);
                    }
                    return data;
                  });
                })
                .then(function (data) {
                  setBadge(item.badgeEl, 'process', 'Обработка…');
                  // небольшая пауза чтобы "Обработка" успела мигнуть
                  setTimeout(function () {
                    if (data.status === 'ok') {
                      setBadge(item.badgeEl, 'ok', '✓ ' + (data.type || 'ОК') + ' — ОК');
                      counts.ok++;
                    } else if (data.status === 'warn') {
                      setBadge(item.badgeEl, 'warn', '⚠ ' + (data.message || 'Уже загружено'));
                      counts.warn++;
                    } else {
                      setBadge(item.badgeEl, 'error', '✗ ' + (data.message || 'Ошибка'));
                      counts.err++;
                    }
                    uploadNext(i + 1, counts);
                  }, 200);
                })
                .catch(function (err) {
                  setBadge(item.badgeEl, 'error', '✗ ' + (err.message || 'Ошибка сети'));
                  counts.err++;
                  uploadNext(i + 1, counts);
                });
            }
          })();
        </script>
      </div>
    </section>

    <?php if (!empty($reports)): ?>
      <section class="table-section import-reports" id="reportsSection">
        <div class="table-toolbar">
          <div class="table-info">
            <span class="table-title">Загруженные справки</span>
            <span class="table-sub" id="reportsSub"></span>
          </div>
        </div>
        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th class="import-report-number">#</th>
                <th>Дата справки</th>
                <th>Кол-во вагонов</th>
                <th class="import-report-action"></th>
              </tr>
            </thead>
            <tbody id="reportsTbody"></tbody>
          </table>
          <div id="reportsSentinel" class="reports-sentinel"></div>
        </div>
      </section>
      <script>
        (function () {
          var PAGE = 10;
          var data = <?= json_encode(array_values($reports), JSON_UNESCAPED_UNICODE) ?>;
          var loaded = 0;
          var tbody = document.getElementById('reportsTbody');
          var sentinel = document.getElementById('reportsSentinel');
          var sub = document.getElementById('reportsSub');
          var canDelete = <?= !empty($canDelete) ? 'true' : 'false' ?>;
          var csrf = <?= json_encode($csrf ?? '', JSON_UNESCAPED_UNICODE) ?>;
          var base = <?= json_encode($basePath, JSON_UNESCAPED_UNICODE) ?>;

          function loadMore() {
            var next = Math.min(loaded + PAGE, data.length);
            var html = '';
            for (var i = loaded; i < next; i++) {
              var r = data[i];
              html += '<tr class="row-data"><td>' + (i + 1) + '</td>'
                + '<td>' + esc(String((r.type_reference || '') + ' [' + (r.report_date || '') + ']')) + '</td>'
                + '<td>' + esc(String(r.cnt || 0)) + '</td>';
              html += '<td><div class="report-actions"><a class="report-download" href="' + attr(base + '/import/download?report_dt=' + encodeURIComponent(r.report_key || '') + '&type_reference=' + encodeURIComponent(r.type_reference || '')) + '" title="Скачать справку" aria-label="Скачать справку"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 3h2v10l3.5-3.5 1.4 1.4-5.9 5.9-5.9-5.9 1.4-1.4L11 13V3zM5 19h14v2H5v-2z"/></svg></a>';
              if (canDelete) {
                html += '<form class="report-delete-form" method="post" action="' + attr(base + '/import/delete') + '" onsubmit="return confirm(\'Удалить выбранную справку и все её строки?\')">'
                  + '<input type="hidden" name="csrf_token" value="' + attr(csrf) + '">'
                  + '<input type="hidden" name="report_dt" value="' + attr(r.report_key || '') + '">'
                  + '<input type="hidden" name="type_reference" value="' + attr(r.type_reference || '') + '">'
                  + '<button class="report-delete" type="submit" title="Удалить справку" aria-label="Удалить справку"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3h8l1 2h4v2H3V5h4l1-2zm-2 6h12l-1 12H7L6 9zm3 2v8h2v-8H9zm4 0v8h2v-8h-2z"/></svg></button></form>';
              }
              html += '</div></td></tr>';
            }
            tbody.insertAdjacentHTML('beforeend', html);
            loaded = next;
            sub.textContent = 'Показано ' + loaded + ' из ' + data.length;
            if (loaded >= data.length) observer.disconnect();
          }

          function esc(s) {
            return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
          }
          function attr(s) {
            return esc(String(s)).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
          }

          var observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) loadMore();
          }, { rootMargin: '100px' });

          loadMore();
          observer.observe(sentinel);
        })();
      </script>
    <?php endif; ?>

  </div>
</body>

</html>
