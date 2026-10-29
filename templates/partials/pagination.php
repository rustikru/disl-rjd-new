<?php
declare(strict_types=1);

$paginationPage = max(1, (int) ($paginationPage ?? 1));
$paginationPages = max(1, (int) ($paginationPages ?? 1));
$paginationLabel = (string) ($paginationLabel ?? 'Страницы');

if (!isset($paginationUrl) || !is_callable($paginationUrl) || $paginationPages <= 1) {
    return;
}

$visiblePages = [1, 2, 3, $paginationPage - 1, $paginationPage, $paginationPage + 1, $paginationPages];
$visiblePages = array_values(array_unique(array_filter(
    $visiblePages,
    static fn(int $page): bool => $page >= 1 && $page <= $paginationPages
)));
sort($visiblePages);
?>
<nav class="app-pagination" aria-label="<?= htmlspecialchars($paginationLabel) ?>">
  <a class="app-pagination__link app-pagination__arrow<?= $paginationPage <= 1 ? ' is-disabled' : '' ?>"
     href="<?= htmlspecialchars((string) $paginationUrl(max(1, $paginationPage - 1))) ?>"
     aria-label="Предыдущая страница">‹</a>
  <?php $previousVisiblePage = null; ?>
  <?php foreach ($visiblePages as $visiblePage): ?>
    <?php if ($previousVisiblePage !== null && $visiblePage - $previousVisiblePage > 1): ?>
      <span class="app-pagination__dots" aria-hidden="true">…</span>
    <?php endif; ?>
    <a class="app-pagination__link<?= $visiblePage === $paginationPage ? ' is-active' : '' ?>"
       href="<?= htmlspecialchars((string) $paginationUrl($visiblePage)) ?>"
       <?= $visiblePage === $paginationPage ? 'aria-current="page"' : '' ?>><?= $visiblePage ?></a>
    <?php $previousVisiblePage = $visiblePage; ?>
  <?php endforeach; ?>
  <a class="app-pagination__link app-pagination__arrow<?= $paginationPage >= $paginationPages ? ' is-disabled' : '' ?>"
     href="<?= htmlspecialchars((string) $paginationUrl(min($paginationPages, $paginationPage + 1))) ?>"
     aria-label="Следующая страница">›</a>
</nav>

