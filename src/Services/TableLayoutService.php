<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;
use App\Reports\ReportConfig;

final class TableLayoutService
{
    public function __construct(private DbInterface $db)
    {
    }

    public function get(int $userId, string $reportCode): array
    {
        $columns = $this->columns($reportCode);
        $saved = $this->db->fetchAll(
            'SELECT c.column_key, c.position_no, c.is_hidden, c.is_pinned
               FROM xx_rjd_table_layouts l
               JOIN xx_rjd_table_layout_columns c ON c.layout_id = l.id
              WHERE l.user_id = :user_id AND l.report_code = :report_code
              ORDER BY c.position_no',
            ['user_id' => $userId, 'report_code' => $reportCode]
        );
        $savedByKey = [];
        foreach ($saved as $row) {
            $savedByKey[(string) $row['column_key']] = $row;
        }

        $ordered = [];
        foreach ($saved as $row) {
            $key = (string) $row['column_key'];
            if (isset($columns[$key])) {
                $ordered[] = $this->columnState($columns[$key], $row);
            }
        }
        foreach ($columns as $key => $column) {
            if (!isset($savedByKey[$key])) {
                $ordered[] = $this->columnState($column, []);
            }
        }
        usort($ordered, static function (array $left, array $right): int {
            return ((int) !$left['pinned']) <=> ((int) !$right['pinned']);
        });

        return [
            'report_code' => $reportCode,
            'columns' => $ordered,
        ];
    }

    public function save(int $userId, string $reportCode, array $keys): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Пользователь не найден');
        }
        $columns = $this->columns($reportCode);
        $keys = array_values(array_unique(array_map('strval', $keys)));
        foreach ($keys as $key) {
            if (!isset($columns[$key])) {
                throw new \InvalidArgumentException('Недопустимая колонка отчёта');
            }
        }
        foreach (array_keys($columns) as $key) {
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }
        $pinned = [];
        $regular = [];
        foreach ($keys as $key) {
            if (!empty($columns[$key]['pinned'])) {
                $pinned[] = $key;
            } else {
                $regular[] = $key;
            }
        }
        $keys = array_merge($pinned, $regular);

        $this->db->beginTransaction();
        try {
            $layout = $this->db->fetchOne(
                'SELECT id FROM xx_rjd_table_layouts WHERE user_id = :user_id AND report_code = :report_code',
                ['user_id' => $userId, 'report_code' => $reportCode]
            );
            if ($layout) {
                $layoutId = (int) $layout['id'];
                $this->db->execute(
                    'DELETE FROM xx_rjd_table_layout_columns WHERE layout_id = :layout_id',
                    ['layout_id' => $layoutId]
                );
                $this->db->execute(
                    'UPDATE xx_rjd_table_layouts SET updated_at = SYSDATE WHERE id = :id',
                    ['id' => $layoutId]
                );
            } else {
                $this->db->execute(
                    'INSERT INTO xx_rjd_table_layouts (id, user_id, report_code) VALUES (xx_rjd_table_layouts_seq.NEXTVAL, :user_id, :report_code)',
                    ['user_id' => $userId, 'report_code' => $reportCode]
                );
                $layout = $this->db->fetchOne(
                    'SELECT id FROM xx_rjd_table_layouts WHERE user_id = :user_id AND report_code = :report_code',
                    ['user_id' => $userId, 'report_code' => $reportCode]
                );
                $layoutId = (int) ($layout['id'] ?? 0);
            }
            foreach ($keys as $position => $key) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_table_layout_columns (id, layout_id, column_key, position_no, is_pinned)
                     VALUES (xx_rjd_table_layout_col_seq.NEXTVAL, :layout_id, :column_key, :position_no, :is_pinned)',
                    [
                        'layout_id' => $layoutId,
                        'column_key' => $key,
                        'position_no' => $position + 1,
                        'is_pinned' => !empty($columns[$key]['pinned']) ? 'Y' : 'N',
                    ]
                );
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }

        return $this->get($userId, $reportCode);
    }

    public function reset(int $userId, string $reportCode): void
    {
        $this->db->execute(
            'DELETE FROM xx_rjd_table_layouts WHERE user_id = :user_id AND report_code = :report_code',
            ['user_id' => $userId, 'report_code' => $reportCode]
        );
    }

    private function columns(string $reportCode): array
    {
        $definition = ReportConfig::forContext('page')[$reportCode] ?? null;
        if (!$definition || empty($definition['detail_columns'])) {
            throw new \InvalidArgumentException('Неизвестный отчёт');
        }
        $columns = [];
        foreach ($definition['detail_columns'] as $column) {
            $columns[(string) $column['key']] = $column;
        }
        return $columns;
    }

    private function columnState(array $column, array $saved): array
    {
        return [
            'key' => (string) $column['key'],
            'label' => (string) ($column['label'] ?? $column['key']),
            'pinned' => !empty($column['pinned']),
            'hidden' => ($saved['is_hidden'] ?? 'N') === 'Y',
        ];
    }
}
