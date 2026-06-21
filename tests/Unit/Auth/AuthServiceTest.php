<?php
declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\AuthService;
use App\Database\DbInterface;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private function makeDb(
        ?array $userRow = null,
        array $rolesRows = []
    ): DbInterface {
        $db = $this->createMock(DbInterface::class);

        $db->method('fetchOne')->willReturn($userRow);
        $db->method('fetchAll')->willReturn($rolesRows);

        return $db;
    }

    private function makeService(DbInterface $db): AuthService
    {
        return new AuthService($db, [
            'ad_enabled' => false,
            'ad_host'    => '',
            'ad_port'    => 389,
            'ad_base_dn' => '',
            'ad_domain'  => '',
        ]);
    }

    public function testValidCredentialsReturnsUser(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);

        $db = $this->makeDb(
            ['id' => 1, 'username' => 'alice', 'display_name' => 'Alice', 'email' => 'alice@test.com', 'password_hash' => $hash, 'is_active' => 1],
            [['id' => 3, 'code' => 'VIEWER', 'name' => 'Просмотр']]
        );

        $result = $this->makeService($db)->login('alice', 'secret');

        $this->assertNotNull($result);
        $this->assertSame('alice', $result['username']);
        $this->assertSame('local', $result['auth_source']);
        $this->assertContains('VIEWER', $result['role_codes']);
        $this->assertFalse($result['is_admin']);
    }

    public function testWrongPasswordReturnsNull(): void
    {
        $hash = password_hash('correct', PASSWORD_BCRYPT);

        $db = $this->makeDb(
            ['id' => 1, 'username' => 'alice', 'display_name' => 'Alice', 'email' => '', 'password_hash' => $hash, 'is_active' => 1]
        );

        $result = $this->makeService($db)->login('alice', 'wrong');
        $this->assertNull($result);
    }

    public function testInactiveUserReturnsNull(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);

        $db = $this->makeDb(
            ['id' => 1, 'username' => 'alice', 'display_name' => 'Alice', 'email' => '', 'password_hash' => $hash, 'is_active' => 0]
        );

        $result = $this->makeService($db)->login('alice', 'secret');
        $this->assertNull($result);
    }

    public function testUserNotFoundReturnsNull(): void
    {
        $db = $this->makeDb(null); // fetchOne вернёт null

        $result = $this->makeService($db)->login('nobody', 'pass');
        $this->assertNull($result);
    }

    public function testEmptyPasswordHashReturnsNull(): void
    {
        $db = $this->makeDb(
            ['id' => 1, 'username' => 'alice', 'display_name' => 'Alice', 'email' => '', 'password_hash' => '', 'is_active' => 1]
        );

        $result = $this->makeService($db)->login('alice', '');
        $this->assertNull($result);
    }

    public function testAdminRoleSetsIsAdminTrue(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT);

        $db = $this->makeDb(
            ['id' => 1, 'username' => 'admin', 'display_name' => 'Admin', 'email' => '', 'password_hash' => $hash, 'is_active' => 1],
            [['id' => 1, 'code' => 'ADMIN', 'name' => 'Администратор']]
        );

        $result = $this->makeService($db)->login('admin', 'secret');

        $this->assertNotNull($result);
        $this->assertTrue($result['is_admin']);
    }

    public function testMultipleRolesAllReturned(): void
    {
        $hash = password_hash('pass', PASSWORD_BCRYPT);

        $db = $this->makeDb(
            ['id' => 2, 'username' => 'user2', 'display_name' => 'User 2', 'email' => '', 'password_hash' => $hash, 'is_active' => 1],
            [
                ['id' => 2, 'code' => 'VIEWER', 'name' => 'Просмотр'],
                ['id' => 3, 'code' => 'IMPORT', 'name' => 'Импорт'],
            ]
        );

        $result = $this->makeService($db)->login('user2', 'pass');

        $this->assertNotNull($result);
        $this->assertCount(2, $result['role_codes']);
        $this->assertContains('VIEWER', $result['role_codes']);
        $this->assertContains('IMPORT', $result['role_codes']);
        $this->assertFalse($result['is_admin']);
    }

    public function testUserWithNoRolesIsNotAdmin(): void
    {
        $hash = password_hash('pass', PASSWORD_BCRYPT);

        $db = $this->makeDb(
            ['id' => 5, 'username' => 'noroles', 'display_name' => 'No Roles', 'email' => '', 'password_hash' => $hash, 'is_active' => 1],
            [] // нет ролей
        );

        $result = $this->makeService($db)->login('noroles', 'pass');

        $this->assertNotNull($result);
        $this->assertSame([], $result['role_codes']);
        $this->assertFalse($result['is_admin']);
    }
}
