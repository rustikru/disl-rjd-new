<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class AuditLog
{
    public function __construct(private DbInterface $db)
    {
    }

    public function write(
        string $action,
        ?string $objectType = null,
        int|string|null $objectId = null,
        ?string $description = null,
        ?int $userId = null,
        ?string $username = null,
        ?string $ipAddress = null
    ): void {
        $sessionUser = (array) ($_SESSION['user'] ?? []);
        $userId ??= isset($sessionUser['id']) ? (int) $sessionUser['id'] : null;
        $username ??= trim((string) ($sessionUser['username'] ?? '')) ?: null;
        $ipAddress ??= $this->clientIp();

        try {
            $this->db->execute(
                'INSERT INTO xx_rjd_audit_log
                    (id, user_id, username, action_code, object_type, object_id, description, ip_address)
                 VALUES
                    (xx_rjd_audit_log_seq.NEXTVAL, :user_id, :username, :action_code,
                     :object_type, :object_id, :description, :ip_address)',
                [
                    'user_id' => $userId,
                    'username' => $this->cut($username, 100),
                    'action_code' => $this->cut($action, 60),
                    'object_type' => $this->cut($objectType, 60),
                    'object_id' => $this->cut($objectId === null ? null : (string) $objectId, 100),
                    'description' => $this->cut($description, 1000),
                    'ip_address' => $this->cut($ipAddress, 45),
                ]
            );
        } catch (\Throwable $error) {
            // Ошибка аудита не должна отменять выполненное действие пользователя.
        }
    }

    private function clientIp(): ?string
    {
        $value = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $value !== '' ? $value : null;
    }

    private function cut(int|string|null $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return mb_substr((string) $value, 0, $length);
    }
}
