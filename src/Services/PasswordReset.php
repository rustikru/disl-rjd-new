<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\DbInterface;

final class PasswordReset
{
    public function __construct(private DbInterface $db, private array $config)
    {
    }

    public function request(string $username, string $baseUrl, string $ipAddress): void
    {
        $user = $this->db->fetchOne(
            "SELECT id, username, display_name, email
               FROM xx_rjd_users
              WHERE username = :username
                AND is_active = 1
                AND email IS NOT NULL
                AND LENGTH(TRIM(email)) > 0
                AND password_hash IS NOT NULL
                AND LENGTH(password_hash) > 0",
            ['username' => trim($username)]
        );

        // Ответ страницы всегда одинаковый, чтобы нельзя было проверить наличие логина.
        if (!$user || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $recent = $this->db->fetchOne(
            "SELECT id
               FROM xx_rjd_password_resets
              WHERE user_id = :user_id
                AND created_at > SYSDATE - (2 / 1440)
                AND ROWNUM = 1",
            ['user_id' => (int) $user['id']]
        );
        if ($recent) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $ttlMinutes = max(5, min(1440, (int) ($this->config['password_reset_ttl_minutes'] ?? 30)));

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE xx_rjd_password_resets SET used_at = SYSDATE WHERE user_id = :user_id AND used_at IS NULL',
                ['user_id' => (int) $user['id']]
            );
            $this->db->execute(
                "INSERT INTO xx_rjd_password_resets
                    (user_id, token_hash, expires_at, request_ip)
                 VALUES
                    (:user_id, :token_hash, SYSDATE + (:ttl_minutes / 1440), :request_ip)",
                [
                    'user_id' => (int) $user['id'],
                    'token_hash' => $tokenHash,
                    'ttl_minutes' => $ttlMinutes,
                    'request_ip' => mb_substr($ipAddress, 0, 45),
                ]
            );
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }

        $resetUrl = rtrim($baseUrl, '/') . '/reset-password?token=' . rawurlencode($token);
        $this->saveOrSendMessage($user, $resetUrl, $ttlMinutes);
    }

    public function isTokenValid(string $token): bool
    {
        return $this->findToken($token) !== null;
    }

    public function reset(string $token, string $password): bool
    {
        $reset = $this->findToken($token);
        if (!$reset) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                'UPDATE xx_rjd_users SET password_hash = :password_hash WHERE id = :user_id AND is_active = 1',
                [
                    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    'user_id' => (int) $reset['user_id'],
                ]
            );
            $this->db->execute(
                'UPDATE xx_rjd_password_resets SET used_at = SYSDATE WHERE user_id = :user_id AND used_at IS NULL',
                ['user_id' => (int) $reset['user_id']]
            );
            $this->db->commit();
            return true;
        } catch (\Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
    }

    private function findToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }

        return $this->db->fetchOne(
            "SELECT r.id, r.user_id
               FROM xx_rjd_password_resets r
               JOIN xx_rjd_users u ON u.id = r.user_id
              WHERE r.token_hash = :token_hash
                AND r.used_at IS NULL
                AND r.expires_at > SYSDATE
                AND u.is_active = 1",
            ['token_hash' => hash('sha256', $token)]
        );
    }

    private function saveOrSendMessage(array $user, string $resetUrl, int $ttlMinutes): void
    {
        $mode = (string) ($this->config['mail_mode'] ?? 'file');
        if (!in_array($mode, ['file', 'oracle'], true)) {
            throw new \RuntimeException('Режим mail_mode должен быть file или oracle');
        }

        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html lang="ru"><head><meta charset="utf-8"><title>Восстановление пароля</title>'
            . '<style>body{font-family:Arial,sans-serif;color:#1c2128;line-height:1.5;padding:32px}'
            . '.card{max-width:620px;margin:auto;border:1px solid #e2e1e7;border-radius:12px;padding:28px}'
            . 'a{display:inline-block;background:#4f328e;color:#fff!important;text-decoration:none;padding:11px 18px;border-radius:7px}'
            . '.muted{color:#777;font-size:13px}</style></head><body><div class="card">'
            . '<h2>Восстановление пароля</h2><p>Здравствуйте.</p>'
            . '<p>Чтобы установить новый пароль, нажмите кнопку ниже.</p>'
            . '<p><a href="' . $safeUrl . '">Сбросить пароль</a></p>'
            . '</div></body></html>';

        if ($mode === 'oracle') {
            (new OracleMail($this->db, $this->config))->send(
                [(string) $user['email']],
                'Восстановление пароля',
                $html,
                []
            );
            return;
        }

        $directory = rtrim((string) ($this->config['password_reset_storage_dir'] ?? ''), '/');
        if ($directory === '') {
            $directory = dirname(__DIR__, 2) . '/tmp/password-reset';
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Не удалось создать папку писем восстановления');
        }
        $file = $directory . '/password-reset-' . date('Y-m-d_H-i-s') . '-' . bin2hex(random_bytes(4)) . '.html';
        if (file_put_contents($file, $html, LOCK_EX) === false) {
            throw new \RuntimeException('Не удалось сохранить письмо восстановления');
        }
        @chmod($file, 0600);
    }
}

