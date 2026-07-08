<?php
declare(strict_types=1);

$config = require __DIR__ . '/../src/Config.php';
$fileName = $argv[1] ?? '';

if ($fileName === '' || !is_file($fileName)) {
    fwrite(STDERR, "SQL file not found\n");
    exit(1);
}

$dsn = sprintf('//%s:%s/%s', $config['db_host'], $config['db_port'] ?: '1521', $config['db_name']);
$connection = oci_connect((string) $config['db_user'], (string) $config['db_pass'], $dsn, 'AL32UTF8');
if (!$connection) {
    fwrite(STDERR, (oci_error()['message'] ?? 'Oracle connection error') . PHP_EOL);
    exit(1);
}

$sqlText = file_get_contents($fileName);
$statements = [];
$buffer = [];

foreach (preg_split('/\R/u', (string) $sqlText) as $line) {
    if (trim($line) === '/') {
        $statement = trim(implode(PHP_EOL, $buffer));
        if ($statement !== '') {
            $statements[] = $statement;
        }
        $buffer = [];
        continue;
    }

    $buffer[] = $line;
}

$statement = trim(implode(PHP_EOL, $buffer));
if ($statement !== '') {
    $statements[] = $statement;
}

foreach ($statements as $statementNumber => $statementText) {
    $statement = oci_parse($connection, $statementText);
    if (!$statement || !oci_execute($statement)) {
        $error = $statement ? oci_error($statement) : oci_error($connection);
        fwrite(STDERR, 'Statement ' . ($statementNumber + 1) . ': ' . ($error['message'] ?? 'unknown error') . PHP_EOL);
        exit(1);
    }
    oci_free_statement($statement);
    echo 'Executed statement ' . ($statementNumber + 1) . PHP_EOL;
}

$check = oci_parse(
    $connection,
    "select type, line, position, text
       from user_errors
      where name = 'XX_RJD_DISLOCATION_NEW_PKG'
      order by type, sequence"
);
oci_execute($check);

$hasErrors = false;
while (($row = oci_fetch_assoc($check)) !== false) {
    $hasErrors = true;
    echo $row['TYPE'] . ' line ' . $row['LINE'] . ':' . $row['POSITION'] . ' ' . $row['TEXT'] . PHP_EOL;
}

exit($hasErrors ? 1 : 0);
