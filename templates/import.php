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
      max-width: 760px;
      margin: 36px auto;
      padding: 0 20px;
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
      margin-top: 6px;
      width: 100%;
      font-size: 14px;
    }

    .btn-loading {
      opacity: 0.65;
      cursor: not-allowed;
      pointer-events: none;
    }

    /* Список файлов */
    .file-list {
      margin: 14px 0 0;
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
      margin-top: 14px;
      font-size: 13px;
      color: #5c6370;
    }
  </style>
</head>

<body>

  <header class="site-header">
    <div class="header-inner">
      <div class="brand">
        <div class="brand-icon">
        </div>
        <div class="brand-text">
          <div class="brand-name">
            <?= htmlspecialchars($appName) ?>
          </div>
          <div class="brand-sub">Загрузка справки РЖД</div>
        </div>
      </div>
      <div class="header-meta">
        <a href="<?= htmlspecialchars($basePath) ?>/" class="btn btn-ghost btn-sm">← На главную</a>
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/logout" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
          <button type="submit" class="btn btn-ghost btn-sm">Выйти</button>
        </form>
      </div>
    </div>
  </header>

  <div class="import-wrap">

    <section class="table-section" style="margin-bottom:28px">
      <div class="table-toolbar">
        <div class="table-info">
          <span class="table-title">Загрузка справки РЖД (.xlsx)</span>
        </div>
      </div>
      <div style="padding:20px 24px">
        <div style="margin-bottom:18px">
          <label class="filter-label" for="xlsx_file">Файлы справок</label>
          <input type="file" id="xlsx_file" accept=".xlsx" multiple>
          <div class="upload-hint">Поддерживаются файлы .xlsx</div>
        </div>

        <div class="file-list" id="fileList"></div>

        <div style="margin-top:14px;display:flex;gap:10px;align-items:center">
          <button id="btnUpload" class="btn btn-primary" disabled>Загрузить</button>
          <div class="upload-summary" id="uploadSummary"></div>
        </div>

        <script>
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
                .then(function (r) { return r.json(); })
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
                  setBadge(item.badgeEl, 'error', '✗ Ошибка сети');
                  counts.err++;
                  uploadNext(i + 1, counts);
                });
            }
          })();
        </script>
      </div>
    </section>

    <?php if (!empty($reports)): ?>
      <section class="table-section">
        <div class="table-toolbar">
          <div class="table-info">
            <span class="table-title">Загруженные справки</span>
          </div>
        </div>
        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th style="width:40px">#</th>
                <th>Дата справки</th>
                <th>Кол-во вагонов</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reports as $i => $r): ?>
                <tr class="row-data">
                  <td><?= $i + 1 ?></td>
                  <td>
                    <?= htmlspecialchars((string) ($r['type_reference'] ?? '') . ' [' . ($r['report_date'] ?? '') . ']') ?>
                  </td>
                  <td><?= htmlspecialchars((string) ($r['cnt'] ?? '0')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

  </div>
</body>

</html>