<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $reports  [['report_dt' => ..., 'cnt' => ...], ...] */
$appName  = $appName  ?? 'Дислокация РЖД';
$basePath = $basePath ?? '';
$error    = $_GET['error']   ?? '';
$warn     = $_GET['warn']    ?? '';
$success  = $_GET['success'] ?? '';
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
      color: var(--text-muted, #9DA5B0);
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

    .upload-spinner {
      display: none;
      margin-top: 14px;
      font-size: 13px;
      color: var(--text-muted, #9DA5B0);
      align-items: center;
      gap: 8px;
    }

    .upload-spinner.visible {
      display: flex;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .spinner-icon {
      width: 16px;
      height: 16px;
      border: 2px solid #CBD5E1;
      border-top-color: #3B82F6;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      flex-shrink: 0;
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
          <button type="submit" class="btn btn-ghost btn-sm">Выйти</button>
        </form>
      </div>
    </div>
  </header>

  <div class="import-wrap">

    <?php if ($error): ?>
      <div class="alert alert-error"><?= nl2br(htmlspecialchars($error ?? '')) ?></div>
    <?php elseif ($warn): ?>
      <div class="alert alert-warn"><?= nl2br(htmlspecialchars($warn ?? '')) ?></div>
    <?php elseif ($success): ?>
      <div class="alert alert-ok"><?= nl2br(htmlspecialchars($success ?? '')) ?></div>
    <?php endif; ?>

    <section class="table-section" style="margin-bottom:28px">
      <div class="table-toolbar">
        <div class="table-info">
          <span class="table-title">Загрузка справки РЖД (.xlsx)</span>
        </div>
      </div>
      <div style="padding:20px 24px">
        <form method="POST" action="<?= htmlspecialchars($basePath) ?>/import" enctype="multipart/form-data">
          <div style="margin-bottom:18px">
            <label class="filter-label" for="xlsx_file">Файлы справок (можно выбрать несколько)</label>
            <input type="file" id="xlsx_file" name="xlsx_files[]" accept=".xlsx,.xls" multiple>
            <div class="upload-hint" id="fileCount"></div>
          </div>
          <button type="submit" id="btnUpload" class="btn btn-primary">Загрузить</button>
          <div class="upload-spinner" id="uploadSpinner">
            <div class="spinner-icon"></div>
            <span id="uploadSpinnerText">Идёт загрузка, пожалуйста подождите…</span>
          </div>
        </form>
        <script>
          var fileInput = document.getElementById('xlsx_file');
          var fileCount = document.getElementById('fileCount');
          fileInput.addEventListener('change', function () {
            var n = fileInput.files.length;
            fileCount.textContent = n > 0 ? 'Выбрано файлов: ' + n : '';
          });
          document.querySelector('form').addEventListener('submit', function () {
            var n = fileInput.files.length;
            var btn = document.getElementById('btnUpload');
            btn.textContent = 'Загружается…';
            btn.classList.add('btn-loading');
            btn.disabled = true;
            document.getElementById('uploadSpinnerText').textContent =
              'Загружается ' + n + ' ' + (n === 1 ? 'файл' : n < 5 ? 'файла' : 'файлов') + ', пожалуйста подождите…';
            document.getElementById('uploadSpinner').classList.add('visible');
          });
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