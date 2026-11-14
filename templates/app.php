<?php
/** @var string $appName */
/** @var string $basePath */
/** @var array  $user  ['username', 'display_name', 'email', 'auth_source'] */
$basePath = $basePath ?? '';
$activeOrganizationCode = '';
$activeOrganizationName = '';
foreach ($_SESSION['organizations'] ?? [] as $organization) {
  if ((int) ($organization['id'] ?? 0) === (int) ($_SESSION['organization_id'] ?? 0)) {
    $activeOrganizationCode = (string) ($organization['code'] ?? '');
    $activeOrganizationName = (string) (
      ($organization['short_name'] ?? '') ?: ($organization['name'] ?? '')
    );
    break;
  }
}
$isMtf = strtoupper($activeOrganizationCode) === 'MTF';
$reportElementId = static fn(string $report,string $key,string $fallback): string => htmlspecialchars((string)($reportDefinitions[$report]['page'][$key]??$fallback));
$kpiContainer = static fn(string $type): string => htmlspecialchars((string) (($kpiTypes[$type]['container_key'] ?? '')));
?>
<!DOCTYPE html>
<html lang="ru">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> — Дислокация</title>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
  <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css?v=<?= (int) @filemtime(__DIR__ . '/../public/assets/css/app.css') ?>">
  <script>
    window.APP_BASE = '<?= htmlspecialchars($basePath, ENT_QUOTES) ?>';
    window.APP_CSRF = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';
    window.APP_ALLOWED_PAGES = <?= json_encode($allowedPages ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.APP_DISABLED_PAGES = <?= json_encode($disabledPages ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.APP_NAV_CONFIG = <?= json_encode($navConfig ?? [], JSON_UNESCAPED_UNICODE) ?>;
    window.APP_ORGANIZATION_CODE = <?= json_encode($activeOrganizationCode, JSON_UNESCAPED_UNICODE) ?>;
    window.APP_REPORT_DEFINITIONS = <?= json_encode($reportDefinitions ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.APP_PAGE_LAYOUTS = <?= json_encode($pageLayouts ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.APP_KPI_TYPES = <?= json_encode($kpiTypes ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.APP_USER_KPI_MANAGEMENT_ENABLED = <?= !empty($userKpiManagementEnabled) ? 'true' : 'false' ?>;
    window.DISLOCATION_STALE_HOURS = <?= (int) ($dislocationStaleHours ?? 8) ?>;
  </script>
</head>

<body>

  <?php
  $headerSub = '<div id="brandDateSub" class="brand-date-sub"></div>';
  $headerRight = '';
  include __DIR__ . '/partials/header.php';
  ?>

  <div class="app-body">

    <aside class="sidebar" id="sidebar"></aside>

    <main class="main-content">
      <?php
      $pageLayout = ['type' => 'wagon_report'];
      include __DIR__ . '/partials/page-layout.php';
      ?>
    </main>
  </div>

  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/jquery/jquery-3.7.1.min.js"></script>
  <script
    src="<?= htmlspecialchars($basePath) ?>/assets/js/detail-contexts.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/detail-contexts.js') ?>"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/csv-export.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/excel-export.js"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/sortable.min.js"></script>
  <script
    src="<?= htmlspecialchars($basePath) ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/app.js') ?>"></script>
  <script src="<?= htmlspecialchars($basePath) ?>/assets/js/user-kpi.js?v=<?= filemtime(__DIR__ . '/../public/assets/js/user-kpi.js') ?>"></script>
</body>

</html>
