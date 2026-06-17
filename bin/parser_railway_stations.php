<?php
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);
set_time_limit(0);

// Имя файла
$jsonFile = 'railway_stations.json';

$url = 'https://raillogistic.ru/stations.php?q=&railway=&region=&type=станция';

echo "=== Запуск точечного парсинга ЖД станций (GET-фильтр + POST-пагинация) ===\n";

// Функция для отправки запроса
function getPageHtml($url, $page)
{

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);


    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'page' => $page
    ]));

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Content-Type: application/x-www-form-urlencoded',
        'Referer: ' . $url
    ]);

    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

echo "Проверяем количество страниц для фильтра... ";
$firstPageHtml = getPageHtml($url, 1);

if (!$firstPageHtml) {
    die("ОШИБКА: Не удалось загрузить первую страницу.\n");
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $firstPageHtml);
libxml_clear_errors();
$xpath = new DOMXPath($dom);

$totalPages = 1;
$paginationLinks = $xpath->query("//ul[contains(@class, 'pagination')]//a");
if ($paginationLinks->length > 0) {
    foreach ($paginationLinks as $link) {
        $text = trim($link->nodeValue);
        if (is_numeric($text) && (int) $text > $totalPages) {
            $totalPages = (int) $text;
        }
    }
}
echo "Найдено страниц: $totalPages\n";

$allStations = [];
//$totalPages = 109;

for ($page = 1; $page <= $totalPages; $page++) {
    echo "[Страница $page/$totalPages] Получение данных... ";

    $html = ($page === 1) ? $firstPageHtml : getPageHtml($url, $page);

    if (!$html) {
        echo "ОШИБКА загрузки. Пропускаем.\n";
        continue;
    }

    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $rows = $xpath->query('//table//tr[td]');
    $savedCount = 0;

    foreach ($rows as $row) {
        $cells = $row->getElementsByTagName('td');

        if ($cells->length >= 5) {
            $esr = trim($cells->item(0)->nodeValue);
            $name = trim($cells->item(1)->nodeValue);
            $type = trim($cells->item(2)->nodeValue);
            $railway = trim($cells->item(3)->nodeValue);
            $region = trim($cells->item(4)->nodeValue);

            $coordinates = ($cells->length >= 6) ? trim($cells->item(5)->nodeValue) : '';

            if ((empty($esr) && empty($name)) || $esr === 'Код ЕСР') {
                continue;
            }

            $latitude = '';
            $longitude = '';

            if (!empty($coordinates) && $coordinates !== '—') {
                $coordsArray = explode(',', $coordinates);
                if (count($coordsArray) === 2) {
                    $latitude = trim($coordsArray[0]);
                    $longitude = trim($coordsArray[1]);
                }
            }

            $allStations[] = [
                'esr_code' => $esr,
                'station_name' => $name,
                'station_type' => $type,
                'railway_name' => $railway,
                'region_name' => $region,
                'latitude' => $latitude,
                'longitude' => $longitude
            ];

            $savedCount++;
        }
    }

    echo "Успешно. Собрано строк: $savedCount\n";
    usleep(300000); // Пауза 0.3 сек
}

echo "Запись данных в JSON файл...\n";
$jsonResult = json_encode($allStations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if (file_put_contents($jsonFile, $jsonResult)) {
    echo "=== Парсинг успешно завершен! ===\n";
    echo "Всего отфильтрованных станций сохранено: " . count($allStations) . "\n";
} else {
    echo "Ошибка записи файла $jsonFile\n";
}