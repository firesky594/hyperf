<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Service\AdminAuditService;
use App\Service\AdminAuthService;
use App\Service\AdminUserManagementService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class AdminUserManagementServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCreateAdministratorReturnsTemporaryPasswordAndAuditsInTransaction(): void
    {
        [$service, $db, $connection, $ids, $audit, $auth] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturnNull();
        $ids->shouldReceive('generate')->once()->andReturn(8001);
        $connection->shouldReceive('insert')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'admin_users')
                && $bindings === [8001, 'operator_1', 'hashed:Temporary-Password-2026!']
        )->andReturnTrue();
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'administrator.create'
                && $event['target_id'] === 8001
                && ! array_key_exists('temporary_password', $event['request_data']))
        );
        $auth->shouldNotReceive('revokeAdminSessions');

        self::assertSame([
            'id' => 8001,
            'username' => 'operator_1',
            'temporary_password' => 'Temporary-Password-2026!',
        ], $service->createAdministrator('operator_1', $this->auditContext()));
    }

    public function testDisableAdministratorAuditsThenRevokesSessions(): void
    {
        [$service, $db, $connection, , $audit, $auth] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) [
            'id' => 52, 'username' => 'operator_2', 'is_super_admin' => 0,
        ]);
        $connection->shouldReceive('update')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, '`status` = ?') && $bindings === [0, 52]
        )->andReturn(1);
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'administrator.status'
                && $event['request_data']['status'] === 0)
        );
        $auth->shouldReceive('revokeAdminSessions')->once()->with(52);

        $service->setStatus(52, false, $this->auditContext());
        self::addToAssertionCount(1);
    }

    public function testWelkinAndSuperAdministratorCannotBeDisabled(): void
    {
        [$service, $db, $connection, , $audit, $auth] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) [
            'id' => 1, 'username' => 'welkin', 'is_super_admin' => 1,
        ]);
        $connection->shouldNotReceive('update');
        $audit->shouldNotReceive('append');
        $auth->shouldNotReceive('revokeAdminSessions');

        $this->expectException(AdminAuthException::class);
        $service->setStatus(1, false, $this->auditContext());
    }

    public function testAssignRolesReplacesAssociationsAndAuditsInSameTransaction(): void
    {
        [$service, $db, $connection, $ids, $audit, $auth] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) [
            'id' => 52, 'username' => 'operator_2', 'is_super_admin' => 0,
        ]);
        $connection->shouldReceive('select')->once()->andReturn([(object) ['id' => 7], (object) ['id' => 8]]);
        $connection->shouldReceive('update')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'admin_user_roles') && $bindings === [52]
        )->andReturn(1);
        $ids->shouldReceive('generate')->twice()->andReturn(9001, 9002);
        $connection->shouldReceive('insert')->twice()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'admin_user_roles')
                && $bindings[1] === 52 && in_array($bindings[2], [7, 8], true)
        )->andReturnTrue();
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'administrator.roles'
                && $event['request_data']['role_ids'] === [7, 8])
        );
        $auth->shouldNotReceive('revokeAdminSessions');

        $service->assignRoles(52, [8, 7, 8], $this->auditContext());
        self::addToAssertionCount(1);
    }

    public function testResetPasswordReturnsTemporaryPasswordAuditsAndRevokesSessions(): void
    {
        [$service, $db, $connection, , $audit, $auth] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) [
            'id' => 52, 'username' => 'operator_2', 'is_super_admin' => 0,
        ]);
        $connection->shouldReceive('update')->once()->withArgs(
            fn (string $sql, array $bindings): bool => str_contains($sql, 'must_change_password')
                && $bindings === ['hashed:Temporary-Password-2026!', 52]
        )->andReturn(1);
        $audit->shouldReceive('append')->once()->with(
            $connection,
            Mockery::on(fn (array $event): bool => $event['action'] === 'administrator.password-reset'
                && $event['request_data'] === [])
        );
        $auth->shouldReceive('revokeAdminSessions')->once()->with(52);

        self::assertSame('Temporary-Password-2026!', $service->resetPassword(52, $this->auditContext()));
    }

    public function testListAdministratorsReturnsRolesWithoutPasswordMaterial(): void
    {
        [$service, $db] = $this->dependencies();
        $db->shouldReceive('select')->once()->withArgs(function (string $sql): bool {
            self::assertStringContainsString('admin_user_roles', $sql);
            self::assertStringContainsString('admin_roles', $sql);
            self::assertStringNotContainsString('password_hash', $sql);
            return true;
        })->andReturn([(object) [
            'id' => 52, 'username' => 'operator_2', 'status' => 1, 'is_super_admin' => 0,
            'must_change_password' => 1, 'role_ids' => '7,8', 'role_names' => '运营,审计',
            'created_at' => '2026-07-15 20:00:00', 'updated_at' => '2026-07-15 21:00:00',
        ]]);

        $rows = $service->listAdministrators();

        self::assertSame([7, 8], $rows[0]['role_ids']);
        self::assertSame(['运营', '审计'], $rows[0]['role_names']);
        self::assertArrayNotHasKey('password_hash', $rows[0]);
    }

    private function dependencies(): array
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $audit = Mockery::mock(AdminAuditService::class);
        $auth = Mockery::mock(AdminAuthService::class);
        $service = new AdminUserManagementService(
            $db,
            $ids,
            $audit,
            $auth,
            static fn (string $password): string => 'hashed:' . $password,
            static fn (): string => 'Temporary-Password-2026!'
        );

        return [$service, $db, $connection, $ids, $audit, $auth];
    }

    private function auditContext(): array
    {
        return [
            'request_id' => 'req-1', 'actor_admin_id' => 41, 'actor_username' => 'manager',
            'request_method' => 'POST', 'request_path' => '/agent_admin/administrators',
        ];
    }
}
