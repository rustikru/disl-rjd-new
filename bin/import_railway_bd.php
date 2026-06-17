<?php
// Отменяем лимит времени выполнения скрипта
set_time_limit(0);

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Конфигурация подключения к Oracle
$username = 'xx_etw';
$password = 'xx_etw123';
$connection_string = '//127.0.0.1:1521/FREEPDB1';
$jsonFile = 'railway_stations.json';

echo "=== Запуск импорта JSON в Oracle DB (через INSERT) ===\n";

// 1. Устанавливаем соединение с кодировкой AL32UTF8
$conn = oci_connect($username, $password, $connection_string, 'AL32UTF8');

if (!$conn) {
    $e = oci_error();
    die("Ошибка подключения к Oracle: " . $e['message'] . "\n");
}
echo "Успешное подключение к базе данных.\n";

// 2. Читаем и декодируем JSON-файл
if (!file_exists($jsonFile)) {
    die("Ошибка: Файл $jsonFile не найден.\n");
}

$jsonData = file_get_contents($jsonFile);
$stations = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die("Ошибка декодирования JSON: " . json_last_error_msg() . "\n");
}

echo "Файл JSON успешно прочитан. Найдено записей для обработки: " . count($stations) . "\n";

// 3. Подготавливаем обычный SQL-запрос INSERT
// Здесь каждый плейсхолдер встречается ровно ОДИН раз, что идеально для OCI8
$sql = "INSERT INTO xx_rjd_stations (esr_code, station_name, station_type, railway_name, region_name, coordinates)
        VALUES (:esr, :name, :type, :railway, :region, :coord)";

$stmt = oci_parse($conn, $sql);

if (!$stmt) {
    $e = oci_error($conn);
    die("Ошибка компиляции SQL: " . $e['message'] . "\n");
}

// Инициализируем переменные
$esr = $name = $type = $railway = $region = $coord = '';
$max_len = 400; // Буфер памяти для стабильной привязки строк

// Привязываем переменные к плейсхолдерам (всего 6 привязок, так как нет дублей)
oci_bind_by_name($stmt, ':esr', $esr, $max_len);
oci_bind_by_name($stmt, ':name', $name, $max_len);
oci_bind_by_name($stmt, ':type', $type, $max_len);
oci_bind_by_name($stmt, ':railway', $railway, $max_len);
oci_bind_by_name($stmt, ':region', $region, $max_len);
oci_bind_by_name($stmt, ':coord', $coord, $max_len);

$importedCount = 0;
$startTime = microtime(true);

echo "Начало вставки строк в базу данных...\n";

// 4. Перебираем массив
foreach ($stations as $index => $station) {
    $esr = isset($station['esr_code']) ? trim($station['esr_code']) : '';
    $name = isset($station['station_name']) ? trim($station['station_name']) : '';
    $type = isset($station['station_type']) ? trim($station['station_type']) : '';
    $railway = isset($station['railway_name']) ? trim($station['railway_name']) : '';
    $region = isset($station['region_name']) ? trim($station['region_name']) : '';
    $coord = isset($station['coordinates']) ? trim($station['coordinates']) : '';

    // Пропускаем пустые строки
    if (empty($esr) || empty($name)) {
        continue;
    }

    // Выполняем запрос без авто-коммита для максимальной скорости
    $result = oci_execute($stmt, OCI_NO_AUTO_COMMIT);

    if (!$result) {
        $e = oci_error($stmt);
        echo "Ошибка вставки на индексе JSON [$index] (ЕСР $esr): " . $e['message'] . "\n";
    } else {
        $importedCount++;
    }

    if ($importedCount % 1000 === 0) {
        echo "Вставлено строк: $importedCount...\n";
    }
}

// 5. Фиксируем транзакцию (все 12400 строк запишутся одним махом)
echo "Фиксация изменений (COMMIT)...\n";
oci_commit($conn);

oci_free_statement($stmt);
oci_close($conn);

$totalTime = round(microtime(true) - $startTime, 2);
echo "=== Импорт через INSERT успешно завершен! ===\n";
echo "Всего записей добавлено в БД: $importedCount\n";
echo "Затраченное время: $totalTime сек.\n";