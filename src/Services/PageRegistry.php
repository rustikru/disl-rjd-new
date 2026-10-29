<?php
declare(strict_types=1);
namespace App\Services;

final class PageRegistry
{
    public const PAGES = [
        'dashboard' => 'Дашборд', 'maps' => 'Карта', 'import' => 'Загрузка справок',
        'admin' => 'Администрирование', 'directories' => 'Справочники',
        'directories_edit' => 'Ведение справочников', 'profile' => 'Профиль',
        'users' => 'Пользователи', 'roles' => 'Роли', 'organizations' => 'Организации',
        'audit' => 'Журнал действий', 'report-mailings' => 'Рассылки',
        'dislocation' => 'Дислокация', 'approach' => 'Подход', 'departure' => 'Отправление',
        'loading' => 'Погрузка', 'downtime' => 'Простой', 'claims' => 'Претензии',
        'analysis-period' => 'Анализ за период', 'downtime-control' => 'Контроль простоя',
        'technical-state' => 'Техническое состояние', 'raw-material' => 'Сырьё',
    ];
}
