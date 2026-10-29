<?php
declare(strict_types=1);
namespace App\Services;

use App\Database\DbInterface;

final class SessionRegistry
{
    public function __construct(private DbInterface $db) {}

    public function touch(string $id, array $user, string $ip, string $agent): bool
    {
        $key = hash('sha256', $id);
        $now = time();
        $this->db->execute('MERGE INTO xx_rjd_sessions t USING (SELECT :sid session_key FROM dual) s ON (t.session_key = s.session_key)
            WHEN MATCHED THEN UPDATE SET t.seen = :seen WHERE t.revoked = 0
            WHEN NOT MATCHED THEN INSERT (session_key, user_id, username, display_name, ip, agent, started, seen, revoked)
            VALUES (:sid, :p_user_id, :username, :display_name, :ip, :agent, :started, :seen, 0)',
            ['sid'=>$key, 'p_user_id'=>(string) ($user['id'] ?? $user['username']), 'username'=>$user['username'] ?? '', 'display_name'=>$user['display_name'] ?? '', 'ip'=>substr($ip,0,64), 'agent'=>mb_strcut($agent,0,1000,'UTF-8'), 'started'=>$now, 'seen'=>$now]);
        $row = $this->db->fetchOne('SELECT revoked FROM xx_rjd_sessions WHERE session_key = :sid', ['sid'=>$key]);
        return $row !== null && (int) $row['revoked'] === 0;
    }

    public function active(): array
    {
        $rows = $this->db->fetchAll('SELECT session_key, user_id, username, display_name, ip, agent, started, seen FROM xx_rjd_sessions WHERE revoked = 0 AND seen > :cutoff ORDER BY seen DESC', ['cutoff'=>time()-28800]);
        $result = [];
        foreach ($rows as $row) {
            $row['name'] = $row['display_name'] ?? '';
            $row['username'] ??= '';
            $row['ip'] ??= '';
            $row['agent'] ??= '';
            $row['started'] = (int) $row['started'];
            $row['seen'] = (int) $row['seen'];
            $result[$row['session_key']] = $row;
        }
        return $result;
    }

    public function revokeUsers(array $userIds): void
    {
        if (!$userIds) return;
        $params = []; $placeholders = [];
        foreach (array_values(array_unique($userIds)) as $index => $id) {
            $params['u'.$index] = (string) $id;
            $placeholders[] = ':u'.$index;
        }
        $this->db->execute('UPDATE xx_rjd_sessions SET revoked = 1 WHERE user_id IN ('.implode(',', $placeholders).')', $params);
    }

    public function revoke(string $key): void
    {
        $this->db->execute('UPDATE xx_rjd_sessions SET revoked = 1 WHERE session_key = :sid', ['sid'=>$key]);
    }
}
