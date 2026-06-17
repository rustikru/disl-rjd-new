<?php
/**
 * Полный цикл: скачать ВСЕ станции с raillogistic.ru -> очистить таблицу -> залить в Oracle.
 *
 * Почему так:
 *  - URL-фильтр ?type=станция на сайте ломается в паре с &page (сервер отдаёт всю базу),
 *    поэтому качаем ПОЛНЫЙ список постранично (без фильтра по типу),
 *    а нужный тип "станция" отсеиваем уже здесь, в PHP.
 *  - Это гарантирует, что мы доходим до конца алфавита (Поточино, Углеуральская и т.д.).
 */

set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

/* ============================ КОНФИГ ============================ */
$username = 'xx_etw';
$password = 'xx_etw123';
$connection_string = '//127.0.0.1:1521/FREEPDB1';

$baseUrl = 'https://raillogistic.ru/stations.php';
$onlyType = 'станция';   // какой тип оставляем; '' = брать все типы
$maxPages = 300;         // страховка от бесконечного цикла (реально страниц ~248)
$pauseMs = 150;         // пауза между запросами, мс (вежливость к сайту)
$jsonBackup = 'railway_stations.json'; // куда сохранить дамп на всякий случай
/* =============================================================== */

echo "=== Старт: скрапинг raillogistic.ru + импорт в Oracle ===\n";

/* ---------- 1. Скачиваем одну страницу ---------- */
function downloadPage(string $baseUrl, int $page): ?string
{
    $url = $baseUrl . '?page=' . $page;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_ENCODING => '', // принимаем gzip
        CURLOPT_HTTPHEADER => ['Accept-Charset: utf-8'],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) ' .
            'AppleWebKit/537.36 (KHTML, like Gecko) ' .
            'Chrome/124.0 Safari/537.36',
    ]);

    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    // curl_close() не нужен: с PHP 8.0 он ничего не делает, а в 8.5 помечен устаревшим.

    if ($html === false || $code !== 200) {
        echo "  ! Ошибка загрузки страницы $page (HTTP $code) $err\n";
        return null;
    }
    return $html;
}

/* ---------- 2. Парсим таблицу станций со страницы ---------- */
function parseRows(string $html): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // навязываем UTF-8, иначе кириллица поедет
    $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tables = $xpath->query('//table');

    // выбираем таблицу, в шапке которой есть "Код ЕСР"
    $target = null;
    foreach ($tables as $tbl) {
        if (mb_strpos($tbl->textContent, 'Код ЕСР') !== false) {
            $target = $tbl;
            break;
        }
    }
    if ($target === null) {
        return [];
    }

    $rows = [];
    $trList = $xpath->query('.//tr', $target);
    foreach ($trList as $tr) {
        $tds = $xpath->query('./td', $tr);
        if ($tds->length < 6) {
            continue; // это строка-заголовок (th) или служебная
        }

        $cells = [];
        foreach ($tds as $td) {
            $cells[] = trim($td->textContent);
        }

        // нормализуем "—" / "-" в пустую строку
        $clean = static function (string $v): string {
            $v = trim($v);
            return ($v === '—' || $v === '-' || $v === '') ? '' : $v;
        };

        $esr = $clean($cells[0]);
        if ($esr === '') {
            continue;
        }

        $rows[] = [
            'esr_code' => $esr,
            'station_name' => $clean($cells[1]),
            'station_type' => $clean($cells[2]),
            'railway_name' => $clean($cells[3]),
            'region_name' => $clean($cells[4]),
            'coordinates' => $clean($cells[5]),
        ];
    }
    return $rows;
}

/* ---------- 3. Перебираем все страницы ---------- */
$stations = [];   // дедуп по ЕСР: ключ = esr_code
$prevFirstEsr = null;
$page = 1;

echo "Скачивание справочника...\n";

