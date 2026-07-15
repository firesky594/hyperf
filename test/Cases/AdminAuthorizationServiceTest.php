<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\AdminAuthorizationService;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class AdminAuthorizationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSuperAdministratorBypassesRoleQueries(): void
    {
        $db = Mockery::mock(Db::class);
        $db->shouldNotReceive('selectOne');

        self::assertTrue((new AdminAuthorizationService($db))->allows([
            'admin_id' => 594,
            'is_super_admin' => true,
        ], 'permissions.sync'));
    }

    public function testNormalAdministratorUsesEnabledRolePermissionUnionOnEveryCheck(): void
    {
        $db = Mockery::mock(Db::class);
        $queries = [];
        $db->shouldReceive('selectOne')->twice()->withArgs(
            function (string $sql, array $bindings) use (&$queries): bool {
                $queries[] = [$sql, $bindings];
                return true;
            }
        )->andReturn((object) ['allowed' => 1], null);
        $service = new AdminAuthorizationService($db);
        $session = ['admin_id' => 41, 'is_super_admin' => false];

        self::assertTrue($service->allows($session, 'roles.view'));
        self::assertFalse($service->allows($session, 'audit.view'));
        self::assertSame([41, 'roles.view'], $queries[0][1]);
        $sql = $queries[0][0];
        foreach (['admin_user_roles', 'admin_roles', 'admin_role_permissions', 'admin_permissions'] as $table) {
            self::assertStringContainsString($table, $sql);
        }
        self::assertGreaterThanOrEqual(3, substr_count($sql, '`status` = 1'));
        self::assertGreaterThanOrEqual(4, substr_count($sql, '`deleted_at` IS NULL'));
    }

    public function testMissingAdministratorIdentityIsDenied(): void
    {
        $db = Mockery::mock(Db::class);
        $db->shouldNotReceive('selectOne');

        self::assertFalse((new AdminAuthorizationService($db))->allows([], 'dashboard.view'));
    }
}
