<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class OrganizationService
{
    private DbInterface $db;
    private ?int $fixedId;
    private ?array $organizations = null;
    private ?int $activeId = null;

    public function __construct(DbInterface $db, ?int $fixedId = null)
    {
        $this->db = $db;
        $this->fixedId = $fixedId;
    }

    public function sync(): void
    {
        if ($this->fixedId !== null) {
            $organization = $this->db->fetchOne(
                'SELECT id, code, name, short_name, 1 AS is_primary
                   FROM xx_rjd_organizations
                  WHERE id = :id
                    AND is_active = 1',
                ['id' => $this->fixedId]
            );
            $this->organizations = $organization ? [$organization] : [];
            $this->activeId = $organization ? $this->fixedId : null;
            if ($this->activeId !== null) {
                $this->setClientId($this->activeId);
            } else {
                $this->clearClientId();
            }
            return;
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $this->organizations = $this->loadOrganizations($userId);

        $allowedIds = array_map(
            static fn(array $organization): int => (int) $organization['id'],
            $this->organizations
        );
        $sessionId = (int) ($_SESSION['organization_id'] ?? 0);

        if ($sessionId > 0 && in_array($sessionId, $allowedIds, true)) {
            $this->activeId = $sessionId;
        } else {
            $this->activeId = $allowedIds[0] ?? null;
        }

        if ($this->activeId !== null) {
            $_SESSION['organization_id'] = $this->activeId;
            $this->setClientId($this->activeId);
        } else {
            unset($_SESSION['organization_id']);
            $this->clearClientId();
        }

        $_SESSION['organizations'] = $this->organizations;
    }

    public function organizations(): array
    {
        if ($this->organizations === null) {
            $this->sync();
        }
        return $this->organizations ?? [];
    }

    public function id(): ?int
    {
        if ($this->organizations === null) {
            $this->sync();
        }
        return $this->activeId;
    }

    public function code(): ?string
    {
        $activeId = $this->id();
        foreach ($this->organizations() as $organization) {
            if ((int) $organization['id'] === $activeId) {
                return (string) $organization['code'];
            }
        }
        return null;
    }

    public function isMtf(): bool
    {
        return strtoupper((string) $this->code()) === 'MTF';
    }

    public function set(int $organizationId): bool
    {
        $ids = array_map(
            static fn(array $organization): int => (int) $organization['id'],
            $this->organizations()
        );
        if (!in_array($organizationId, $ids, true)) {
            return false;
        }

        $this->activeId = $organizationId;
        $_SESSION['organization_id'] = $organizationId;
        $this->setClientId($organizationId);
        return true;
    }

    public function filter(string $alias = ''): array
    {
        $organizationId = $this->id();
        if ($organizationId === null) {
            return ['sql' => '1=0', 'params' => []];
        }

        $column = $alias !== '' ? $alias . '.organization_id' : 'organization_id';
        return [
            'sql' => $column . ' = :organization_id',
            'params' => ['organization_id' => $organizationId],
        ];
    }

    public function addFilter(string &$where, array &$params, string $alias = ''): void
    {
        $filter = $this->filter($alias);
        $where .= ' AND ' . $filter['sql'];
        $params = array_merge($params, $filter['params']);
    }

    private function loadOrganizations(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $result = [];
        $primary = $this->db->fetchOne(
            'SELECT o.id, o.code, o.name, o.short_name, 1 AS is_primary
               FROM xx_rjd_users u
               JOIN xx_rjd_organizations o ON o.id = u.organization_id
              WHERE u.id = :user_id
                AND o.is_active = 1',
            ['user_id' => $userId]
        );
        if ($primary) {
            $result[(int) $primary['id']] = $primary;
        }

        try {
            $extraOrganizations = $this->db->fetchAll(
                'SELECT o.id, o.code, o.name, o.short_name, 0 AS is_primary
                   FROM xx_rjd_user_organizations uo
                   JOIN xx_rjd_organizations o ON o.id = uo.organization_id
                  WHERE uo.user_id = :user_id
                    AND o.is_active = 1
                  ORDER BY o.name',
                ['user_id' => $userId]
            );
            foreach ($extraOrganizations as $organization) {
                $id = (int) $organization['id'];
                if (!isset($result[$id])) {
                    $result[$id] = $organization;
                }
            }
        } catch (\Throwable $error) {
            // Дополнительные организации могли быть ещё не перенесены в старой схеме.
        }

        return array_values($result);
    }

    private function setClientId(int $organizationId): void
    {
        $this->db->execute(
            'BEGIN DBMS_SESSION.SET_IDENTIFIER(:organization_id); END;',
            ['organization_id' => (string) $organizationId]
        );
    }

    private function clearClientId(): void
    {
        $this->db->execute('BEGIN DBMS_SESSION.CLEAR_IDENTIFIER; END;');
    }
}