while ($page <= $maxPages) {
    $html = downloadPage($baseUrl, $page);
    if ($html === null) {
        // одна сетевая ошибка — пробуем ещё раз, потом сдаёмся
        usleep(500000);
        $html = downloadPage($baseUrl, $page);
        if ($html === null) {
            echo "Прерывание на странице $page из-за ошибки сети.\n";
            break;
        }
    }

    $rows = parseRows($html);

    if (empty($rows)) {
        echo "Страница $page пустая — считаем, что список закончился.\n";
        break;
    }

    // ЗАЩИТА: проверяем, что страница реально сменилась
    $firstEsr = $rows[0]['esr_code'];
    if ($firstEsr === $prevFirstEsr) {
        echo "СТОП: страница $page повторяет предыдущую (ЕСР $firstEsr).\n";
        echo "Пагинация ?page=N не перематывает — сайт отдаёт то же самое.\n";
        echo "Нужно менять способ перехода между страницами.\n";
        break;
    }
    $prevFirstEsr = $firstEsr;

    // фильтруем по типу и складываем с дедупом по ЕСР
    $added = 0;
    foreach ($rows as $r) {
        if ($onlyType !== '' && $r['station_type'] !== $onlyType) {
            continue;
        }
        $stations[$r['esr_code']] = $r; // перезапись дубля = ок
        $added++;
    }

    echo "Стр. $page: строк на странице " . count($rows) .
        ", подходящих (тип «{$onlyType}») +$added, всего собрано " . count($stations) . "\n";

    $page++;
    if ($pauseMs > 0) {
        usleep($pauseMs * 1000);
    }
}

$stations = array_values($stations);
$total = count($stations);
echo "Скачивание завершено. Уникальных станций собрано: $total\n";

if ($total === 0) {
    die("Нечего импортировать — список пуст. Проверьте сеть/доступ к сайту.\n");
}

// сохраняем дамп на всякий случай (необязательно, но удобно)
file_put_contents(
    $jsonBackup,
    json_encode($stations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);
echo "Резервная копия данных сохранена в $jsonBackup\n";

/* ---------- 4. Подключаемся к Oracle ---------- */
$conn = oci_connect($username, $password, $connection_string, 'AL32UTF8');
if (!$conn) {
    $e = oci_error();
    die("Ошибка подключения к Oracle: " . $e['message'] . "\n");
}
echo "Подключение к Oracle успешно.\n";

/* ---------- 5. ОЧИЩАЕМ таблицу перед загрузкой ---------- */
// TRUNCATE — быстро и сразу фиксируется (это DDL).
// Если нет прав на TRUNCATE, замените на: DELETE FROM xx_rjd_stations
echo "Очистка таблицы xx_rjd_stations (TRUNCATE)...\n";
$truncate = oci_parse($conn, "TRUNCATE TABLE xx_rjd_stations");
if (!oci_execute($truncate)) {
    $e = oci_error($truncate);
    die("Ошибка очистки таблицы: " . $e['message'] . "\n");
}
echo "Таблица очищена.\n";

/* ---------- 6. Готовим INSERT ---------- */
$sql = "INSERT INTO xx_rjd_stations
            (esr_code, station_name, station_type, railway_name, region_name, coordinates)
        VALUES (:esr, :name, :type, :railway, :region, :coord)";

$stmt = oci_parse($conn, $sql);
if (!$stmt) {
    $e = oci_error($conn);
    die("Ошибка компиляции SQL: " . $e['message'] . "\n");
}

$esr = $name = $type = $railway = $region = $coord = '';
$max_len = 400;

oci_bind_by_name($stmt, ':esr', $esr, $max_len);
oci_bind_by_name($stmt, ':name', $name, $max_len);
oci_bind_by_name($stmt, ':type', $type, $max_len);
oci_bind_by_name($stmt, ':railway', $railway, $max_len);
oci_bind_by_name($stmt, ':region', $region, $max_len);
oci_bind_by_name($stmt, ':coord', $coord, $max_len);

$importedCount = 0;
$skipped = 0;
$startTime = microtime(true);

echo "Вставка строк в базу...\n";

foreach ($stations as $s) {
    $esr = $s['esr_code'];
    $name = $s['station_name'];
    $type = $s['station_type'];
    $railway = $s['railway_name'];
    $region = $s['region_name'];
    $coord = $s['coordinates'];

    if ($esr === '' || $name === '') {
        $skipped++;
        continue;
    }

    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($stmt);
        echo "  ! Ошибка вставки (ЕСР $esr): " . $e['message'] . "\n";
    } else {
        $importedCount++;
    }

    if ($importedCount % 1000 === 0 && $importedCount > 0) {
        echo "  ...вставлено $importedCount\n";
    }
}

echo "Фиксация (COMMIT)...\n";
oci_commit($conn);

oci_free_statement($stmt);
oci_close($conn);

$totalTime = round(microtime(true) - $startTime, 2);
echo "=== Готово ===\n";
echo "Добавлено в БД:        $importedCount\n";
echo "Пропущено (пустые):    $skipped\n";
echo "Время вставки:         {$totalTime} сек.\n";