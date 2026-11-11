<?php
declare(strict_types=1);

use App\Controllers\ImportController;
use App\Database\DbFactory;
use App\Services\Organizations;

require dirname(__DIR__) . '/vendor/autoload.php';

$directory = $argv[1] ?? dirname(__DIR__) . '/tmp/mf/rjd';
$organizationId = max(1, (int) (getenv('RJD_ORGANIZATION_ID') ?: 1));
$userId = max(0, (int) (getenv('RJD_USER_ID') ?: 0));

$directory = str_starts_with($directory, DIRECTORY_SEPARATOR)
    ? $directory
    : dirname(__DIR__) . DIRECTORY_SEPARATOR . $directory;
$directory = realpath($directory) ?: $directory;

if (!is_dir($directory)) {
    fwrite(STDERR, "Каталог не найден: {$directory}" . PHP_EOL);
    exit(2);
}

$_SESSION = [
    'organization_id' => $organizationId,
    'user' => ['id' => $userId, 'username' => 'rjd-mail-import'],
];

$config = require dirname(__DIR__) . '/src/Config.php';
$db = DbFactory::create($config);
$organizations = new Organizations($db, $organizationId);
$organizations->sync();
$import = new ImportController($db, $config, $organizations);

$files = glob($directory . DIRECTORY_SEPARATOR . '*.xlsx') ?: [];
sort($files, SORT_NATURAL | SORT_FLAG_CASE);
if ($files === []) {
    fwrite(STDERR, "В каталоге нет XLSX-файлов: {$directory}" . PHP_EOL);
    exit(2);
}

$success = 0;
$errors = 0;
foreach ($files as $file) {
    try {
        $result = $import->importLocalFile($file);
        $state = $result['current_updated']
            ? 'обновлена текущая справка'
            : 'добавлено только в архив';
        if (!unlink($file)) {
            throw new RuntimeException('Справка загружена, но не удалось удалить файл');
        }
        printf(
            "OK  %s: %s, %s, строк %d; %s; файл удалён%s",
            basename($file),
            $result['type'],
            $result['report_dt'],
            $result['rows'],
            $state,
            PHP_EOL
        );
        $success++;
    } catch (Throwable $error) {
        fprintf(STDERR, "ERR %s: %s%s", basename($file), $error->getMessage(), PHP_EOL);
        $errors++;
    }
}

printf("Готово: успешно %d, с ошибкой %d.%s", $success, $errors, PHP_EOL);
exit($errors > 0 ? 1 : 0);
