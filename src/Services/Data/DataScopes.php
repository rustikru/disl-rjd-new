<?php
declare(strict_types=1);

namespace App\Services\Data;

use App\Database\DbInterface;

final class DataScopes
{
    public function __construct(private DbInterface $db) {}

    public function available(): bool
    {
        $row = $this->db->fetchOne("SELECT COUNT(*) cnt FROM user_tables WHERE table_name IN ('XX_RJD_DATA_SCOPES', 'XX_RJD_USER_DATA_SCOPES')");
        return (int) ($row['cnt'] ?? 0) === 2;
    }

    public function options(): array
    {
        return $this->available() ? $this->db->fetchAll('SELECT id, name, is_active FROM xx_rjd_data_scopes ORDER BY name') : [];
    }

    public function userIds(int $userId): array
    {
        return $this->available() ? array_map('intval', array_column($this->db->fetchAll('SELECT scope_id FROM xx_rjd_user_data_scopes WHERE user_id = :id', ['id' => $userId]), 'scope_id')) : [];
    }

    public function saveUser(int $userId, array $input): void
    {
        if (!$this->available()) {
            if ($input) throw new \InvalidArgumentException('Сначала установите SQL-объекты областей данных.');
            return;
        }
        $ids = array_values(array_unique(array_map('intval', $input)));
        foreach ($ids as $id) {
            if ($id <= 0 || !$this->db->fetchOne('SELECT id FROM xx_rjd_data_scopes WHERE id = :id', ['id' => $id])) {
                throw new \InvalidArgumentException('Выбранная область данных не найдена.');
            }
        }
        $this->db->execute('DELETE FROM xx_rjd_user_data_scopes WHERE user_id = :id', ['id' => $userId]);
        foreach ($ids as $id) {
            $this->db->execute('INSERT INTO xx_rjd_user_data_scopes (user_id, scope_id) VALUES (:user_id, :scope_id)', ['user_id' => $userId, 'scope_id' => $id]);
        }
    }
}
