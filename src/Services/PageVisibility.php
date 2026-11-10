<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class PageVisibility
{
    private const CACHE_SECONDS = 30;

    public function __construct(private DbInterface $db)
    {
    }

    public function disabledPages(): array
    {
        $cachedAt = (int) ($_SESSION['disabled_pages_cached_at'] ?? 0);
        if (isset($_SESSION['disabled_pages']) && $cachedAt > time() - self::CACHE_SECONDS) {
            return (array) $_SESSION['disabled_pages'];
        }

        $disabledPages = array_map(
            static fn(array $row): string => (string) $row['page_code'],
            $this->db->fetchAll('SELECT page_code FROM xx_rjd_disabled_pages ORDER BY page_code')
        );
        $_SESSION['disabled_pages'] = $disabledPages;
        $_SESSION['disabled_pages_cached_at'] = time();
        return $disabledPages;
    }

    public function save(array $disabledPages, ?int $userId): void
    {
        $disabledPages = array_values(array_unique(array_filter(array_map(
            static fn(mixed $page): string => strtolower(trim((string) $page)),
            $disabledPages
        ))));

        $this->db->beginTransaction();
        try {
            $this->db->execute('DELETE FROM xx_rjd_disabled_pages');
            foreach ($disabledPages as $page) {
                $this->db->execute(
                    'INSERT INTO xx_rjd_disabled_pages (page_code, disabled_by)
                     VALUES (:page_code, :disabled_by)',
                    ['page_code' => $page, 'disabled_by' => $userId]
                );
            }
            $this->db->commit();
            $_SESSION['disabled_pages'] = $disabledPages;
            $_SESSION['disabled_pages_cached_at'] = time();
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }
}
