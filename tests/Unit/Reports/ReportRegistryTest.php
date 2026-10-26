<?php
declare(strict_types=1);

use App\Reports\ReportRegistry;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$allowedContexts = ['page', 'mailing', 'export'];
$allowedValueTypes = ['text', 'select', 'date'];

function requireRegistryCondition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (ReportRegistry::all() as $reportCode => $reportDefinition) {
    requireRegistryCondition($reportCode !== '', 'Пустой код отчёта');
    requireRegistryCondition(($reportDefinition['name'] ?? '') !== '', "Не указано название отчёта: {$reportCode}");
    requireRegistryCondition(
        ($reportDefinition['summary_endpoint'] ?? null) !== null || ($reportDefinition['detail_endpoint'] ?? null) !== null,
        "Не указан API отчёта: {$reportCode}"
    );
    requireRegistryCondition(
        array_diff($reportDefinition['contexts'] ?? [], $allowedContexts) === [],
        "Неизвестный контекст отчёта: {$reportCode}"
    );
    requireRegistryCondition(
        isset($reportDefinition['page']) && is_array($reportDefinition['page']),
        "Не указаны настройки страницы: {$reportCode}"
    );
    requireRegistryCondition(
        is_array($reportDefinition['navigation'] ?? null),
        "Отчёт не добавлен в навигацию: {$reportCode}"
    );

    $filterKeys = [];
    foreach ($reportDefinition['filters'] ?? [] as $filterDefinition) {
        $filterKey = (string) ($filterDefinition['key'] ?? '');
        requireRegistryCondition($filterKey !== '', "Пустой ключ фильтра: {$reportCode}");
        requireRegistryCondition(!isset($filterKeys[$filterKey]), "Повтор фильтра {$reportCode}.{$filterKey}");
        requireRegistryCondition(
            in_array($filterDefinition['value_type'] ?? '', $allowedValueTypes, true),
            "Неизвестный тип фильтра: {$reportCode}.{$filterKey}"
        );
        requireRegistryCondition(
            array_diff($filterDefinition['contexts'] ?? [], $allowedContexts) === [],
            "Неизвестный контекст фильтра: {$reportCode}.{$filterKey}"
        );
        $filterKeys[$filterKey] = true;
    }

    $detailColumnKeys = [];
    foreach ($reportDefinition['detail_columns'] ?? [] as $columnDefinition) {
        $columnKey = (string) ($columnDefinition['key'] ?? '');
        requireRegistryCondition($columnKey !== '', "Пустой ключ колонки: {$reportCode}");
        requireRegistryCondition(
            !isset($detailColumnKeys[$columnKey]),
            "Повтор колонки {$reportCode}.{$columnKey}"
        );
        requireRegistryCondition(
            ($columnDefinition['label'] ?? '') !== '',
            "Не указано название колонки: {$reportCode}.{$columnKey}"
        );
        $detailColumnKeys[$columnKey] = true;
    }
}

echo "ReportRegistry: OK\n";
