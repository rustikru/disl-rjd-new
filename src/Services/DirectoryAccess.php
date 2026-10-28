<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class DirectoryAccess
{
    private ?array $pages = null;

    public function __construct(private DbInterface $db) {}

    public static function expandPages(array $pages): array
    {
        if (in_array('directories_edit', $pages, true)) {
            $pages[] = 'directories';
        }
        return array_values(array_unique($pages));
    }

    public function canView(): bool
    {
        return !empty($_SESSION['user']['is_admin']) || in_array('directories', $this->pages(), true);
    }

    public function canEdit(): bool
    {
        return !empty($_SESSION['user']['is_admin']) || in_array('directories_edit', $this->pages(), true);
    }

    private function pages(): array
    {
        if ($this->pages === null) {
            $rows = $this->db->fetchAll(
                'SELECT p.page FROM xx_rjd_role_pages p JOIN xx_rjd_user_roles ur ON ur.role_id = p.role_id WHERE ur.user_id = :id',
                ['id' => (int) ($_SESSION['user']['id'] ?? 0)]
            );
            $this->pages = self::expandPages(array_column($rows, 'page'));
        }
        return $this->pages;
    }
}
