<?php
$basePath = $basePath ?? '';
require_once __DIR__ . '/../partials/ui.php';

$formatBytes = static function (?float $bytes): string {
    if ($bytes === null || $bytes < 0) {
        return 'Недоступно';
    }
    $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    $unit = 0;
    while ($bytes >= 1024 && $unit < count($units) - 1) {
        $bytes /= 1024;
        $unit++;
    }
    return number_format($bytes, $unit === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$unit];
};
$formatUptime = static function (?int $seconds): string {
    if ($seconds === null) {
        return 'Недоступно';
    }
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return ($days > 0 ? $days . ' дн. ' : '') . sprintf('%02d:%02d', $hours, $minutes);
};
$statusClass = static function (string $status): string {
    return $status === 'ok' ? 'system-status--ok' : ($status === 'warning' ? 'system-status--warning' : 'system-status--error');
};
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?= htmlspecialchars($appName) ?> — О системе</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/app.css">
    <style>
        .system-page {
            max-width: 1280px
        }

        .system-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px
        }

        /* Карточки верхнего ряда должны занимать одинаковую высоту и начинаться на одном уровне. */
        .system-grid > .ui-card {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            width: 100%;
            max-width: none;
            min-height: 124px;
            height: 100%;
            box-sizing: border-box;
        }

        /* Сбрасываем глобальный отступ .ui-card + .ui-card внутри CSS Grid. */
        .system-grid > .ui-card + .ui-card {
            margin-top: 0;
        }

        .system-metric {
            min-width: 0;
            padding: 14px 16px
        }

        .system-metric-label {
            font-size: 12px;
            color: var(--text-3);
            margin-bottom: 8px
        }

        .system-metric-value {
            font-size: 21px;
            font-weight: 700;
            line-height: 1.25;
            overflow-wrap: anywhere
        }

        .system-metric-note {
            font-size: 11px;
            color: var(--text-3);
            margin-top: 5px
        }

        .system-columns {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(300px, 1fr);
            gap: 14px;
            margin-bottom: 14px
        }

        .system-panel {
            min-width: 0
        }

        .system-panel .ui-card-body {
            padding: 14px 16px
        }

        .system-component {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border)
        }

        .system-component:first-child {
            padding-top: 0
        }

        .system-component:last-child {
            padding-bottom: 0;
            border-bottom: 0
        }

        .system-component-main {
            flex: 1;
            min-width: 0
        }

        .system-component-name {
            font-size: 13px;
            font-weight: 600
        }

        .system-component-description {
            font-size: 11px;
            color: var(--text-3);
            margin-top: 3px;
            overflow-wrap: anywhere
        }

        .system-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            white-space: nowrap
        }

        .system-status:before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor
        }

        .system-status--ok {
            color: var(--brand-green)
        }

        .system-status--warning {
            color: #b17b25
        }

        .system-status--error {
            color: var(--brand-red, #c34b4b)
        }

        .system-resource {
            margin-bottom: 17px
        }

        .system-resource:last-child {
            margin-bottom: 0
        }

        .system-resource-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 12px;
            margin-bottom: 7px
        }

        .system-resource-head span {
            color: var(--text-2)
        }

        .system-resource-head strong {
            font-weight: 600
        }

        .system-track {
            height: 7px;
            border-radius: 8px;
            background: var(--border);
            overflow: hidden
        }

        .system-track span {
            display: block;
            height: 100%;
            border-radius: 8px;
            background: var(--accent)
        }

        .system-track .system-track--warning {
            background: #d49a3a
        }

        .system-resource-foot {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 10px;
            color: var(--text-3);
            margin-top: 5px
        }

        .system-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 22px
        }

        .system-info-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 0;
            border-bottom: 1px solid var(--border);
            font-size: 12px
        }

        .system-info-item span {
            color: var(--text-2)
        }

        .system-info-item strong {
            text-align: right;
            font-weight: 600;
            overflow-wrap: anywhere
        }

        .system-section-note {
            font-size: 11px;
            color: var(--text-3);
            line-height: 1.5;
            margin-top: 12px
        }

        @media(max-width:1050px) {
            .system-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .system-columns {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:650px) {
            .system-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px
            }

            .system-metric {
                padding: 11px
            }

            .system-metric-value {
                font-size: 17px
            }

            .system-info-grid {
                grid-template-columns: 1fr
            }

            .system-component {
                align-items: flex-start;
                flex-wrap: wrap
            }

            .system-component-main {
                min-width: 65%
            }
        }
    </style>
