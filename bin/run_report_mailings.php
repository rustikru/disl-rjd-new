<?php
declare(strict_types=1);

use App\Database\DbFactory;
use App\Reports\MailingStore;
use App\Reports\MailingWorker;

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../src/Config.php';
$db = DbFactory::create($config);
$store = new MailingStore($db);
$queued = $store->queueDue();

if (empty($config['report_mail_enabled'])) {
    fwrite(STDOUT, "queued={$queued}; mail delivery is disabled\n");
    exit(0);
}

$worker = new MailingWorker(
    $db,
    (string) ($config['report_mail_from'] ?? ''),
    (string) ($config['report_storage_dir'] ?? (__DIR__ . '/../storage/reports'))
);
$result = $worker->run(20);
fwrite(STDOUT, sprintf(
    "queued=%d sent=%d skipped=%d errors=%d\n",
    $queued,
    $result['sent'],
    $result['skipped'],
    $result['errors']
));
