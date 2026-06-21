#!/usr/bin/env php
<?php
/**
 * Запуск расписания отчётов — вызывается из cron.
 *
 * Пример в crontab (каждые 30 минут):
 *   *\/30 * * * * php /path/to/project/bin/run-reports.php >> /path/to/project/tmp/log/cron.log 2>&1
 *
 * Или для ежечасного запуска:
 *   0 * * * * php /path/to/project/bin/run-reports.php
 *
 * Скрипт сам проверяет, какие расписания «пришло время» выполнить,
 * на основании поля frequency + run_hour/run_minute/day_of_week.
 */
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../src/Config.php';

$db = \App\Database\DbFactory::create($config);

// Текущее время для проверки расписаний
$nowHour    = (int) date('G');   // 0-23
$nowMinute  = (int) date('i');   // 0-59
$nowDow     = (int) date('N');   // 1=Пн .. 7=Вс
$todayStr   = date('Y-m-d');

echo "[" . date('Y-m-d H:i:s') . "] Запуск проверки расписаний...\n";

// Получаем все активные расписания
$schedules = $db->fetchAll(
    "SELECT id, name, report_type, frequency, run_hour, run_minute,
            day_of_week, output_dir,
            TO_CHAR(last_run_at, 'YYYY-MM-DD HH24:MI:SS') AS last_run_at
       FROM xx_rjd_report_schedules
      WHERE is_active = 1
      ORDER BY id"
);

if (empty($schedules)) {
    echo "Активных расписаний нет.\n";
    exit(0);
}

$ran = 0;

foreach ($schedules as $schedule) {
    $id        = (int) $schedule['id'];
    $name      = $schedule['name'];
    $freq      = $schedule['frequency'];
    $runHour   = (int) $schedule['run_hour'];
    $runMinute = (int) $schedule['run_minute'];
    $dow       = $schedule['day_of_week'] !== null ? (int) $schedule['day_of_week'] : null;
    $lastRun   = $schedule['last_run_at'];
    $outputDir = $schedule['output_dir'];

    if (!shouldRun($freq, $runHour, $runMinute, $dow, $lastRun, $nowHour, $nowMinute, $nowDow, $todayStr)) {
        continue;
    }

    echo "[" . date('H:i:s') . "] Выполняю «{$name}» (id={$id}, freq={$freq})...\n";

    try {
        $exporter = new \App\Services\DislocationExcelExporter($db);
        $path     = $exporter->export($outputDir);

        $db->execute(
            "UPDATE xx_rjd_report_schedules SET last_run_at = SYSDATE WHERE id = :id",
            ['id' => $id]
        );

        echo "  ✓ Сохранён: {$path}\n";
        $ran++;
    } catch (\Throwable $e) {
        echo "  ✗ Ошибка: " . $e->getMessage() . "\n";
    }
}

echo "[" . date('H:i:s') . "] Завершено. Выполнено расписаний: {$ran}.\n";

// =========================================================================

/**
 * Проверяет, пришло ли время выполнить данное расписание.
 *
 * daily:   текущий час >= run_hour И текущая минута >= run_minute
 *          И last_run_at < сегодня (не выполнялось сегодня)
 *
 * hourly:  last_run_at < начало текущего часа
 *
 * weekly:  как daily, плюс day_of_week совпадает с текущим
 */
function shouldRun(
    string  $freq,
    int     $runHour,
    int     $runMinute,
    ?int    $dow,
    ?string $lastRun,
    int     $nowHour,
    int     $nowMinute,
    int     $nowDow,
    string  $todayStr
): bool {
    switch ($freq) {
        case 'hourly':
            // Не выполнялось в текущем часу
            if ($lastRun === null) {
                return true;
            }
            $lastHour = date('Y-m-d H', strtotime($lastRun));
            $curHour  = date('Y-m-d H');
            return $lastHour < $curHour;

        case 'daily':
            // Не выполнялось сегодня И прошло время запуска
            if ($nowHour < $runHour) {
                return false;
            }
            if ($nowHour === $runHour && $nowMinute < $runMinute) {
                return false;
            }
            if ($lastRun === null) {
                return true;
            }
            return substr($lastRun, 0, 10) < $todayStr;

        case 'weekly':
            // Совпадает день недели И не выполнялось сегодня И прошло время
            if ($dow !== null && $dow !== $nowDow) {
                return false;
            }
            if ($nowHour < $runHour) {
                return false;
            }
            if ($nowHour === $runHour && $nowMinute < $runMinute) {
                return false;
            }
            if ($lastRun === null) {
                return true;
            }
            return substr($lastRun, 0, 10) < $todayStr;

        default:
            return false;
    }
}
