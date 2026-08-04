<?php
declare(strict_types=1);

use App\Database\DbFactory;
use App\Reports\MailingData;
use App\Reports\MailingSender;

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../src/Config.php';
$db = DbFactory::create($config);
$mailings = new MailingData($db);
$queued = $mailings->queueDue();

if (empty($config['report_mail_enabled'])) {
    fwrite(STDOUT, "queued={$queued}; mail delivery is disabled\n");
    exit(0);
}

$sender = new MailingSender(
    $db,
    (string) ($config['report_storage_dir'] ?? (__DIR__ . '/../storage/reports')),
    !empty($config['report_mail_test'])
);
$result = $sender->runAll();
fwrite(STDOUT, sprintf(
    "queued=%d sent=%d skipped=%d errors=%d\n",
    $queued,
    $result['sent'],
    $result['skipped'],
    $result['errors']
));