</head>

<body>
    <?php
    $headerSub = '<div class="brand-sub">Администрирование</div>';
    $headerLeft = '<a href="' . htmlspecialchars($basePath) . '/" class="btn-nav-back" title="На главную">←</a>';
    $headerRight = '';
    include __DIR__ . '/../partials/header.php';
    ?>
    <div class="app-body">
        <?php $activeAdminPage = 'system';
        include __DIR__ . '/../partials/admin-sidebar.php'; ?>
        <main class="main-content">
            <div class="system-page">
                <div class="ui-page-head">
                    <div class="ui-page-title">О системе<small>Состояние приложения и серверной среды</small></div>
                    <span class="ui-muted" style="font-size:11px">Проверено: <?= htmlspecialchars($checkedAt) ?></span>
                </div>

                <div class="system-grid">
                    <section class="ui-card system-metric">
                        <div class="system-metric-label">PHP</div>
                        <div class="system-metric-value"><?= htmlspecialchars($phpVersion) ?></div>
                        <div class="system-metric-note">Версия среды выполнения</div>
                    </section>
                    <section class="ui-card system-metric">
                        <div class="system-metric-label">Время работы сервера</div>
                        <div class="system-metric-value"><?= htmlspecialchars($formatUptime($uptimeSeconds)) ?></div>
                        <div class="system-metric-note">С момента последней загрузки ОС</div>
                    </section>
                    <section class="ui-card system-metric">
                        <div class="system-metric-label">Оперативная память</div>
                        <div class="system-metric-value">
                            <?= $memory ? htmlspecialchars($formatBytes((float) $memory['used'])) : 'Недоступно' ?>
                        </div>
                        <div class="system-metric-note">
                            <?= $memory ? htmlspecialchars($memory['percent'] . '% использовано') : 'Нет данных ОС' ?>
                        </div>
                    </section>
                    <section class="ui-card system-metric">
                        <div class="system-metric-label">Диск приложения</div>
                        <div class="system-metric-value">
                            <?= $disk ? htmlspecialchars($formatBytes($disk['free'])) : 'Недоступно' ?></div>
                        <div class="system-metric-note">
                            <?= $disk ? htmlspecialchars($disk['percent'] . '% занято') : 'Нет данных файловой системы' ?>
                        </div>
                    </section>
                </div>

                <div class="system-columns">
                    <section class="ui-panel system-panel">
                        <div class="ui-panel-head"><span class="ui-panel-title">Состояние компонентов</span></div>
                        <div class="ui-card-body">
                            <?php foreach ($components as $component): ?>
                                <div class="system-component">
                                    <div class="system-component-main">
                                        <div class="system-component-name"><?= htmlspecialchars($component['name']) ?></div>
                                        <div class="system-component-description">
                                            <?= htmlspecialchars($component['description']) ?></div>
                                    </div>
                                    <span
                                        class="system-status <?= $statusClass($component['status']) ?>"><?= htmlspecialchars($component['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="ui-panel system-panel">
                        <div class="ui-panel-head"><span class="ui-panel-title">Ресурсы сервера</span></div>
                        <div class="ui-card-body">
                            <?php if ($memory): ?>
                                <div class="system-resource">
                                    <div class="system-resource-head"><span>Оперативная
                                            память</span><strong><?= htmlspecialchars($formatBytes((float) $memory['used'])) ?>
                                            / <?= htmlspecialchars($formatBytes((float) $memory['total'])) ?></strong></div>
                                    <div class="system-track"><span
                                            class="<?= $memory['percent'] >= 85 ? 'system-track--warning' : '' ?>"
                                            style="width:<?= (int) $memory['percent'] ?>%"></span></div>
                                    <div class="system-resource-foot"><span>Использовано
                                            <?= (int) $memory['percent'] ?>%</span><span>Свободно
                                            <?= htmlspecialchars($formatBytes((float) $memory['available'])) ?></span></div>
                                </div>
                            <?php else: ?>
                                <div class="system-resource">
                                    <div class="ui-empty">Информация о памяти недоступна на этой платформе.</div>
                                </div>
                            <?php endif; ?>

                            <?php if ($disk): ?>
                                <div class="system-resource">
                                    <div class="system-resource-head"><span>Дисковое
                                            пространство</span><strong><?= htmlspecialchars($formatBytes($disk['used'])) ?>
                                            / <?= htmlspecialchars($formatBytes($disk['total'])) ?></strong></div>
                                    <div class="system-track"><span
                                            class="<?= $disk['percent'] >= 85 ? 'system-track--warning' : '' ?>"
                                            style="width:<?= (int) $disk['percent'] ?>%"></span></div>
                                    <div class="system-resource-foot"><span>Занято
                                            <?= (int) $disk['percent'] ?>%</span><span>Свободно
                                            <?= htmlspecialchars($formatBytes($disk['free'])) ?></span></div>
                                </div>
                            <?php else: ?>
                                <div class="system-resource">
                                    <div class="ui-empty">Информация о диске недоступна.</div>
                                </div>
                            <?php endif; ?>

                            <div class="system-info-grid">
                                <div class="system-info-item"><span>CPU load (1
                                        мин.)</span><strong><?= $loadAverage !== null ? htmlspecialchars(number_format((float) $loadAverage[0], 2, ',', ' ')) : 'Недоступно' ?></strong>
                                </div>
                                <div class="system-info-item"><span>CPU load (5
                                        мин.)</span><strong><?= $loadAverage !== null ? htmlspecialchars(number_format((float) $loadAverage[1], 2, ',', ' ')) : 'Недоступно' ?></strong>
                                </div>
                                <div class="system-info-item"><span>CPU load (15
                                        мин.)</span><strong><?= $loadAverage !== null ? htmlspecialchars(number_format((float) $loadAverage[2], 2, ',', ' ')) : 'Недоступно' ?></strong>
                                </div>
                                <div class="system-info-item">
                                    <span>Uptime</span><strong><?= htmlspecialchars($formatUptime($uptimeSeconds)) ?></strong>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <section class="ui-panel system-panel">
                    <div class="ui-panel-head"><span class="ui-panel-title">Информация о приложении</span></div>
                    <div class="ui-card-body">
                        <div class="system-info-grid">
                            <div class="system-info-item">
                                <span>Приложение</span><strong><?= htmlspecialchars($appName) ?></strong></div>
                            <div class="system-info-item">
                                <span>Окружение</span><strong><?= htmlspecialchars($environment) ?></strong></div>
                            <div class="system-info-item"><span>Операционная
                                    система</span><strong><?= htmlspecialchars($os) ?></strong></div>
                            <div class="system-info-item">
                                <span>Архитектура</span><strong><?= htmlspecialchars(php_uname('m')) ?></strong></div>
                            <div class="system-info-item"><span>Часовой
                                    пояс</span><strong><?= htmlspecialchars($timezone) ?></strong></div>
                            <div class="system-info-item"><span>Время
                                    сервера</span><strong><?= htmlspecialchars(date('d.m.Y H:i:s')) ?></strong></div>
                            <div class="system-info-item"><span>Расширение
                                    OCI8</span><strong><?= extension_loaded('oci8') ? 'Установлено' : 'Не установлено' ?></strong>
                            </div>
                            <div class="system-info-item"><span>Расширение
                                    mbstring</span><strong><?= extension_loaded('mbstring') ? 'Установлено' : 'Не установлено' ?></strong>
                            </div>
                            <div class="system-info-item"><span>Расширение
                                    JSON</span><strong><?= extension_loaded('json') ? 'Установлено' : 'Не установлено' ?></strong>
                            </div>
                            <div class="system-info-item"><span>Oracle, контрольный
                                    запрос</span><strong><?= htmlspecialchars($oracleStatus) ?><?= $oracleLatency !== null ? ' · ' . (int) $oracleLatency . ' мс' : '' ?></strong>
                            </div>
                        </div>
                        <p class="system-section-note">Секреты, пароли, токены и содержимое конфигурационных файлов не
                            отображаются.</p>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>

</html>