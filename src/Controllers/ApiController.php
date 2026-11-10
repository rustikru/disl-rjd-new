<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\DbInterface;
use App\Reports\ReportConfig;
use App\Reports\KpiCondition;
use App\Reports\KpiGroupingTemplates;
use App\Reports\KpiTypeRegistry;
use App\Services\Claims;
use App\Services\Organizations;
use App\Services\ReportService;
use App\Services\WagonCargoState;
use App\Services\WagonMovement;
use App\Services\TableLayoutService;
use App\Logging\ErrorLogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class ApiController
{
    private const WAG_TYPE_EXPR = "xx_etw.xx_rjd_dislocation_new_pkg.fnc_mapping_wag_type(wagon_type_code)";
    private const WAG_STATE = WagonCargoState::SQL_EXPRESSION;
    // Исключение вагонов из отчета по простоям (по данным справочника xx_disl_idle_control_v)
    private const EXCLUDED_WAGONS_COND = "
        AND NOT EXISTS (
            SELECT 1
              FROM xx_disl_idle_control_v dic
             WHERE 1=1 and TRIM(dic.car_number) = TRIM(TO_CHAR(wagon_no))
               AND UPPER(TRIM(dic.is_excluded)) = 'Y'
               AND UPPER(REPLACE(TRIM(dic.idle_reasons_name), 'Ё', 'Е'))
                   IN ('ЛОМ', 'МЕТАЛЛОЛОМ','СНЯТЬ С АВТОСЛЕЖЕНИЯ')
        )";

    private DbInterface $db;
    private Organizations $organizations;
    private array $reportDtCache = [];

    public function __construct(DbInterface $db, ?Organizations $organizations = null)
    {
        $this->db = $db;
        $this->organizations = $organizations ?? new Organizations($db);
    }

    // =========================================================================
    // API
    // =========================================================================

    /** GET /api/wagon/movement */
    public function wagonMovement(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $number = trim((string) ($query['wagon_no'] ?? ''));
        $page = max(1, (int) ($query['page'] ?? 1));
        try {
            $data = (new WagonMovement($this->db, $this->organizations))->rows($number, $page, 50);
            $rowCount = count($data['rows'] ?? []);
            $data += [
                'page' => 1,
                'per_page' => 50,
                'total' => $rowCount,
                'total_pages' => 1,
                'from' => $rowCount > 0 ? 1 : 0,
                'to' => $rowCount,
            ];
        } catch (\InvalidArgumentException $error) {
            $response = $response->withStatus(400);
            $data = ['error' => $error->getMessage()];
        }
        return $this->json($response, $data);
    }

    /** Первичная загрузка фильтров и данных отчёта одним HTTP-запросом. */
    public function reportInitial(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $reportCode
    ): ResponseInterface {
        $definition = ReportConfig::find($reportCode);
        $handlers = (array) ($definition['api_handlers'] ?? []);
        $filterMethod = (string) ($handlers['filters'] ?? '');
        $dataMethod = (string) ($handlers['summary'] ?? $handlers['detail'] ?? '');
        if ($filterMethod === '' || $dataMethod === ''
            || !method_exists($this, $filterMethod) || !method_exists($this, $dataMethod)) {
            return $this->json($response->withStatus(404), ['error' => 'Отчёт не найден']);
        }

        $filterResponse = $this->{$filterMethod}($request, new Response());
        if ($filterResponse->getStatusCode() >= 400) {
            return $filterResponse;
        }
        $dataResponse = $this->{$dataMethod}($request, new Response());
        if ($dataResponse->getStatusCode() >= 400) {
            return $dataResponse;
        }

        return $this->json($response, [
            'filters' => $this->responseJson($filterResponse),
            'data' => $this->responseJson($dataResponse),
        ]);
    }

    /** GET /api/downtime-control/detail */
    public function downtimeControlDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rows = (new ReportService($this->db, $this->organizations))
            ->downtimeControlDetail($request->getQueryParams());
        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/dashboard */
    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $dtsByType = $this->getLatestDtsByType(null, ['Подход', 'Отправка']);

        if (empty($dtsByType)) {
            return $this->json($response, ['updated_at' => null, 'sections' => [], 'trends' => []]);
        }

        $dt = max($dtsByType);

        $sections = [];

        try {
            $dtLabel = (new \DateTime($dt))->format('d.m.Y H:i:s');
        } catch (\Exception $e) {
            $dtLabel = $dt;
        }

        return $this->json($response, [
            'updated_at' => $dtLabel,
            'sections' => array_values($sections),
            'trends' => '',
        ]);
    }
    /** GET /api/kpi/summary */
    public function kpiSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $kpiType = trim((string) ($params['kpi_type'] ?? ''));
        $sourcePage = trim((string) ($params['source_page'] ?? ''));
        $expectedPage = KpiTypeRegistry::page($kpiType);
        if ($expectedPage === null || $sourcePage !== $expectedPage) {
            return $this->json($response->withStatus(400), ['error' => 'Некорректный запрос KPI']);
        }

        $filter = $this->organizations->filter();
        $dtRow = $this->db->fetchAll(
            "SELECT MAX(report_dt) AS latest_dt
               FROM xx_dislocation_rjd
              WHERE {$filter['sql']}",
            $filter['params']
        );
        $latestDt = $dtRow[0]['latest_dt'] ?? null;
        try {
            $updatedAt = $latestDt ? (new \DateTime($latestDt))->format('d.m.Y H:i') : null;
        } catch (\Exception $e) {
            $updatedAt = $latestDt;
        }

        if (!$this->organizations->isMtf()) {
            return $this->json($response, [
                'updated_at' => $updatedAt,
                'sections' => [['values' => []]],
            ]);
        }

        try {
            $rows = $this->db->fetchAll(
                'SELECT *
                   FROM TABLE(XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.GET_PAGE_KPI(
                       :kpi_type, :user_id
                   ))',
                [
                    'kpi_type' => $kpiType,
                    'user_id' => (int) ($_SESSION['user']['id'] ?? 0),
                ]
            );
        } catch (\Throwable $error) {
            $code = ErrorLogger::logThrowable($error, [
                'operation' => 'load_system_kpi',
                'kpi_type' => $kpiType,
            ], $request);
            return $this->json($response->withStatus(500), [
                'error' => 'Не удалось загрузить KPI',
                'code' => $code,
            ]);
        }

        if (KpiTypeRegistry::isUserCreatable($kpiType)) {
            try {
                $userId = (int) ($_SESSION['user']['id'] ?? 0);
                $userCards = $this->db->fetchAll(
                    "SELECT k.id, k.code, k.type, k.label, k.owner_id, k.visibility,
                            DBMS_LOB.SUBSTR(k.where_config, 3500, 1) AS where_config
                       FROM XX_ETW.XX_RJD_KPI_TBL k
                      WHERE k.is_system = 'N' AND k.is_active = 'Y' AND k.type = :type
                        AND (k.owner_id = :owner_id AND k.visibility = 'PRIVATE' OR k.visibility = 'GLOBAL')
                        AND NOT EXISTS (
                            SELECT 1 FROM XX_ETW.XX_RJD_KPI_HIDDEN h
                             WHERE h.user_id = :hidden_user_id AND h.kpi_id = k.id
                        )
                      ORDER BY order_by, id",
                    ['owner_id' => $userId, 'hidden_user_id' => $userId, 'type' => $kpiType]
                );
                if ($userCards !== []) {
                    $source = $this->dislFrom([]);
                    foreach ($userCards as $card) {
                        $condition = KpiCondition::compile($kpiType, (string) ($card['where_config'] ?? ''));
                        $count = 0;
                        if ($source['from'] !== '') {
                            $countRow = $this->db->fetchOne(
                                "SELECT COUNT(*) AS total FROM {$source['from']} xdr WHERE 1=1 AND type_reference = :kpi_type_reference AND {$condition['sql']}",
                                array_merge($source['bindings'], $condition['params'], [
                                    'kpi_type_reference' => KpiTypeRegistry::referenceType($kpiType),
                                ])
                            );
                            $count = (int) ($countRow['total'] ?? 0);
                        }
                        $rows[] = [
                            'id' => $card['id'], 'code' => $card['code'], 'type' => $card['type'],
                            'x_label' => $card['label'], 'x_value' => $count,
                            'trend_pct' => null, 'trend_dir' => null, 'is_user' => 'Y',
                            'is_global' => $card['visibility'] === 'GLOBAL' ? 'Y' : 'N',
                            'can_edit' => (($card['visibility'] === 'PRIVATE' && (int) $card['owner_id'] === $userId)
                                || ($card['visibility'] === 'GLOBAL' && \App\Services\Authorization::isSuper())) ? 'Y' : 'N',
                            'config' => json_decode((string) ($card['where_config'] ?? ''), true),
                        ];
                    }
                }
            } catch (\Throwable $error) {
                $code = ErrorLogger::logThrowable($error, ['operation' => 'load_user_kpi_summary', 'user_id' => (int) ($_SESSION['user']['id'] ?? 0)]);
                return $this->json($response->withStatus(500), ['error' => 'Не удалось загрузить пользовательские KPI', 'code' => $code]);
            }
        }

        $detailConfigs = [];
        $configRows = $this->db->fetchAll(
            "SELECT kpi_id, detail_type, detail_profile, group_title, count_label, empty_label
               FROM XX_ETW.XX_RJD_KPI_DETAIL_CFG
              WHERE is_active = 'Y'"
        );
        foreach ($configRows as $configRow) {
            $detailConfigs[(int) $configRow['kpi_id']] = $configRow;
        }

        $values = [];
        foreach ($rows as $r) {
            $code = (string) ($r['code'] ?? '');
            $kpiId = (int) ($r['id'] ?? 0);
            $detailConfig = $detailConfigs[$kpiId] ?? null;
            $detail = ReportConfig::kpiDetail($code);

            if ($detailConfig) {
                $detailType = strtoupper((string) ($detailConfig['detail_type'] ?? 'TABLE'));
                if ($detailType === 'SPECIAL') {
                    $detail = ReportConfig::trainGroups(
                        strtolower(trim((string) ($detailConfig['detail_profile'] ?? ''))),
                        trim((string) ($detailConfig['group_title'] ?? '')) ?: (string) ($r['x_label'] ?? ''),
                        trim((string) ($detailConfig['empty_label'] ?? '')) ?: 'Поездов нет'
                    );
                    if ($detail) {
                        $detail['count_label'] = trim((string) ($detailConfig['count_label'] ?? '')) ?: $detail['count_label'];
                    }
                } else {
                    $detail = null;
                }
            }

            $detailParams = ['kpi_id' => $r['id']];
            if (($r['is_user'] ?? 'N') === 'Y') {
                $userDetail = is_array($r['config']['detail'] ?? null) ? $r['config']['detail'] : [];
                if (strtoupper((string) ($userDetail['type'] ?? 'TABLE')) === 'GROUPED' && KpiGroupingTemplates::fromDetail($userDetail)) {
                    $detailParams['kpi_view'] = 'GROUPED';
                    $detailParams['kpi_type'] = (string) ($r['type'] ?? '');
                }
            } else {
                $detailParams['train_groups'] = $detail['code'] ?? '';
            }

            $cardDetail = ['ctx' => 'dislocation', 'params' => $detailParams];
            if (($r['type'] ?? $kpiType) === 'loading_kpi') {
                $cardDetail = [
                    'ctx' => 'loading',
                    'params' => ['loading_period' => $code],
                ];
            }

            $values[] = [
                'id' => $r['id'] ?? '',
                'type' => $r['type'] ?? $kpiType,
                'value' => $r['x_value'] ?? '0',
                'label' => $detail && !$detailConfig ? $detail['title'] : ($r['x_label'] ?? ''),
                'detail' => $cardDetail,
                'trend' => $r['trend_dir'] ?? '',
                'change' => $r['trend_pct'] ?? '',
                'is_user' => $r['is_user'] ?? 'N',
                'is_global' => $r['is_global'] ?? 'N',
                'can_edit' => $r['can_edit'] ?? 'N',
                'can_hide' => 'Y',
                'is_error' => $r['is_error'] ?? 'N',
                'config' => $r['config'] ?? null,
            ];
        }

        return $this->json($response, [
            'updated_at' => $updatedAt,
            'sections' => [['values' => $values]],
        ]);
    }

    /** Условие отбора по периоду погрузки. */
    private function loadingPeriodCondition(string $period, array &$bindings, string $prefix = 'loading'): string
    {
        $today = new \DateTimeImmutable('today');
        switch ($period) {
            case 'loading_today':
                $from = $today;
                $to = $today->modify('+1 day');
                break;
            case 'loading_yesterday':
                $from = $today->modify('-1 day');
                $to = $today;
                break;
            case 'loading_month':
                $from = $today->modify('first day of this month');
                $to = $today->modify('+1 day');
                break;
            default:
                return '1=0';
        }
        $bindings[$prefix . '_from'] = $from->format('Y-m-d');
        $bindings[$prefix . '_to'] = $to->format('Y-m-d');
        return "trip_start_dt >= TO_DATE(:{$prefix}_from, 'YYYY-MM-DD')"
            . " AND trip_start_dt < TO_DATE(:{$prefix}_to, 'YYYY-MM-DD')";
    }

    /** GET /api/dislocation/filters */
    public function dislFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $filter = $this->organizations->filter();
        $rows = $this->db->fetchAll(
            'SELECT TRUNC(report_dt) AS report_date, type_reference, COUNT(*) AS cnt
             FROM xx_dislocation_rjd
             WHERE ' . $filter['sql'] . '
             GROUP BY TRUNC(report_dt), type_reference
             ORDER BY TRUNC(report_dt) DESC, type_reference',
            $filter['params']
        );

        $source = $this->dislFrom([]);
        $cargo = $source['reportDt'] ? $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']} WHERE cargo_name IS NOT NULL ORDER BY cargo_name",
            $source['bindings']
        ) : [];

        $reports = array_map(function (array $r) {
            $dt = (string) ($r['report_date'] ?? '');
            try {
                $label = (new \DateTime($dt))->format('d.m.Y');
            } catch (\Exception $e) {
                $label = $dt;
            }
            return [
                'report_dt' => $dt,
                'type_reference' => (string) ($r['type_reference'] ?? ''),
                'label' => $label . ($r['type_reference'] ? ' (' . $r['type_reference'] . ')' : ''),
                'cnt' => (int) $r['cnt'],
            ];
        }, $rows);

        return $this->json($response, [
            'reports' => $reports,
            'cargo' => array_column($cargo, 'cargo_name')
        ]);
    }

    /** GET /api/dislocation/summary */
    public function dislSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->dislFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_state', 'dest_road']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);

        return $this->json($response, $this->summaryReport($source, $rowDims, $colDefs));
    }

    /** GET /api/dislocation/detail */
    public function dislDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->dislFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = '';
        $detail = null;
        $userDetailConfig = null;
        $kpiId = trim((string) ($params['kpi_id'] ?? ''));
        if ($kpiId !== '' && (!ctype_digit($kpiId) || (int) $kpiId < 1)) {
            return $this->json($response->withStatus(422), ['error' => 'Некорректный идентификатор KPI']);
        }
        if ($kpiId !== '') {
            $userId = (int) ($_SESSION['user']['id'] ?? 0);
            $userKpi = $this->db->fetchOne(
                "SELECT k.id, k.type, k.code, k.label, DBMS_LOB.SUBSTR(k.where_config, 3500, 1) AS where_config FROM XX_ETW.XX_RJD_KPI_TBL k
                  WHERE k.id = :kpi_id AND k.is_system = 'N' AND k.is_active = 'Y'
                    AND (k.owner_id = :owner_id AND k.visibility = 'PRIVATE' OR k.visibility = 'GLOBAL')
                    AND NOT EXISTS (
                        SELECT 1 FROM XX_ETW.XX_RJD_KPI_HIDDEN h
                         WHERE h.user_id = :hidden_user_id AND h.kpi_id = k.id
                    )",
                ['kpi_id' => (int) $kpiId, 'owner_id' => $userId, 'hidden_user_id' => $userId]
            );
            if ($userKpi) {
                if (!KpiTypeRegistry::isUserCreatable((string) $userKpi['type'])) {
                    return $this->json($response->withStatus(404), ['error' => 'Карточка KPI не найдена']);
                }
                $kpi = ['code' => $userKpi['code'], 'label' => $userKpi['label']];
                $userConfig = json_decode((string) ($userKpi['where_config'] ?? ''), true);
                $userDetailConfig = is_array($userConfig['detail'] ?? null) ? $userConfig['detail'] : null;
                try {
                    $userCondition = KpiCondition::compile((string) $userKpi['type'], (string) $userKpi['where_config']);
                } catch (\InvalidArgumentException $error) {
                    return $this->json($response->withStatus(422), ['error' => 'Настройки KPI повреждены']);
                } catch (\Throwable $error) {
                    $code = ErrorLogger::logThrowable($error, ['operation' => 'load_user_kpi_detail', 'kpi_id' => (int) $kpiId]);
                    return $this->json($response->withStatus(500), ['error' => 'Не удалось открыть детализацию KPI', 'code' => $code]);
                }
            } else {
                try {
                    $kpi = $this->db->fetchOne(
                        "SELECT k.code, k.label, XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.get_kpi_where(k.id) AS kpi_condition
                           FROM XX_ETW.XX_RJD_KPI_TBL k
                          WHERE k.id = :kpi_id AND k.is_system = 'Y' AND k.is_active = 'Y'
                            AND NOT EXISTS (
                                SELECT 1 FROM XX_ETW.XX_RJD_KPI_HIDDEN h
                                 WHERE h.user_id = :hidden_user_id AND h.kpi_id = k.id
                            )",
                        ['kpi_id' => (int) $kpiId, 'hidden_user_id' => $userId]
                    );
                } catch (\Throwable $error) {
                    if (strpos($error->getMessage(), 'ORA-20001') !== false) {
                        return $this->json($response->withStatus(422), ['error' => 'Условие для KPI не определено.']);
                    }
                    $code = ErrorLogger::logThrowable($error, ['operation' => 'load_system_kpi_detail', 'kpi_id' => (int) $kpiId]);
                    return $this->json($response->withStatus(500), ['error' => 'Не удалось открыть детализацию KPI', 'code' => $code]);
                }
                $userCondition = null;
            }
            if (!$kpi) {
                return $this->json($response->withStatus(404), ['error' => 'Карточка KPI не найдена']);
            }
            $detailConfig = null;
            if ($userCondition === null) {
                $detailConfig = $this->db->fetchOne(
                    "SELECT detail_type, detail_profile, group_title, count_label, empty_label
                       FROM XX_ETW.XX_RJD_KPI_DETAIL_CFG
                      WHERE kpi_id = :kpi_id AND is_active = 'Y'",
                    ['kpi_id' => (int) $kpiId]
                );
            }

            if ($userCondition !== null) {
                $detail = null;
            } elseif ($detailConfig) {
                if (strtoupper((string) ($detailConfig['detail_type'] ?? '')) === 'SPECIAL') {
                    $detail = ReportConfig::trainGroups(
                        strtolower(trim((string) ($detailConfig['detail_profile'] ?? ''))),
                        trim((string) ($detailConfig['group_title'] ?? '')) ?: (string) ($kpi['label'] ?? ''),
                        trim((string) ($detailConfig['empty_label'] ?? '')) ?: 'Поездов нет'
                    );
                    if ($detail) {
                        $detail['count_label'] = trim((string) ($detailConfig['count_label'] ?? '')) ?: $detail['count_label'];
                    }
                }
            } else {
                $detail = ReportConfig::kpiDetail($kpi['code'] ?? '');
            }

            if ($userCondition !== null) {
                $whereCond .= ' AND type_reference = :kpi_type_reference AND ' . $userCondition['sql'];
                $bindings = array_merge($bindings, $userCondition['params'], [
                    'kpi_type_reference' => KpiTypeRegistry::referenceType((string) $userKpi['type']),
                ]);
            } else {
                $condition = trim((string) ($kpi['kpi_condition'] ?? '')) ?: '1=0';
                $whereCond .= ' AND (' . $condition . ')';
            }
        }

        $rowDims = $detail
            ? $detail['group_by']
            : $this->parseGroupBy($params['group_by'] ?? '', ['dest_state']);
        $whereCond .= $this->applyDetailFilters($rowDims, $params, $bindings);

        $trainGroups = in_array(
            (string) ($params['train_groups'] ?? ''),
            ['train-groups', 'abandoned-trains', 'formed-trains'],
            true
        );
        if ($detail && $trainGroups && $detail['code'] === 'train-groups') {
            $groupField = strtoupper($detail['group_field']);
            $select = [
                $groupField . ' AS "group_value"',
                'COUNT(*) AS "total"',
                "SUM(CASE WHEN " . self::WAG_STATE . " = 'гр.' THEN 1 ELSE 0 END) AS \"loaded\"",
                "SUM(CASE WHEN " . self::WAG_STATE . " = 'пор.' THEN 1 ELSE 0 END) AS \"empty_count\"",
            ];
            foreach ($detail['route_fields'] as $field) {
                $key = $field['key'];
                $column = strtoupper($key);
                $select[] = "MIN($column) AS \"route_{$key}\"";
                $select[] = "COUNT(DISTINCT $column) AS \"route_{$key}_count\"";
            }
            foreach ($detail['fields'] as $field) {
                $key = $field['key'];
                $column = strtoupper($key);
                if (in_array($key, ['oper_dt', 'trip_start_dt'], true)) {
                    $select[] = "TO_CHAR(MIN($column), 'DD.MM.YYYY HH24:MI') AS \"$key\"";
                } else {
                    $select[] = "MIN($column) AS \"$key\"";
                }
                if (!in_array($key, ['oper_dt', 'trip_start_dt'], true)) {
                    $select[] = "COUNT(DISTINCT $column) AS \"{$key}_count\"";
                }
            }
            $rows = $this->db->fetchAll(
                "SELECT " . implode(', ', $select) . "
                   FROM {$source['from']} xdr
                  WHERE 1=1 $whereCond
                  GROUP BY $groupField
                  ORDER BY $groupField",
                $bindings
            );
            $rows = $this->addTrainIndexGroups($rows, $detail, $groupField, $source['from'], $whereCond, $bindings);

            $summary = $this->db->fetchOne(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN " . self::WAG_STATE . " = 'гр.' THEN 1 ELSE 0 END) AS loaded,
                        SUM(CASE WHEN " . self::WAG_STATE . " = 'пор.' THEN 1 ELSE 0 END) AS empty_count
                   FROM {$source['from']} xdr
                  WHERE 1=1 $whereCond",
                $bindings
            ) ?? ['total' => 0, 'loaded' => 0, 'empty' => 0];

            return $this->json($response, [
                'rows' => $rows,
                'group_config' => $detail,
                'summary' => [
                    'total' => (int) ($summary['total'] ?? 0),
                    'loaded' => (int) ($summary['loaded'] ?? 0),
                    'empty' => (int) ($summary['empty_count'] ?? 0),
                ],
            ]);
        }

        if (
            $userDetailConfig
            && strtoupper((string) ($userDetailConfig['type'] ?? 'TABLE')) === 'GROUPED'
            && ($params['kpi_table'] ?? '') !== 'Y'
        ) {
            $template = KpiGroupingTemplates::fromDetail($userDetailConfig);
            if (!$template) {
                return $this->json($response->withStatus(422), ['error' => 'Шаблон группировки KPI недоступен']);
            }
            $groupField = $template['group_field'];
            $hasGroupValue = array_key_exists('group_value', $params) || ($params['group_value_is_null'] ?? '') === 'Y';
            $groupValue = $params['group_value'] ?? null;
            if ($hasGroupValue) {
                if (($params['group_value_is_null'] ?? '') === 'Y') {
                    $groupWhere = $whereCond . ' AND ' . strtoupper($groupField) . ' IS NULL';
                } else {
                    if (!is_scalar($groupValue) || strlen((string) $groupValue) > 800) {
                        return $this->json($response->withStatus(422), ['error' => 'Некорректное значение группы']);
                    }
                    $bindings['user_group_value'] = $groupValue;
                    $groupWhere = $whereCond . ' AND ' . strtoupper($groupField) . ' = :user_group_value';
                }
                $columns = array_values(array_unique(array_merge(
                    ['wagon_no', 'wagon_type_code', 'cargo_name', 'cargo_weight_kg', 'wagon_state', 'depart_station', 'dest_station', 'oper_station', 'oper_road', 'operation', 'oper_mnemonic', 'oper_dt', 'train_no', 'train_index', 'trip_start_dt'],
                    array_column(KpiCondition::fields((string) $userKpi['type']), 'key')
                )));
                $detailRows = $this->db->fetchAll(
                    "SELECT " . implode(', ', array_map(static fn($field) => strtoupper($field) . ' AS \"' . $field . '\"', $columns)) . " FROM {$source['from']} xdr WHERE 1=1 $groupWhere ORDER BY OPER_DT DESC",
                    $bindings
                );
                $detailColumns = [];
                $sourceColumns = array_column(ReportConfig::sourceColumns(), null, 'key');
                foreach ($columns as $field) {
                    if (isset($sourceColumns[$field])) {
                        $detailColumns[] = ['key' => $field, 'label' => $sourceColumns[$field]['label']];
                    }
                }
                return $this->json($response, [
                    'rows' => $detailRows,
                    'columns' => $detailColumns,
                    'label' => (string) ($kpi['label'] ?? 'Детализация группы'),
                ]);
            }

            $select = [
                strtoupper($groupField) . ' AS "group_value"',
                'COUNT(*) AS "total"',
                "SUM(CASE WHEN " . self::WAG_STATE . " = 'гр.' THEN 1 ELSE 0 END) AS \"loaded\"",
                "SUM(CASE WHEN " . self::WAG_STATE . " = 'пор.' THEN 1 ELSE 0 END) AS \"empty_count\"",
            ];
            foreach ($template['route_fields'] as $field) {
                $key = $field['key'];
                $column = strtoupper($key);
                $select[] = "MIN($column) AS \"route_{$key}\"";
                $select[] = "COUNT(DISTINCT $column) AS \"route_{$key}_count\"";
            }
            foreach ($template['fields'] as $field) {
                $key = $field['key'];
                $column = strtoupper($key);
                if (in_array($key, ['oper_dt', 'trip_start_dt'], true)) {
                    $select[] = "TO_CHAR(MIN($column), 'DD.MM.YYYY HH24:MI') AS \"$key\"";
                } else {
                    $select[] = "MIN($column) AS \"$key\"";
                }
                if (!in_array($key, ['oper_dt', 'trip_start_dt'], true)) {
                    $select[] = "COUNT(DISTINCT $column) AS \"{$key}_count\"";
                }
            }
            $rows = $this->db->fetchAll(
                "SELECT " . implode(', ', $select) . " FROM {$source['from']} xdr WHERE 1=1 $whereCond GROUP BY " . strtoupper($groupField) . " ORDER BY " . strtoupper($groupField) . " ASC",
                $bindings
            );
            $rows = $this->addTrainIndexGroups($rows, $template, strtoupper($groupField), $source['from'], $whereCond, $bindings);
            $summary = $this->db->fetchOne(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN " . self::WAG_STATE . " = 'гр.' THEN 1 ELSE 0 END) AS loaded,
                        SUM(CASE WHEN " . self::WAG_STATE . " = 'пор.' THEN 1 ELSE 0 END) AS empty_count
                   FROM {$source['from']} xdr
                  WHERE 1=1 $whereCond",
                $bindings
            ) ?? ['total' => 0, 'loaded' => 0, 'empty_count' => 0];

            foreach ($rows as &$row) {
                $row['loaded'] = (int) ($row['loaded'] ?? 0);
                $row['empty_count'] = (int) ($row['empty_count'] ?? 0);
            }
            unset($row);

            return $this->json($response, [
                'rows' => $rows,
                'label' => (string) ($kpi['label'] ?? 'Детализация KPI'),
                'summary' => [
                    'total' => (int) ($summary['total'] ?? 0),
                    'loaded' => (int) ($summary['loaded'] ?? 0),
                    'empty' => (int) ($summary['empty_count'] ?? 0),
                ],
                'group_config' => $template,
            ]);
        }

        if (
            $userDetailConfig
            && ($params['kpi_table'] ?? '') === 'Y'
            && (array_key_exists('group_value', $params) || ($params['group_value_is_null'] ?? '') === 'Y')
        ) {
            $template = KpiGroupingTemplates::fromDetail($userDetailConfig);
            if (!$template) {
                return $this->json($response->withStatus(422), ['error' => 'Шаблон группировки KPI недоступен']);
            }
            $groupField = strtoupper($template['group_field']);
            if (($params['group_value_is_null'] ?? '') === 'Y') {
                $whereCond .= ' AND ' . $groupField . ' IS NULL';
            } else {
                $groupValue = $params['group_value'];
                if (!is_scalar($groupValue) || strlen((string) $groupValue) > 800) {
                    return $this->json($response->withStatus(422), ['error' => 'Некорректное значение группы']);
                }
                $whereCond .= ' AND ' . $groupField . ' = :user_group_value';
                $bindings['user_group_value'] = $groupValue;
            }
        }

        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims) . ', oper_station');

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} xdr WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );
        $result = ['rows' => $rows];
        if ($userDetailConfig) $result['label'] = (string) ($kpi['label'] ?? 'Детализация KPI');
        return $this->json($response, $result);
    }

    private function addTrainIndexGroups(
        array $rows,
        array $config,
        string $groupField,
        string $from,
        string $where,
        array $bindings
    ): array {
        if ($groupField !== 'TRAIN_NO' || !$rows) {
            return $rows;
        }

        $select = [
            'TRAIN_NO AS "group_value"',
            'TRAIN_INDEX AS "value"',
            'COUNT(*) AS "total"',
        ];
        foreach ($config['fields'] as $field) {
            $key = $field['key'];
            if ($key === 'train_index') {
                continue;
            }
            $column = strtoupper($key);
            if (in_array($key, ['oper_dt', 'trip_start_dt'], true)) {
                $select[] = "TO_CHAR(MIN($column), 'DD.MM.YYYY HH24:MI') AS \"$key\"";
            } else {
                $select[] = "MIN($column) AS \"$key\"";
            }
            if (!in_array($key, ['oper_dt', 'trip_start_dt'], true)) {
                $select[] = "COUNT(DISTINCT $column) AS \"{$key}_count\"";
            }
        }

        $indexRows = $this->db->fetchAll(
            "SELECT " . implode(', ', $select) . "
               FROM $from xdr
              WHERE 1=1 $where
              GROUP BY TRAIN_NO, TRAIN_INDEX
              ORDER BY TRAIN_NO, TRAIN_INDEX",
            $bindings
        );
        $groups = [];
        foreach ($indexRows as $indexRow) {
            $key = $indexRow['group_value'] === null ? "\0" : (string) $indexRow['group_value'];
            unset($indexRow['group_value']);
            $indexRow['total'] = (int) ($indexRow['total'] ?? 0);
            $groups[$key][] = $indexRow;
        }
        foreach ($rows as &$row) {
            $key = $row['group_value'] === null ? "\0" : (string) $row['group_value'];
            $row['train_index_groups'] = $groups[$key] ?? [];
        }
        unset($row);

        return $rows;
    }

    /** GET /api/approach/filters */
    public function approachFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->approachFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$source['reportDt']) {
            return $this->json($response, ['cargo' => [], 'depart_station' => []]);
        }

        return $this->json($response, $this->approachFilterValues($source));
    }

    /** GET /api/approach/summary */
    public function approachSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->approachFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        $data = $this->summaryReport($source, $rowDims, $colDefs);
        return $this->json($response, $data);
    }

    /** GET /api/approach/detail */
    public function approachDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $source = $this->approachFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);

        $excl = $params['exclude_station'] ?? null;
        if ($excl) {
            $whereCond .= " AND UPPER(oper_station) NOT LIKE '%' || UPPER(:excl_st) || '%'";
            $bindings['excl_st'] = strtoupper($excl);
        }

        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims));

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** Полная последняя загруженная справка «Подход» для рассылки. */
    public function sourceApproach(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->sourceReference($request, $response, 'Подход');
    }

    /** Полная последняя загруженная справка «Отправка» для рассылки. */
    public function sourceDeparture(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->sourceReference($request, $response, 'Отправка');
    }

    /** GET /api/departure/filters */
    public function departureFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->departureFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$source['reportDt']) {
            return $this->json($response, ['cargo' => [], 'dest_station' => []]);
        }

        return $this->json($response, $this->departureFilterValues($source));
    }

    /** GET /api/departure/summary 
     * Отправление вагонов
     */
    public function departureSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->departureFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        $data = $this->summaryReport($source, $rowDims, $colDefs);
        return $this->json($response, $data);
    }

    private function approachFilterValues(array $source): array
    {
        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']}
             WHERE cargo_name IS NOT NULL AND NVL(prev_cargo, '*') != '*'
             ORDER BY cargo_name",
            $source['bindings']
        );
        $departStation = $this->db->fetchAll(
            "SELECT DISTINCT depart_station FROM {$source['from']}
             WHERE depart_station IS NOT NULL AND NVL(depart_station, '*') != '*'
             ORDER BY depart_station",
            $source['bindings']
        );

        return [
            'cargo' => array_column($cargo, 'cargo_name'),
            'depart_station' => array_column($departStation, 'depart_station'),
        ];
    }

    private function departureFilterValues(array $source): array
    {
        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']}
             WHERE cargo_name IS NOT NULL ORDER BY cargo_name",
            $source['bindings']
        );
        $destStation = $this->db->fetchAll(
            "SELECT DISTINCT dest_station FROM {$source['from']}
             WHERE dest_station IS NOT NULL ORDER BY dest_station",
            $source['bindings']
        );

        return [
            'cargo' => array_column($cargo, 'cargo_name'),
            'dest_station' => array_column($destStation, 'dest_station'),
        ];
    }

    /** GET /api/departure/detail */
    public function departureDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['depart_road', 'depart_station']);
        $source = $this->departureFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);
        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims));

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }


    /** GET /api/loading/filters */
    public function loadingFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->loadingFrom(['report_dt' => $params['report_dt'] ?? null]);

        if (!$source['reportDt']) {
            return $this->json($response, ['cargo' => []]);
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name FROM {$source['from']}
             WHERE cargo_name IS NOT NULL
             ORDER BY cargo_name",
            $source['bindings']
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
        ]);
    }

    /** GET /api/loading/summary */
    public function loadingSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->loadingFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code', 'cargo_w_type']);
        return $this->json($response, $this->summaryReport($source, $rowDims, $colDefs));
    }

    /** GET /api/loading/detail */
    public function loadingDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['dest_road', 'dest_station']);
        $source = $this->loadingFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);
        $selectCols = $this->selectFields($params['fields'] ?? '');
        $orderBy = $this->orderBY($params, implode(', ', $rowDims));

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/downtime/filters */
    public function downtimeFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $source = $this->downtimeFrom($request->getQueryParams());

        if (!$source['reportDt']) {
            return $this->json($response, ['dest_station' => []]);
        }

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT dest_station FROM {$source['from']}
             WHERE dest_station IS NOT NULL
             ORDER BY dest_station",
            $source['bindings']
        );

        return $this->json($response, ['dest_station' => array_column($rows, 'dest_station')]);
    }

    /** GET /api/downtime/summary */
    public function downtimeSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->downtimeFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0]);
        }

        // Простои используют колонки из подзапроса (downtimeFrom)
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['oper_road', 'oper_station']);
        $colAliases = $this->parseGroupBy($params['col_by'] ?? '', ['fixed_col_label', 'm_wagon_type_code']);
        $selectRow = implode(', ', $rowDims);
        $selectCols = implode(', ', $colAliases);

        $rows = $this->db->fetchAll(
            "SELECT $selectRow, $selectCols, COUNT(*) AS cnt
             FROM {$source['from']}
             GROUP BY idle_time_order_by, $selectRow, $selectCols
             ORDER BY idle_time_order_by ASC, $selectRow",
            $source['bindings']
        );

        return $this->json($response, $this->roadTable($rows, $rowDims, $colAliases));
    }

    /** GET /api/downtime/detail */
    public function downtimeDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['idle_time_name']);
        $source = $this->downtimeFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);

        // wagon_type_code использует функцию из пакета (XX_RJD_DISLOCATION_NEW_PKG)
        $rawFields = array_values(array_filter(
            array_map('trim', explode(',', $params['fields'] ?? '')),
            fn($f) => self::isSafeField($f)
        ));
        $selectParts = array_map(
            fn($f) => $f === 'wagon_type_code' ? self::WAG_TYPE_EXPR . ' AS wagon_type_code' : $f,
            $rawFields
        );
        $selectCols = $selectParts ? implode(', ', $selectParts) : 'wagon_no';
        $selectCols = preg_replace('/\bidle_time_days\b/', 'station_idle_days AS idle_time_days', $selectCols);
        $orderBy = $this->orderBY($params, 'station_idle_days DESC NULLS LAST');
        $orderBy = preg_replace('/\bidle_time_days\b/', 'station_idle_days', $orderBy);

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/raw-material/summary */
    public function rawSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $source = $this->rawFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['cols' => [], 'roads' => [], 'metrics' => [], 'total' => 0, 'max_idle' => 0]);
        }

        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['raw_material_idle_label', 'cargo_name']);
        $colDefs = $this->resolveColDims($params['col_by'] ?? '', ['wagon_type_code']);
        $rowOrder = implode(', ', array_map(
            fn($field) => $field === 'raw_material_idle_label' ? 'MIN(LEAST(raw_material_idle_days, 13))' : $field,
            $rowDims
        ));
        $result = $this->summaryReport($source, $rowDims, $colDefs, $rowOrder);

        $maxIdleRow = $this->db->fetchOne("SELECT MAX(raw_material_idle_days) AS max_idle FROM {$source['from']}", $source['bindings']);
        $result['max_idle'] = (float) ($maxIdleRow['max_idle'] ?? 0);

        return $this->json($response, $result);
    }

    /** GET /api/raw-material/detail */
    public function rawDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $rowDims = $this->parseGroupBy($params['group_by'] ?? '', ['raw_material_idle_label', 'cargo_name']);
        $source = $this->rawFrom($params);

        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => []]);
        }

        $bindings = $source['bindings'];
        $whereCond = $this->applyDetailFilters($rowDims, $params, $bindings);
        $selectCols = $this->selectFields($params['fields'] ?? '');
        $selectCols = preg_replace('/\bidle_time_days\b/', 'raw_material_idle_days AS idle_time_days', $selectCols);
        $orderBy = $this->orderBY($params, implode(', ', $rowDims) . ', raw_material_idle_days DESC');
        $orderBy = preg_replace('/\bidle_time_days\b/', 'raw_material_idle_days', $orderBy);

        $rows = $this->db->fetchAll(
            "SELECT $selectCols FROM {$source['from']} WHERE 1=1 $whereCond ORDER BY $orderBy",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    /** GET /api/technical-state/filters */
    public function technicalFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $source = $this->technicalFrom([]);
        if (!$source['reportDt']) {
            return $this->json($response, ['wagon_type' => [], 'wagon_state' => []]);
        }
        $types = $this->db->fetchAll(
            "SELECT DISTINCT wagon_type_code FROM {$source['from']} WHERE wagon_type_code IS NOT NULL ORDER BY wagon_type_code",
            $source['bindings']
        );
        $states = $this->db->fetchAll(
            "SELECT DISTINCT wagon_state FROM {$source['from']} WHERE wagon_state IS NOT NULL ORDER BY wagon_state",
            $source['bindings']
        );
        return $this->json($response, [
            'wagon_type' => array_column($types, 'wagon_type_code'),
            'wagon_state' => array_column($states, 'wagon_state')
        ]);
    }

    /** GET /api/technical-state/detail */
    public function technicalDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $repair = (string) ($params['repair'] ?? '');
        if (!in_array($repair, ['', 'overdue', 'soon', 'missing'], true)) {
            return $this->json($response->withStatus(400), ['error' => 'Выберите срок ремонта']);
        }
        $source = $this->technicalFrom($params);
        if (!$source['reportDt']) {
            return $this->json($response, ['rows' => [], 'reports' => []]);
        }
        $bindings = $source['bindings'];
        $where = '1=1' . $this->wagonNoCond($params, $bindings);
        foreach (['wagon_type' => 'wagon_type_code', 'wagon_state' => 'wagon_state'] as $param => $field) {
            $where .= $this->multiValueCondition($field, $params[$param] ?? null, $param, $bindings);
        }
        if ($repair === 'overdue') {
            $where .= ' AND TRUNC(next_repair_dt) < TRUNC(report_dt)';
        } elseif ($repair === 'soon') {
            $where .= ' AND TRUNC(next_repair_dt) BETWEEN TRUNC(report_dt) AND TRUNC(report_dt) + 30';
        } elseif ($repair === 'missing') {
            $where .= ' AND next_repair_dt IS NULL';
        }
        $fields = $this->selectFields((string) ($params['fields'] ?? ''));
        $order = $this->orderBY($params, 'wagon_no');
        $rows = $this->db->fetchAll("SELECT $fields FROM {$source['from']} WHERE $where ORDER BY $order", $bindings);
        return $this->json($response, ['rows' => $rows, 'reports' => $source['reports']]);
    }

    /** Технические данные из актуальных справок без дублей вагонов. */
    private function technicalFrom(array $params): array
    {
        $source = $this->dislFrom(['report_dt' => $params['report_dt'] ?? null]);
        if (!$source['reportDt']) {
            return $source;
        }
        $source['reports'] = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        $source['from'] = "(SELECT * FROM (
            SELECT xdr.*, ROW_NUMBER() OVER (
                PARTITION BY TRIM(wagon_no)
                ORDER BY oper_dt DESC NULLS LAST, report_dt DESC,
                         CASE WHEN type_reference = 'Отправка' THEN 0 ELSE 1 END, id DESC
            ) AS technical_row FROM {$source['from']} xdr
        ) WHERE technical_row = 1 AND TRIM(wagon_no) NOT LIKE '0%'
            AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%')";
        return $source;
    }

    /** GET /api/claims/filters */
    public function claimsFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, $this->claims()->filters($request->getQueryParams()));
    }

    /** GET /api/claims/summary */
    public function claimsSummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->claims()->summary($request->getQueryParams());
        } catch (\InvalidArgumentException $error) {
            return $this->json($response->withStatus(400), ['error' => $error->getMessage()]);
        }
        return $this->json($response, $data);
    }

    /** GET /api/claims/detail */
    public function claimsDetail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->claims()->detail($request->getQueryParams());
        } catch (\InvalidArgumentException $error) {
            return $this->json($response->withStatus(400), ['error' => $error->getMessage()]);
        }
        return $this->json($response, $data);
    }

    private function claims(): Claims
    {
        return new Claims($this->db, $this->organizations);
    }

    public function analysisFilters(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';
        $this->organizations->addFilter($whereCond, $bindings);

        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo !== '') {
            $whereCond .= ' AND wagon_no IN (' . $this->parserInValues($wagonNo, ';', 'wagon_no', $bindings) . ')';
        }

        $dateFrom = $params['date_from'] ?? '';
        if ($dateFrom !== '') {
            $whereCond .= " AND TRUNC(oper_dt) >= TO_DATE(:date_from, 'YYYY-MM-DD')";
            $bindings['date_from'] = $dateFrom;
        }

        $dateTo = $params['date_to'] ?? '';
        if ($dateTo !== '') {
            $whereCond .= " AND TRUNC(oper_dt) <= TO_DATE(:date_to, 'YYYY-MM-DD')";
            $bindings['date_to'] = $dateTo;
        }

        $cargo = $this->db->fetchAll(
            "SELECT DISTINCT cargo_name
             FROM xx_rjd_dislocation_archive
             WHERE $whereCond
             ORDER BY cargo_name",
            $bindings
        );

        return $this->json($response, [
            'cargo' => array_column($cargo, 'cargo_name'),
        ]);
    }
    /** GET /api/analysis/period/detail */
    public function analysisPeriod(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $bindings = [];
        $whereCond = '1=1';
        $this->organizations->addFilter($whereCond, $bindings);

        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo !== '') {
            $whereCond .= ' AND wagon_no IN (' . $this->parserInValues($wagonNo, ';', 'wagon_no', $bindings) . ')';
        }

        $dateFrom = $params['date_from'] ?? '';
        if ($dateFrom !== '') {
            $whereCond .= " AND TRUNC(oper_dt) >= TO_DATE(:date_from, 'YYYY-MM-DD')";
            $bindings['date_from'] = $dateFrom;
        }

        $dateTo = $params['date_to'] ?? '';
        if ($dateTo === '') {
            $dateTo = date('Y-m-d');
        }
        $whereCond .= " AND TRUNC(oper_dt) <= TO_DATE(:date_to, 'YYYY-MM-DD')";
        $bindings['date_to'] = $dateTo;

        $whereCond .= $this->multiValueCondition('cargo_name', $params['cargo'] ?? null, 'analysis_cargo', $bindings);

        $selectCols = $this->selectFields($params['fields'] ?? '');

        $rows = $this->db->fetchAll(
            "SELECT DISTINCT $selectCols
             FROM xx_rjd_dislocation_archive
             WHERE $whereCond
             ORDER BY oper_dt DESC, wagon_no",
            $bindings
        );

        return $this->json($response, ['rows' => $rows]);
    }

    // =========================================================================
    // Источники данных (FROM + базовый WHERE каждого раздела)
    // Каждый возвращает: ['from' => <subquery>, 'bindings' => [...], 'reportDt' => string|null]
    // =========================================================================

    /** Дислокация: объединение двух типов справок ('Подход' + 'Отправка'). */
    private function dislFrom(array $params): array
    {
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        if (empty($dtsByType)) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }
        $cond = $this->latestDtCondition($dtsByType);
        $bindings = $cond['params'];
        $whereCond = $cond['sql'];

        $whereCond .= $this->multiValueCondition(
            "UPPER(REPLACE(COALESCE(cargo_name,''), 'Ё', 'Е'))",
            $params['cargo'] ?? null,
            'cargo_f',
            $bindings,
            true
        );
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        return [
            'from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)",
            'bindings' => $bindings,
            'reportDt' => max($dtsByType),
        ];
    }

    /** Подход: вагоны станция назначения УГЛ  (type_reference='Подход'). */
    private function approachFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND type_reference = 'Подход'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND UPPER(dest_station) LIKE '%УГЛ%'";
            // Не учитывать служебные номера и вагоны, исключённые из модели дороги.
            $whereCond .= " AND TRIM(wagon_no) NOT LIKE '0%'";
            $whereCond .= " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                               NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        }
        $this->organizations->addFilter($whereCond, $bindings);

        $whereCond .= $this->multiValueCondition("UPPER(REPLACE(COALESCE(cargo_name,''), 'Ё', 'Е'))", $params['cargo'] ?? null, 'cargo_f', $bindings, true);
        $whereCond .= $this->multiValueCondition("UPPER(REPLACE(COALESCE(depart_station,''), 'Ё', 'Е'))", $params['depart_station'] ?? null, 'depart_station_f', $bindings, true);
        $whereCond .= $this->wagonNoCond($params, $bindings);
        // Фильтр по реестру исключений временно отключён.
        // $whereCond .= $this->excludedWagonsCondition();

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Возвращает все загруженные строки и поля справки без условий экранных отчётов. */
    private function sourceReference(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $typeReference
    ): ResponseInterface {
        $params = $request->getQueryParams();
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, $typeReference);
        if (!$reportDt) {
            return $this->json($response, ['rows' => []]);
        }

        $allowedFields = array_column(ReportConfig::sourceColumns(), 'key');
        $requestedFields = array_values(array_filter(
            array_map('trim', explode(',', (string) ($params['fields'] ?? ''))),
            static fn(string $field): bool => in_array($field, $allowedFields, true)
        ));
        $fields = $requestedFields ?: $allowedFields;

        $bindings = [
            'report_dt' => $reportDt,
            'type_reference' => $typeReference,
        ];
        $where = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS')"
            . ' AND type_reference = :type_reference';
        $this->organizations->addFilter($where, $bindings);

        $rows = $this->db->fetchAll(
            'SELECT ' . implode(', ', $fields)
            . ' FROM xx_dislocation_rjd WHERE ' . $where
            . ' ORDER BY wagon_no, waybill_no',
            $bindings
        );

        try {
            $reportDate = (new \DateTimeImmutable($reportDt))->format('d.m.Y H:i');
        } catch (\Exception $e) {
            $reportDate = $reportDt;
        }

        return $this->json($response, ['rows' => $rows, 'report_date' => $reportDate]);
    }

    /** Отправление: вагоны с положительным оставшимся расстоянием. */
    private function departureFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS') AND type_reference = 'Отправка'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND REGEXP_LIKE(UPPER(TRIM(depart_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')";
            $whereCond .= " AND dist_remain_km > 0";
            $whereCond .= " AND TRIM(wagon_no) NOT LIKE '0%'";
            $whereCond .= " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                               NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        }
        $this->organizations->addFilter($whereCond, $bindings);

        $whereCond .= $this->multiValueCondition('UPPER(COALESCE(cargo_name,\'\'))', $params['cargo'] ?? null, 'cargo_f', $bindings, true);
        $whereCond .= $this->multiValueCondition('dest_station', $params['dest_station'] ?? null, 'dest_station', $bindings);
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Погрузка: рейсы со станции отправления без ограничения по дате начала. */
    private function loadingFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Отправка');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS')"
            . " AND type_reference = 'Отправка'"
            . " AND trip_start_dt IS NOT NULL"
            . " AND TRIM(wagon_no) NOT LIKE '0%'"
            . " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                   NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND REGEXP_LIKE(UPPER(TRIM(depart_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')";
        }
        $this->organizations->addFilter($whereCond, $bindings);
        $period = trim($params['loading_period'] ?? '');
        if ($period !== '') {
            $whereCond .= ' AND (' . $this->loadingPeriodCondition($period, $bindings) . ')';
        }


        $whereCond .= $this->multiValueCondition('UPPER(COALESCE(cargo_name,\'\'))', $params['cargo'] ?? null, 'cargo_f', $bindings, true);
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        return ['from' => "(SELECT * FROM xx_dislocation_rjd WHERE $whereCond)", 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Сырьё: прибывшие вагоны с грузом тяжелее 12 тонн. */
    private function rawFrom(array $params): array
    {
        $reportDt = $this->getReportDt($params['report_dt'] ?? null, 'Подход');
        if (!$reportDt) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }

        $bindings = ['report_dt' => $reportDt];
        $whereCond = "report_dt = TO_DATE(:report_dt, 'YYYY-MM-DD HH24:MI:SS')"
            . " AND type_reference = 'Подход'"
            . " AND dist_remain_km = 0"
            . " AND cargo_weight_kg > 12000"
            . " AND trip_end_dt IS NOT NULL AND TRUNC(trip_end_dt) <= TRUNC(report_dt)"
            . " AND TRIM(wagon_no) NOT LIKE '0%'"
            . " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                   NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        if ($this->organizations->isMtf()) {
            $whereCond .= " AND REGEXP_LIKE(UPPER(TRIM(dest_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')"
                . " AND REGEXP_LIKE(UPPER(TRIM(oper_station)), '^УГЛЕУРАЛЬ?СКАЯ([[:space:]]|$)')";
        }
        $this->organizations->addFilter($whereCond, $bindings);
        $whereCond .= $this->wagonNoCond($params, $bindings);
        $whereCond .= $this->excludedWagonsCondition();

        $from = "(SELECT xdr.*,
                         GREATEST(1, TRUNC(report_dt) - TRUNC(trip_end_dt)) AS raw_material_idle_days
                    FROM xx_dislocation_rjd xdr WHERE $whereCond)";
        $from = "(SELECT raw_data.*,
                         CASE
                             WHEN raw_material_idle_days > 12 THEN 'Простой свыше 12 суток'
                             WHEN raw_material_idle_days = 1 THEN 'Простой 1 сутки'
                             ELSE 'Простой ' || TO_CHAR(raw_material_idle_days) || ' суток'
                         END AS raw_material_idle_label
                    FROM $from raw_data)";
        return ['from' => $from, 'bindings' => $bindings, 'reportDt' => $reportDt];
    }

    /** Простои: добавляет вычисляемые колонки (idle_time_name, m_wagon_type_code) в подзапрос. */
    private function downtimeFrom(array $params): array
    {
        $dtsByType = $this->getLatestDtsByType($params['report_dt'] ?? null, ['Подход', 'Отправка']);
        if (!$dtsByType) {
            return ['from' => '', 'bindings' => [], 'reportDt' => null];
        }
        $cond = $this->latestDtCondition($dtsByType);
        $bindings = $cond['params'];
        $baseCond = $cond['sql'] . $this->wagonNoCond($params, $bindings);
        // Фильтр по реестру исключений временно отключён.
        // $baseCond .= $this->excludedWagonsCondition();
        $source = [
            'from' => "(SELECT * FROM xx_dislocation_rjd WHERE $baseCond)",
            'reportDt' => max($dtsByType),
        ];
        $whereCond = "dist_remain_km = 0 AND UPPER(TRIM(oper_station)) = UPPER(TRIM(dest_station))";
        $whereCond .= " AND TRIM(wagon_no) NOT LIKE '0%'"
            . " AND NVL(UPPER(REGEXP_REPLACE(TRIM(operation), '[[:space:]]+', ' ')), ' ')
                   NOT LIKE '%ИСКЛЮЧЕНИЕ ВАГОНА ИЗ ВАГОННОЙ МОДЕЛИ ДОРОГИ%'";
        $whereCond .= $this->multiValueCondition(
            'dest_station',
            $params['dest_station'] ?? null,
            'downtime_dest_station',
            $bindings
        );
        $bindings['idle_fact_date'] = (new \DateTimeImmutable('now'))->format('Y-m-d');
        $from = "(SELECT xdr.*,
                         CASE WHEN trip_end_dt IS NOT NULL
                                   AND TRUNC(trip_end_dt) <= TO_DATE(:idle_fact_date, 'YYYY-MM-DD')
                              THEN GREATEST(1, TO_DATE(:idle_fact_date, 'YYYY-MM-DD') - TRUNC(trip_end_dt))
                         END AS station_idle_days
                    FROM {$source['from']} xdr WHERE $whereCond)";

        $idleCond = '1=1';
        if (isset($params['min_days']) && $params['min_days'] !== '') {
            $idleCond .= ' AND station_idle_days >= :min_days';
            $bindings['min_days'] = max(0, (int) $params['min_days']);
        }
        if (isset($params['max_days']) && $params['max_days'] !== '') {
            $idleCond .= ' AND station_idle_days <= :max_days';
            $bindings['max_days'] = (int) $params['max_days'];
        }
        $from = "(SELECT idle_data.*,
                         XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_DOWNTIME_WAGON(
                             station_idle_days, 'name'
                         ) AS idle_time_name,
                         TO_NUMBER(XX_ETW.XX_RJD_DISLOCATION_NEW_PKG.FNC_GET_DOWNTIME_WAGON(
                             station_idle_days, 'order_by'
                         )) AS idle_time_order_by,
                         " . self::WAG_TYPE_EXPR . " AS m_wagon_type_code,
                         " . self::WAG_STATE . " AS m_wag_state,
                         'Кол-во' AS fixed_col_label
                    FROM $from idle_data WHERE $idleCond)";

        return ['from' => $from, 'bindings' => $bindings, 'reportDt' => $source['reportDt']];
    }

    // =========================================================================
    // Построители SQL-запросов (общие)
    // =========================================================================

    /**
     * Строит и выполняет запрос сводной таблицы.
     * Общий метод для всех разделов 
     *
     * $source   — источник данных из *From(): ['from', 'bindings']
     * $rowDims  — поля строк сводной (GROUP BY строки)
     * $colDefs  — колонки сводной: [['alias' => ..., 'expr' => ...], ...]
     */
    private function summaryReport(array $source, array $rowDims, array $colDefs, ?string $rowOrder = null): array
    {
        $selectRow = implode(', ', $rowDims);
        $rowOrder ??= $this->summaryRowOrder($rowDims);
        $selectCols = implode(', ', array_map(fn($c) => $this->applyFormat($c) . " AS {$c['alias']}", $colDefs));
        $groupByCols = implode(', ', array_map(fn($c) => $this->applyFormat($c), $colDefs));
        $colAliases = array_column($colDefs, 'alias');
        $orderByCols = implode(', ', $colAliases);

        $rows = $this->db->fetchAll(
            "SELECT $selectRow, $selectCols, COUNT(*) AS cnt
             FROM {$source['from']}
             GROUP BY $selectRow, $groupByCols
             ORDER BY $rowOrder, $orderByCols",
            $source['bindings']
        );

        return $this->roadTable($rows, $rowDims, $colAliases);
    }

    /** Задаёт приоритет для основных значений назначения в сводной таблице. */
    private function summaryRowOrder(array $rowDims): string
    {
        $parts = [];
        if (in_array('dest_state', $rowDims, true)) {
            $parts[] = "CASE WHEN UPPER(TRIM(dest_state)) LIKE '%РОССИЙСК%' THEN 0 ELSE 1 END";
        }
        if (in_array('dest_road', $rowDims, true)) {
            $parts[] = "CASE WHEN UPPER(TRIM(dest_road)) LIKE '%СВЕРДЛОВ%' THEN 0 ELSE 1 END";
        }
        $parts[] = implode(', ', $rowDims);
        return implode(', ', array_filter($parts, static fn(string $part): bool => $part !== ''));
    }

    /**
     * WHERE для детализации: фильтр по строкам сводной + фильтр по колонкам сводной.
     *
     * $rowDims — поля GROUP BY строк (из group_by параметра)
     * $params  — все URL-параметры запроса
     * $bindings — биндинги, пополняются по ссылке
     */
    private function applyDetailFilters(array $rowDims, array $params, array &$bindings): string
    {
        // Фильтр по строкам: пустой JSON-массив от select не является значением строки.
        $whereCond = '';
        foreach ($rowDims as $field) {
            if (!isset($params[$field]))
                continue;
            $safe = preg_replace('/[^a-z0-9_]/i', '', $field);
            $whereCond .= $this->multiValueCondition($field, $params[$field], 'row_' . $safe, $bindings);
        }

        // Фильтр по колонкам: берёт выражения из единого реестра colDimRegistry
        foreach ($this->colDimRegistry() as $alias => $def) {
            $value = $params[$def['param']] ?? null;
            if ($value !== null && $value !== '') {
                $bind = 'col_' . $alias;
                $whereCond .= " AND {$def['expr']} = :$bind";
                $bindings[$bind] = $value;
            }
        }

        return $whereCond;
    }

    /** Добавляет безопасный IN-фильтр для одиночного или множественного значения. */
    private function multiValueCondition(
        string $field,
        mixed $value,
        string $prefix,
        array &$bindings,
        bool $normalize = false
    ): string {
        $values = [];
        if (is_array($value)) {
            $values = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            $values = is_array($decoded) ? $decoded : [$value];
        }
        $values = array_values(array_unique(array_filter(
            array_map(static fn($item): string => trim((string) $item), $values),
            static fn(string $item): bool => $item !== ''
        )));
        if ($values === []) {
            return '';
        }
        if (count($values) > 200) {
            throw new \InvalidArgumentException('Выбрано слишком много значений фильтра');
        }

        $safePrefix = preg_replace('/[^a-z0-9_]/i', '', $prefix);
        $placeholders = [];
        foreach ($values as $index => $item) {
            $bind = $safePrefix . '_' . $index;
            $bindings[$bind] = $normalize
                ? str_replace('Ё', 'Е', mb_strtoupper($item))
                : $item;
            $placeholders[] = ':' . $bind;
        }
        return ' AND ' . $field . ' IN (' . implode(', ', $placeholders) . ')';
    }

    // =========================================================================
    // Реестры и вспомогательные SQL-шаблоны
    // =========================================================================

    /**
     * Единый подход вычисляемых колонок сводной.
     * Источник для summaryReport (expr в SELECT/GROUP BY)
     * и для applyDetailFilters (expr в WHERE, param — имя URL-параметра).
     *
     * Добавить новую вычисляемую колонку
     * param должен совпадать с colDims[].paramName в app.js.
     */
    private function colDimRegistry(): array
    {
        return [
            'wagon_type_code' => ['expr' => self::WAG_TYPE_EXPR, 'param' => 'wagon_type'],
            'cargo_w_type' => ['expr' => self::WAG_STATE, 'param' => 'cargo_state'],
        ];
    }

    /**
     * Раскрывает col_by (алиасы через запятую) в определения колонок для summaryReport.
     * Возвращает [['alias' => ..., 'expr' => ...], ...].
     */
    private function resolveColDims(string $colBy, array $defaultAliases): array
    {
        $registry = $this->colDimRegistry();
        $aliases = $this->parseGroupBy($colBy, $defaultAliases);
        return array_values(array_filter(
            array_map(
                fn($a) => isset($registry[$a]) ? ['alias' => $a, 'expr' => $registry[$a]['expr']] : null,
                $aliases
            ),
            fn($c) => $c !== null
        ));
    }

    /** Если у колонки задан formatData — оборачивает expr в TO_CHAR(expr, 'mask'). */
    private function applyFormat(array $colDef): string
    {
        if (!empty($colDef['formatData'])) {
            return "TO_CHAR({$colDef['expr']}, '{$colDef['formatData']}')";
        }
        return $colDef['expr'];
    }

    /**
     * Парсит строку «field1,field2,...» в массив безопасных имён полей.
     * Если строка пуста или содержит только небезопасные имена — возвращает $defaults.
     */
    private function parseGroupBy(string $raw, array $defaults): array
    {
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => self::isSafeField($f)
        ));
        return $fields ?: $defaults;
    }

    /** Поля запроса. По умолчанию — wagon_no. */
    private function selectFields(string $raw): string
    {
        $fields = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn($f) => self::isSafeField($f)
        ));
        return $fields ? implode(', ', $fields) : 'wagon_no';
    }

    /** Сортировка по параметрам запроса. Если не задана — $default. */
    private function orderBY(array $params, string $default): string
    {
        $sortRaw = trim($params['sort'] ?? '');
        if ($sortRaw === '') {
            return $default;
        }
        $fields = array_map('trim', explode(',', $sortRaw));
        $dirs = array_map('trim', explode(',', $params['sort_dir'] ?? ''));
        $types = array_map('trim', explode(',', $params['sort_type'] ?? ''));

        $parts = [];
        foreach ($fields as $i => $field) {
            if ($field === '' || !self::isSafeField($field)) {
                continue;
            }
            $dir = strtoupper($dirs[$i] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $expr = strtolower($types[$i] ?? '') === 'number' ? "TO_NUMBER($field)" : $field;
            $parts[] = "$expr $dir";
        }

        return $parts ? implode(', ', $parts) : $default;
    }

    /** Добавляет фильтр wagon_no IN (...) если передан параметр wagon_no (через ';'). */
    private function wagonNoCond(array $params, array &$bindings): string
    {
        $wagonNo = trim($params['wagon_no'] ?? '');
        if ($wagonNo === '')
            return '';
        return ' AND wagon_no IN (' . $this->parserInValues($wagonNo, ';', 'wagon_no', $bindings) . ')';
    }

    /** Строит плейсхолдеры для SQL IN из строки с разделителем. */
    private function parserInValues(string $raw, string $delimiter, string $paramPrefix, array &$bindings): string
    {
        $items = array_filter(array_map('trim', explode($delimiter, $raw)));
        if (empty($items)) {
            return '';
        }
        $placeholders = [];
        foreach ($items as $i => $value) {
            $key = "{$paramPrefix}_{$i}";
            $placeholders[] = ':' . $key;
            $bindings[$key] = $value;
        }
        return implode(', ', $placeholders);
    }

    /** Допускает только безопасные имена полей: буквы, цифры, _ */
    private static function isSafeField(string $f): bool
    {
        return $f !== '' && (bool) preg_match('/^[a-z_][a-z0-9_]*$/iD', $f);
    }

    private function excludedWagonsCondition(): string
    {
        return $this->organizations->isMtf() ? self::EXCLUDED_WAGONS_COND : '';
    }

    // =========================================================================
    // Вспомогательные методы для работы с датами справок
    // =========================================================================

    /** MAX(report_dt) для указанного типа справки; если $dt передан — возвращает его сразу. */
    private function getReportDt(?string $dt, ?string $typeRef = null): ?string
    {
        if ($dt) {
            return $dt;
        }
        $cacheKey = $typeRef ?? '*';
        if (array_key_exists($cacheKey, $this->reportDtCache)) {
            return $this->reportDtCache[$cacheKey];
        }
        $sql = 'SELECT MAX(report_dt) AS dt FROM xx_dislocation_rjd';
        $filter = $this->organizations->filter();
        $sql .= ' WHERE ' . $filter['sql'];
        $params = $filter['params'];
        if ($typeRef !== null) {
            $sql .= ' AND type_reference = :type_ref';
            $params['type_ref'] = $typeRef;
        }
        $row = $this->db->fetchOne($sql, $params);
        $this->reportDtCache[$cacheKey] = $row['dt'] ?? null;
        return $this->reportDtCache[$cacheKey];
    }

    /** [type_reference => MAX(report_dt)] для указанных типов справок. */
    public function getLatestDtsByType(?string $dt = null, ?array $types = null): array
    {
        return (new \App\Services\ReportService($this->db, $this->organizations))->getLatestDtsByType($dt, $types);
    }

    /** Условие отбора актуальных справок с ограничением по организации. */
    public function latestDtCondition(array $dtsByType, string $alias = ''): array
    {
        return (new \App\Services\ReportService($this->db, $this->organizations))->latestDtCondition($dtsByType, $alias);
    }

    // =========================================================================
    // Построитель иерархической структуры для сводных таблиц
    // =========================================================================

    /**
     * Преобразует плоский результат SELECT (строки × колонки) в структуру для фронтенда:
     * { roads: [{road, stations: [{...v[]}], total: [], grand_total}, ...], col_groups/cols, metrics, total }
     *
     * $rows      — строки из БД
     * $rowDims   — поля строк (groupKeys[0] = верхний уровень, последний = нижний)
     * $colAliases — алиасы колонок в порядке, соответствующем flat-индексу
     */
    private function roadTable(array $rows, array $rowDims, array $colAliases): array
    {
        $topKey = $rowDims[0];
        $subKeys = array_slice($rowDims, 1);
        $nColLevels = count($colAliases);

        // Уникальные значения для каждого уровня колонок.
        $colValues = array_fill(0, $nColLevels, []);
        $colIndex = array_fill(0, $nColLevels, []);
        foreach ($rows as $r) {
            foreach ($colAliases as $k => $alias) {
                $v = (string) ($r[$alias] ?? '');
                if ($v !== '' && !isset($colIndex[$k][$v])) {
                    $colIndex[$k][$v] = count($colValues[$k]);
                    $colValues[$k][] = $v;
                }
            }
        }

        $colDims = array_map(fn($vals) => max(1, count($vals)), $colValues);
        $nFlat = (int) array_product($colDims);
        $roads = [];

        foreach ($rows as $r) {
            $topVal = (string) ($r[$topKey] ?? 'Не указана');
            $subComposite = implode('|', array_map(fn($k) => (string) ($r[$k] ?? ''), $subKeys)) ?: 'Не указана';
            $cnt = (int) $r['cnt'];

            if (!isset($roads[$topVal])) {
                $roads[$topVal] = [$topKey => $topVal, 'stations' => [], 'total' => array_fill(0, $nFlat, 0), 'grand_total' => 0];
            }
            if (!isset($roads[$topVal]['stations'][$subComposite])) {
                $stData = ['v' => array_fill(0, $nFlat, 0)];
                foreach ($subKeys as $k) {
                    $stData[$k] = (string) ($r[$k] ?? '');
                }
                $roads[$topVal]['stations'][$subComposite] = $stData;
            }

            $firstColVal = (string) ($r[$colAliases[0]] ?? '');
            if ($firstColVal === '' || !isset($colIndex[0][$firstColVal])) {
                continue;
            }

            $flatIdx = 0;
            foreach ($colAliases as $k => $alias) {
                $v = (string) ($r[$alias] ?? '');
                $flatIdx = $flatIdx * $colDims[$k] + ($colIndex[$k][$v] ?? 0);
            }

            $roads[$topVal]['stations'][$subComposite]['v'][$flatIdx] += $cnt;
            $roads[$topVal]['total'][$flatIdx] += $cnt;
            $roads[$topVal]['grand_total'] += $cnt;
        }

        foreach ($roads as &$road) {
            $road['stations'] = array_values($road['stations']);
        }
        unset($road);

        $roadList = array_values($roads);
        $metrics = array_map(fn($r) => ['label' => $r[$topKey], 'total' => $r['grand_total']], $roadList);
        $grandTotal = array_sum(array_column($metrics, 'total'));

        if ($nColLevels <= 1) {
            return ['cols' => $colValues[0], 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 20), 'total' => $grandTotal];
        }

        $buildColTree = function (int $level) use (&$buildColTree, $colValues, $nColLevels) {
            $isLeaf = $level === $nColLevels - 1;
            $out = [];
            foreach ($colValues[$level] as $v) {
                $out[] = $isLeaf ? $v : ['label' => $v, 'subs' => $buildColTree($level + 1)];
            }
            return $out;
        };

        return ['col_groups' => $buildColTree(0), 'roads' => $roadList, 'metrics' => array_slice($metrics, 0, 20), 'total' => $grandTotal];
    }

    /**
     * Экспорт виртуальной таблицы (динамической детализации) из JS
     */
    public function exportVirtualTable(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $rawData = json_decode($parsedBody['export_data'] ?? '[]', true);
        $filename = $parsedBody['filename'] ?? 'детализация';
        $tableType = ($parsedBody['table_type'] ?? 'detail') === 'summary' ? 'summary' : 'detail';

        $cols = $rawData['cols'] ?? [];
        $rows = $rawData['rows'] ?? [];

        return \App\ExcelExport::download($response, $cols, $rows, $filename, $tableType);
    }

    /**
     * Экспорт сложных матричных таблиц (двухуровневых шахматок дашборда)
     */
    public function exportMatrixTable(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $rawData = json_decode($parsedBody['matrix_data'] ?? '[]', true);
        $filename = $parsedBody['filename'] ?? 'Сводный_отчет';

        $colGroups = $rawData['col_groups'] ?? [];
        $cols = $rawData['cols'] ?? [];
        $groupCols = $rawData['group_cols'] ?? [];
        $roads = $rawData['roads'] ?? [];

        return \App\ExcelExport::downloadMatrix($response, $colGroups, $roads, $filename, $groupCols, $cols);
    }

    /** GET /api/table-layout/{reportCode} */
    public function tableLayout(ServerRequestInterface $request, ResponseInterface $response, string $reportCode): ResponseInterface
    {
        try {
            $userId = (int) ($_SESSION['user']['id'] ?? 0);
            return $this->json($response, (new TableLayoutService($this->db))->get($userId, $reportCode));
        } catch (\InvalidArgumentException $error) {
            return $this->json($response->withStatus(422), ['error' => $error->getMessage()]);
        } catch (\Throwable $error) {
            ErrorLogger::logThrowable($error, ['operation' => 'table_layout_read', 'report_code' => $reportCode]);
            return $this->json($response->withStatus(500), ['error' => 'Не удалось загрузить настройки таблицы']);
        }
    }

    /** POST /api/table-layout/{reportCode}: columns — порядок, widths — ширины по ключам колонок. */
    public function saveTableLayout(ServerRequestInterface $request, ResponseInterface $response, string $reportCode): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $csrf = (string) ($body['csrf_token'] ?? '');
        if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) {
            return $response->withStatus(403);
        }
        try {
            if (array_key_exists('columns', $body) && array_key_exists('widths', $body)) {
                throw new \InvalidArgumentException('Порядок и ширину колонок сохраняйте отдельными запросами');
            }
            $service = new TableLayoutService($this->db);
            $userId = (int) ($_SESSION['user']['id'] ?? 0);
            $layout = null;
            if (array_key_exists('columns', $body)) {
                $keys = json_decode((string) $body['columns'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($keys)) {
                    throw new \InvalidArgumentException('Некорректный порядок колонок');
                }
                $layout = $service->save($userId, $reportCode, $keys);
            }
            if (array_key_exists('widths', $body)) {
                $widths = json_decode((string) $body['widths'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($widths)) {
                    throw new \InvalidArgumentException('Некорректная ширина колонок');
                }
                $layout = $service->saveWidths($userId, $reportCode, $widths);
            }
            if ($layout === null) {
                throw new \InvalidArgumentException('Не переданы настройки таблицы');
            }
            return $this->json($response, $layout);
        } catch (\InvalidArgumentException | \JsonException $error) {
            return $this->json($response->withStatus(422), ['error' => $error->getMessage()]);
        } catch (\Throwable $error) {
            ErrorLogger::logThrowable($error, ['operation' => 'table_layout_save', 'report_code' => $reportCode]);
            return $this->json($response->withStatus(500), ['error' => 'Не удалось сохранить настройки таблицы']);
        }
    }

    /** POST /api/table-layout/{reportCode}/reset */
    public function resetTableLayout(ServerRequestInterface $request, ResponseInterface $response, string $reportCode): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $csrf = (string) ($body['csrf_token'] ?? '');
        if ($csrf === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) {
            return $response->withStatus(403);
        }
        try {
            (new TableLayoutService($this->db))->reset((int) ($_SESSION['user']['id'] ?? 0), $reportCode);
            return $this->json($response, ['status' => 'ok']);
        } catch (\Throwable $error) {
            ErrorLogger::logThrowable($error, ['operation' => 'table_layout_reset', 'report_code' => $reportCode]);
            return $this->json($response->withStatus(500), ['error' => 'Не удалось сбросить настройки таблицы']);
        }
    }

    private function json(ResponseInterface $response, $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function responseJson(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        return is_array($data) ? $data : [];
    }
}
