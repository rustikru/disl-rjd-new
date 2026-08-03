<?php
declare(strict_types=1);

namespace App\Auth;

use Psr\Http\Message\ServerRequestInterface;

final class KerberosAuth
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function authenticate(ServerRequestInterface $request): ?array
    {
        if (!(bool) ($this->config['kerberos_enabled'] ?? false)) {
            return null;
        }

        $mode = (string) ($this->config['kerberos_mode'] ?? 'local');
        if ($mode === 'local') {
            return $this->authenticateLocal($request);
        }

        if ($mode !== 'server') {
            return null;
        }

        $serverParams = $request->getServerParams();
        $serverKey = (string) ($this->config['kerberos_server_key'] ?? 'REMOTE_USER');
        $principal = trim((string) ($serverParams[$serverKey] ?? ''));

        return $this->identity($principal);
    }

    private function authenticateLocal(ServerRequestInterface $request): ?array
    {
        if (($this->config['app_env'] ?? 'production') !== 'development') {
            return null;
        }

        $remoteAddress = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
        if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
            return null;
        }

        return $this->identity((string) ($this->config['kerberos_local_username'] ?? ''));
    }

    private function identity(string $principal): ?array
    {
        if ($principal === '') {
            return null;
        }

        $username = $principal;
        if (str_contains($username, '\\')) {
            $username = substr($username, strrpos($username, '\\') + 1);
        }
        if (($this->config['kerberos_strip_realm'] ?? true) && str_contains($username, '@')) {
            $username = strstr($username, '@', true);
        }
        $username = trim($username);

        if ($username === '') {
            return null;
        }

        return [
            'username' => $username,
            'display_name' => $username,
            'email' => '',
            'auth_source' => 'kerberos',
        ];
    }
}
