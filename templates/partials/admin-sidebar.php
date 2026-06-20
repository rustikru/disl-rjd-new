<?php
/**
 * Боковая панель раздела администрирования.
 * Переменные из вызывающего шаблона:
 *   $basePath         (string) — базовый путь
 *   $activeAdminPage  (string) — ключ активной страницы: 'users' | 'roles' | ...
 *
 * Для добавления нового пункта admin-меню — добавить строку в $adminNavItems ниже.
 */
$activeAdminPage = $activeAdminPage ?? '';

$adminNavItems = [
    ['key' => 'users', 'label' => 'Пользователи', 'url' => $basePath . '/admin/users'],
    ['key' => 'roles', 'label' => 'Роли',         'url' => $basePath . '/admin/roles'],
];
?>
<aside class="sidebar">
  <div class="nav-group">
    <span class="nav-group-label">Администрирование</span>
    <?php foreach ($adminNavItems as $item): ?>
      <a class="nav-item<?= $activeAdminPage === $item['key'] ? ' active' : '' ?>"
         href="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars($item['label']) ?></a>
    <?php endforeach; ?>
  </div>
</aside>
