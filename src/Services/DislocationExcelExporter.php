<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Генерирует Excel-файл "Сводная дислокация АО Метафракс Кемикалс".
 *
 * Структура идентична сводной таблице на странице Дислокация:
 *   строки  — Страна → Дорога → Станция назначения
 *   колонки — Итого (гр./пор.) + каждый тип вагона (гр./пор.)
 */
class DislocationExcelExporter
{
    private const WAG_TYPE_EXPR    = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code)";
    private const CARGO_STATE_EXPR = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_get_state_wagon(cargo_weight_kg)";

    // Цвета (ARGB)
    private const CLR_HEADER   = 'FFE8E4FF'; // заголовок таблицы
    private const CLR_COUNTRY  = 'FFF0EDFF'; // страна назначения
    private const CLR_ROAD     = 'FFF9F8FF'; // дорога
    private const CLR_STATION  = 'FFFFFFFF'; // станция
    private const CLR_TOTAL    = 'FFD8D2FF'; // строка Итого
    private const CLR_BORDER   = 'FFD4CFEE'; // цвет рамок

    public function __construct(private DbInterface $db) {}

    /**
     * Генерирует .xlsx-файл и сохраняет его в $outputDir.
     *
     * @param  string $outputDir  Путь к директории (создаётся автоматически)
     * @param  string $subtitle   Подзаголовок (название компании)
     * @return string             Полный путь к созданному файлу
     */
    public function export(string $outputDir, string $subtitle = 'АО Метафракс Кемикалс'): string
    {
        // 1. Актуальные даты справок
        $dtsByType = $this->getLatestDtsByType(['Подход', 'Отправка']);
        $reportDtLabel = !empty($dtsByType)
            ? date('d.m.Y H:i', strtotime((string) max($dtsByType)))
            : '—';

        // 2. Сырые строки из БД
        $rawRows = $this->fetchRows($dtsByType);

        // 3. Индексируем уникальные типы вагонов и состояния груза
        [$wagTypes, $cargoStates, $wagIdx, $csIdx] = $this->collectDims($rawRows);

        $nCs   = max(1, count($cargoStates));
        $nWag  = count($wagTypes);
        $nFlat = $nWag * $nCs;

        // 4. Строим 3-уровневое дерево: страна → дорога → станция
        $tree = $this->buildTree($rawRows, $wagIdx, $csIdx, $nFlat);

        // 5. Генерируем Excel
        $spreadsheet = $this->buildSpreadsheet(
            $tree, $wagTypes, $cargoStates, $nFlat, $subtitle, $reportDtLabel
        );

        // 6. Сохраняем
        @mkdir($outputDir, 0777, true);
        $filename = 'disl_summary_' . date('Y-m-d_H-i') . '.xlsx';
        $path = rtrim($outputDir, '/') . '/' . $filename;
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    // =========================================================================
    // БД
    // =========================================================================

    private function getLatestDtsByType(array $types): array
    {
        $placeholders = implode(',', array_map(fn($i) => ":t$i", array_keys($types)));
        $params = [];
        foreach ($types as $i => $t) {
            $params["t$i"] = $t;
        }
        $rows = $this->db->fetchAll(
            "SELECT type_reference, MAX(report_dt) AS dt
               FROM xx_dislocation_rjd
              WHERE type_reference IN ($placeholders)
              GROUP BY type_reference",
            $params
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['type_reference']] = (string) $r['dt'];
        }
        return $map;
    }

    private function fetchRows(array $dtsByType): array
    {
        if (empty($dtsByType)) {
            return [];
        }

        $parts = [];
        $bindings = [];
        $i = 0;
        foreach ($dtsByType as $type => $dt) {
            $parts[] = "(type_reference = :ldt_type_{$i} AND report_dt = TO_DATE(:ldt_dt_{$i}, 'YYYY-MM-DD HH24:MI:SS'))";
            $bindings["ldt_type_{$i}"] = $type;
            $bindings["ldt_dt_{$i}"]   = $dt;
            $i++;
        }
        $where = '(' . implode(' OR ', $parts) . ')';

        $wt = self::WAG_TYPE_EXPR;
        $cs = self::CARGO_STATE_EXPR;

        return $this->db->fetchAll(
            "SELECT NVL(dest_state, 'Не указана')   AS dest_state,
                    NVL(dest_road,  'Не указана')   AS dest_road,
                    NVL(dest_station,'Не указана')  AS dest_station,
                    $wt AS wt,
                    $cs AS cs,
                    COUNT(*) AS cnt
               FROM (SELECT * FROM xx_dislocation_rjd WHERE $where)
              GROUP BY dest_state, dest_road, dest_station, $wt, $cs
              ORDER BY dest_state, dest_road, dest_station, $wt, $cs",
            $bindings
        );
    }

    // =========================================================================
    // Построение структуры
    // =========================================================================

    /** Возвращает [wagTypes[], cargoStates[], wagIdx[], csIdx[]] */
    private function collectDims(array $rows): array
    {
        $wagIdx = [];
        $wagTypes = [];
        $csIdx = [];
        $cargoStates = [];

        foreach ($rows as $r) {
            $wt = (string) ($r['wt'] ?? '');
            $cs = (string) ($r['cs'] ?? '');

            if ($wt !== '' && !isset($wagIdx[$wt])) {
                $wagIdx[$wt] = count($wagTypes);
                $wagTypes[]  = $wt;
            }
            if ($cs !== '' && !isset($csIdx[$cs])) {
                $csIdx[$cs]   = count($cargoStates);
                $cargoStates[] = $cs;
            }
        }

        return [$wagTypes, $cargoStates, $wagIdx, $csIdx];
    }

    /**
     * tree[state]['v']                                  — вектор страны
     * tree[state]['roads'][road]['v']                   — вектор дороги
     * tree[state]['roads'][road]['stations'][sta]       — вектор станции
     */
    private function buildTree(array $rows, array $wagIdx, array $csIdx, int $nFlat): array
    {
        $nCs  = max(1, count($csIdx));
        $tree = [];

        foreach ($rows as $r) {
            $state = (string) ($r['dest_state'] ?? 'Не указана');
            $road  = (string) ($r['dest_road']  ?? 'Не указана');
            $sta   = (string) ($r['dest_station'] ?? 'Не указана');
            $wt    = (string) ($r['wt'] ?? '');
            $cs    = (string) ($r['cs'] ?? '');
            $cnt   = (int) $r['cnt'];

            if (!isset($wagIdx[$wt]) || !isset($csIdx[$cs])) {
                continue;
            }

            $flatIdx = $wagIdx[$wt] * $nCs + $csIdx[$cs];

            if (!isset($tree[$state])) {
                $tree[$state] = ['v' => array_fill(0, $nFlat, 0), 'roads' => []];
            }
            if (!isset($tree[$state]['roads'][$road])) {
                $tree[$state]['roads'][$road] = ['v' => array_fill(0, $nFlat, 0), 'stations' => []];
            }
            if (!isset($tree[$state]['roads'][$road]['stations'][$sta])) {
                $tree[$state]['roads'][$road]['stations'][$sta] = array_fill(0, $nFlat, 0);
            }

            $tree[$state]['v'][$flatIdx]                                    += $cnt;
            $tree[$state]['roads'][$road]['v'][$flatIdx]                    += $cnt;
            $tree[$state]['roads'][$road]['stations'][$sta][$flatIdx]       += $cnt;
        }

        return $tree;
    }

    // =========================================================================
    // Генерация Excel
    // =========================================================================

    private function buildSpreadsheet(
        array $tree,
        array $wagTypes,
        array $cargoStates,
        int $nFlat,
        string $subtitle,
        string $reportDtLabel
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сводная дислокация');

        $nCs  = max(1, count($cargoStates));
        $nWag = count($wagTypes);

        // Колонки: A = label, B..B+nCs-1 = Итого, затем nWag*nCs колонок по типам вагонов
        $totalCols = 1 + $nCs + $nWag * $nCs;   // A + Итого[nCs] + типы[nWag*nCs]
        $lastCol   = $this->colLetter($totalCols);

        // --- Заголовок ---
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', "Сводная дислокация {$subtitle}");
        $this->style($sheet, "A1", [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF2D1A6B']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->setCellValue('A2',
            "Дислокация РЖД на {$reportDtLabel}    •    Сформировано: " . date('d.m.Y H:i')
        );
        $this->style($sheet, 'A2', [
            'font' => ['size' => 10, 'color' => ['argb' => 'FF888888']],
        ]);

        // --- Двухуровневый заголовок таблицы (строки 4-5) ---

        $sheet->setCellValue('A4', 'Страна / Дорога / Станция назначения');
        $sheet->setCellValue('A5', '');

        // "Итого" header
        $fromC = $this->colLetter(2);
        $toC   = $this->colLetter(1 + $nCs);
        if ($nCs > 1) {
            $sheet->mergeCells("{$fromC}4:{$toC}4");
        }
        $sheet->setCellValue("{$fromC}4", 'Итого');

        // cargo state sub-headers for "Итого"
        for ($ci = 0; $ci < $nCs; $ci++) {
            $c = $this->colLetter(2 + $ci);
            $sheet->setCellValue("{$c}5", $this->csShort($cargoStates[$ci] ?? ''));
        }

        // Per wagon type headers
        for ($wi = 0; $wi < $nWag; $wi++) {
            $fromC = $this->colLetter(2 + $nCs + $wi * $nCs);
            $toC   = $this->colLetter(2 + $nCs + $wi * $nCs + $nCs - 1);
            if ($nCs > 1) {
                $sheet->mergeCells("{$fromC}4:{$toC}4");
            }
            $sheet->setCellValue("{$fromC}4", $wagTypes[$wi]);

            for ($ci = 0; $ci < $nCs; $ci++) {
                $c = $this->colLetter(2 + $nCs + $wi * $nCs + $ci);
                $sheet->setCellValue("{$c}5", $this->csShort($cargoStates[$ci] ?? ''));
            }
        }

        $hStyle = [
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::CLR_HEADER]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ];
        $this->style($sheet, "A4:{$lastCol}5", $hStyle);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension(4)->setRowHeight(22);
        $sheet->getRowDimension(5)->setRowHeight(16);

        $sheet->freezePane('A6');

        // --- Данные ---
        $dataRow = 6;
        $grandV  = array_fill(0, $nFlat, 0);

        foreach ($tree as $state => $stateData) {
            $stateRow = $dataRow;
            $stateV   = $stateData['v'];
            for ($k = 0; $k < $nFlat; $k++) {
                $grandV[$k] += $stateV[$k];
            }

            // Строки дорог и станций
            foreach ($stateData['roads'] as $road => $roadData) {
                $roadRow = $dataRow;
                $roadV   = $roadData['v'];

                foreach ($roadData['stations'] as $sta => $staV) {
                    $this->writeDataRow(
                        $sheet, $dataRow, "    {$sta}", $staV,
                        $nCs, $nWag, $nFlat, self::CLR_STATION, false
                    );
                    $dataRow++;
                }

                // Подитог по дороге
                $this->writeDataRow(
                    $sheet, $dataRow, "  {$road}",
                    $roadV, $nCs, $nWag, $nFlat, self::CLR_ROAD, true
                );
                // Вставляем строку дороги ПОСЛЕ строк станций — переставляем через setRowIndex нельзя,
                // поэтому пишем заголовок дороги ПЕРЕД станциями: перепишем в обратном порядке через insertNewRowBefore
                // Проще: переставим в нужный порядок — road, затем stations
                // → reset подхода: пишем road-строку до stations-строк

                // Нет, проще сначала сбросить dataRow к roadRow, вставить перед stations:
                // Такой подход потребует копирования. Проще писать в правильном порядке сразу.
                $dataRow++;
            }

            // Строка страны идёт перед дорогами — нам нужен правильный порядок.
            // Текущий порядок: станции → дороги → страна (снизу вверх). Нужен обратный.
            // Исправляем ниже через буфер.
        }

        // Сбросим и перепишем правильно
        // Очистим заполненное и перестроим
        $sheet->removeRow(6, $dataRow - 6);
        $dataRow = 6;
        $grandV  = array_fill(0, $nFlat, 0);

        foreach ($tree as $state => $stateData) {
            $stateV = $stateData['v'];
            for ($k = 0; $k < $nFlat; $k++) {
                $grandV[$k] += $stateV[$k];
            }

            // Сначала строка страны
            $this->writeDataRow(
                $sheet, $dataRow, $state,
                $stateV, $nCs, $nWag, $nFlat, self::CLR_COUNTRY, true
            );
            $dataRow++;

            foreach ($stateData['roads'] as $road => $roadData) {
                // Строка дороги
                $this->writeDataRow(
                    $sheet, $dataRow, "  {$road}",
                    $roadData['v'], $nCs, $nWag, $nFlat, self::CLR_ROAD, true
                );
                $dataRow++;

                // Строки станций
                foreach ($roadData['stations'] as $sta => $staV) {
                    $this->writeDataRow(
                        $sheet, $dataRow, "    {$sta}",
                        $staV, $nCs, $nWag, $nFlat, self::CLR_STATION, false
                    );
                    $dataRow++;
                }
            }
        }

        // --- Итоговая строка ---
        $this->writeDataRow(
            $sheet, $dataRow, 'ИТОГО',
            $grandV, $nCs, $nWag, $nFlat, self::CLR_TOTAL, true
        );
        $sheet->getStyle("A{$dataRow}")->getFont()->setSize(11);
        $dataRow++;

        // --- Рамки таблицы ---
        $this->style($sheet, "A4:{$lastCol}" . ($dataRow - 1), [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['argb' => self::CLR_BORDER],
                ],
            ],
        ]);

        // --- Ширина колонок ---
        $sheet->getColumnDimension('A')->setWidth(52);
        for ($ci = 2; $ci <= $totalCols; $ci++) {
            $sheet->getColumnDimension($this->colLetter($ci))->setWidth(7.5);
        }

        $spreadsheet->getProperties()
            ->setTitle("Сводная дислокация {$subtitle}")
            ->setCreator('АО Метафракс Кемикалс');

        return $spreadsheet;
    }

    /** Записывает одну строку данных (страна / дорога / станция / итого). */
    private function writeDataRow(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        int $row,
        string $label,
        array $v,
        int $nCs,
        int $nWag,
        int $nFlat,
        string $fillColor,
        bool $bold
    ): void {
        $sheet->setCellValue("A{$row}", $label);

        // Итого по состояниям груза (суммируем по всем типам вагонов)
        $itogoByCs = array_fill(0, $nCs, 0);
        for ($wi = 0; $wi < $nWag; $wi++) {
            for ($ci = 0; $ci < $nCs; $ci++) {
                $itogoByCs[$ci] += $v[$wi * $nCs + $ci] ?? 0;
            }
        }

        // Записываем: Итого[nCs] + типы[nWag * nCs]
        $colIdx = 2;
        foreach ($itogoByCs as $val) {
            $colL = $this->colLetter($colIdx++);
            $sheet->setCellValue("{$colL}{$row}", $val ?: '');
        }
        for ($wi = 0; $wi < $nWag; $wi++) {
            for ($ci = 0; $ci < $nCs; $ci++) {
                $val  = $v[$wi * $nCs + $ci] ?? 0;
                $colL = $this->colLetter($colIdx++);
                $sheet->setCellValue("{$colL}{$row}", $val ?: '');
            }
        }

        $nTotalCols = 1 + $nCs + $nWag * $nCs;
        $lastC = $this->colLetter($nTotalCols);

        $this->style($sheet, "A{$row}:{$lastC}{$row}", [
            'font' => ['bold' => $bold, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fillColor]],
        ]);
        $sheet->getStyle("B{$row}:{$lastC}{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // =========================================================================
    // Вспомогательные методы
    // =========================================================================

    /** Сокращение для состояния груза в заголовке: Загруженный → гр., Порожний → пор. */
    private function csShort(string $cs): string
    {
        $cs = mb_strtolower($cs);
        if (str_contains($cs, 'загруж') || str_contains($cs, 'груж')) {
            return 'гр.';
        }
        if (str_contains($cs, 'пор')) {
            return 'пор.';
        }
        return mb_substr($cs, 0, 3) . '.';
    }

    /** Применяет массив стилей к диапазону. */
    private function style(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        string $range,
        array $styleArray
    ): void {
        $sheet->getStyle($range)->applyFromArray($styleArray);
    }

    /** Преобразует номер колонки (1-based) в букву Excel: 1→A, 27→AA. */
    private function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $n--;
            $letter = chr(65 + $n % 26) . $letter;
            $n      = (int) ($n / 26);
        }
        return $letter;
    }
}
