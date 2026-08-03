<?php
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Отдельная ручная страница импорта из обработанных писем.
    // Разрешаем только этот файл, не открывая через HTTP всю папку bin.
    if ($path === '/bin/downloads_mail_rjd.php') {
        require __DIR__ . '/bin/downloads_mail_rjd.php';
        return true;
    }

    $file = __DIR__ . '/public' . $path;
    if (is_file($file)) {
        return false;
    }
}
require __DIR__ . '/public/index.php';
