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
        $ttlMinutes = (int) $this->config['password_reset_ttl_minutes'];

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
        $this->saveOrSendMessage($user, $resetUrl);
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
            (new AuditLog($this->db))->write(
                'PASSWORD_RESET',
                'USER',
                (int) $reset['user_id'],
                'Пароль изменён по ссылке восстановления',
                (int) $reset['user_id']
            );
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

    private function saveOrSendMessage(array $user, string $resetUrl): void
    {
        $mode = (string) $this->config['mail_mode'];
        if (!in_array($mode, ['file', 'oracle'], true)) {
            throw new \RuntimeException('Режим mail_mode должен быть file или oracle');
        }

        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<!DOCTYPE html>'
            . '<html lang="ru">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<meta name="color-scheme" content="light">'
            . '<title>Сброс пароля — Дислокация</title>'
            . '</head>'
            . '<body style="margin:0;padding:0;background:#f3f2f6;font-family:Arial,Helvetica,sans-serif;color:#262334;">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">Сброс пароля для вашей учётной записи.</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;background:#f3f2f6;">'
            . '<tr><td align="center" style="padding:36px 16px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;border-collapse:separate;border-spacing:0;">'
            . '<tr><td align="center" style="padding:0 0 18px;">'
            . '<div style="font-size:21px;line-height:28px;font-weight:700;letter-spacing:-.4px;color:#46297f;">Дислокация</div>'
            . '<div style="padding-top:3px;font-size:11px;line-height:16px;color:#8b8797;letter-spacing:.5px;">АО «Метафракс-Кемикалс»</div>'
            . '</td></tr>'
            . '<tr><td style="background:#fff;border:1px solid #e7e4ed;border-radius:12px;padding:34px 36px 30px;">'
            . '<div style="font-size:19px;line-height:26px;font-weight:700;color:#262334;margin:0 0 14px;">Сброс пароля</div>'
            . '<div style="font-size:14px;line-height:22px;color:#625e6e;margin:0 0 22px;">Чтобы сбросить пароль, нажмите кнопку ниже.</div>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 0 22px;">'
            . '<tr><td align="center" bgcolor="#46297f" style="border-radius:7px;">'
            . '<a href="' . $safeUrl . '" target="_blank" style="display:inline-block;padding:12px 22px;font-size:13px;line-height:18px;font-weight:700;color:#fff;text-decoration:none;border-radius:7px;">Создать новый пароль</a>'
            . '</td></tr></table>'
            . '<div style="height:1px;background:#eeecf2;margin:24px 0 18px;font-size:1px;line-height:1px;">&nbsp;</div>'
            . '<div style="font-size:12px;line-height:19px;color:#777282;">Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо. Ваш текущий пароль останется без изменений.</div>'
            . '</td></tr>'
            . '<tr><td align="center" style="padding:18px 20px 0;">'
            . '<div style="font-size:10px;line-height:16px;color:#9a96a4;">Это письмо создано автоматически. Пожалуйста, не отвечайте на него.</div>'
            . '</td></tr>'
            . '</table></td></tr></table>'
            . '</body></html>';

        if ($mode === 'oracle') {
            (new OracleMail($this->db, $this->config))->sendSystem(
                [(string) $user['email']],
                'Сброс пароля',
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

