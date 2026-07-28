<?php
declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\KerberosAuth;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class KerberosAuthTest extends TestCase
{
    private function request(array $serverParams): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getServerParams')->willReturn($serverParams);

        return $request;
    }

    public function testLocalModeAuthenticatesConfiguredUserFromLocalhost(): void
    {
        $auth = new KerberosAuth([
            'app_env' => 'development',
            'kerberos_enabled' => true,
            'kerberos_mode' => 'local',
            'kerberos_local_username' => 'administrator',
        ]);

        $identity = $auth->authenticate($this->request(['REMOTE_ADDR' => '127.0.0.1']));

        $this->assertSame('administrator', $identity['username']);
        $this->assertSame('kerberos', $identity['auth_source']);
    }

    public function testLocalModeIsDisabledInProduction(): void
    {
        $auth = new KerberosAuth([
            'app_env' => 'production',
            'kerberos_enabled' => true,
            'kerberos_mode' => 'local',
            'kerberos_local_username' => 'administrator',
        ]);

        $identity = $auth->authenticate($this->request(['REMOTE_ADDR' => '127.0.0.1']));

        $this->assertNull($identity);
    }

    public function testLocalModeRejectsExternalAddress(): void
    {
        $auth = new KerberosAuth([
            'app_env' => 'development',
            'kerberos_enabled' => true,
            'kerberos_mode' => 'local',
            'kerberos_local_username' => 'administrator',
        ]);

        $identity = $auth->authenticate($this->request(['REMOTE_ADDR' => '10.0.0.5']));

        $this->assertNull($identity);
    }

    public function testServerModeReadsRemoteUserAndRemovesRealm(): void
    {
        $auth = new KerberosAuth([
            'kerberos_enabled' => true,
            'kerberos_mode' => 'server',
            'kerberos_server_key' => 'REMOTE_USER',
            'kerberos_strip_realm' => true,
        ]);

        $identity = $auth->authenticate(
            $this->request(['REMOTE_USER' => 'ivanov@MF.METAFRAX.RU'])
        );

        $this->assertSame('ivanov', $identity['username']);
    }

    public function testServerModeSupportsDomainUsernameFormat(): void
    {
        $auth = new KerberosAuth([
            'kerberos_enabled' => true,
            'kerberos_mode' => 'server',
            'kerberos_server_key' => 'REMOTE_USER',
        ]);

        $identity = $auth->authenticate(
            $this->request(['REMOTE_USER' => 'MF\\ivanov'])
        );

        $this->assertSame('ivanov', $identity['username']);
    }
}
