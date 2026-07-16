<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\AdminAuditService;
use App\Service\AdminRoleManagementService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class AdminRoleManagementServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testListRolesReturnsPermissionCollections(): void
    {
        [$service, $db] = $this->dependencies();
        $db->shouldReceive('select')->once()->withArgs(function (string $sql): bool {
            self::assertStringContainsString('admin_role_permissions', $sql);
            self::assertStringContainsString('admin_permissions', $sql);
            self::assertStringContainsString('ar.`deleted_at` IS NULL', $sql);
            return true;
        })->andReturn([(object) [
            'id' => 21, 'name' => '运营', 'code' => 'operator', 'description' => '运营角色', 'status' => 1,
            'permission_ids' => '31,32', 'permission_codes' => 'roles.view,menus.view',
            'created_at' => '2026-07-16 10:00:00', 'updated_at' => '2026-07-16 10:00:00',
        ]]);

        $rows = $service->listRoles();

        self::assertSame([31, 32], $rows[0]['permission_ids']);
        self::assertSame(['roles.view', 'menus.view'], $rows[0]['permission_codes']);
    }

    public function testCreateRoleWritesRoleAndAuditInSameTransaction(): void
    {
        [$service, $db, $connection, $ids, $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturnNull();
        $ids->shouldReceive('generate')->once()->andReturn(21);
        $connection->shouldReceive('insert')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'admin_roles')
                && $bindings === [21, '运营', 'operator', '负责日常运营']
        )->andReturnTrue();
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'role.create'
                && $event['target_id'] === 21
                && $event['request_data']['code'] === 'operator')
        );

        self::assertSame(21, $service->createRole('运营', 'operator', '负责日常运营', $this->auditContext()));
    }

    public function testUpdateRoleLocksTargetChecksCodeAndAudits(): void
    {
        [$service, $db, $connection, , $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->twice()->andReturn((object) ['id' => 21], null);
        $connection->shouldReceive('update')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'UPDATE `admin_roles`')
                && $bindings === ['高级运营', 'senior_operator', '扩展职责', 21]
        )->andReturn(1);
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'role.update'
                && $event['target_id'] === 21)
        );

        $service->updateRole(21, '高级运营', 'senior_operator', '扩展职责', $this->auditContext());
        self::addToAssertionCount(1);
    }

    public function testDisableRoleAuditsInTransaction(): void
    {
        [$service, $db, $connection, , $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['id' => 21]);
        $connection->shouldReceive('update')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, '`status` = ?')
                && $bindings === [0, 21]
        )->andReturn(1);
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'role.status'
                && $event['request_data']['status'] === 0)
        );

        $service->setStatus(21, false, $this->auditContext());
        self::addToAssertionCount(1);
    }

    public function testAssignPermissionsReplacesAssociationsAndAuditsInTransaction(): void
    {
        [$service, $db, $connection, $ids, $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['id' => 21]);
        $connection->shouldReceive('select')->once()->andReturn([(object) ['id' => 31], (object) ['id' => 32]]);
        $connection->shouldReceive('update')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'admin_role_permissions')
                && $bindings === [21]
        )->andReturn(2);
        $ids->shouldReceive('generate')->twice()->andReturn(9001, 9002);
        $connection->shouldReceive('insert')->twice()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'admin_role_permissions')
                && $bindings[1] === 21 && in_array($bindings[2], [31, 32], true)
        )->andReturnTrue();
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'role.permissions'
                && $event['request_data']['permission_ids'] === [31, 32])
        );

        $service->assignPermissions(21, [32, 31, 32], $this->auditContext());
        self::addToAssertionCount(1);
    }

    private function dependencies(): array
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $audit = Mockery::mock(AdminAuditService::class);
        $service = new AdminRoleManagementService($db, $ids, $audit);

        return [$service, $db, $connection, $ids, $audit];
    }

    /** @return array<string,mixed> */
    private function auditContext(): array
    {
        return [
            'request_id' => 'req-role-1', 'actor_admin_id' => 41, 'actor_username' => 'manager',
            'request_method' => 'POST', 'request_path' => '/agent_admin/roles',
        ];
    }
}
