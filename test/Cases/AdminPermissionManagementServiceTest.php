<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Service\AdminAuditService;
use App\Service\AdminPermissionManagementService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class AdminPermissionManagementServiceTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }

    public function testListsPermissionsWithoutDeletedRows(): void
    {
        [$service, $db] = $this->dependencies();
        $db->shouldReceive('select')->once()->withArgs(fn (string $sql): bool => str_contains($sql, '`deleted_at` IS NULL'))
            ->andReturn([(object) ['id' => 1, 'name' => '查看报表', 'code' => 'reports.view', 'source' => 'custom']]);
        self::assertSame('reports.view', $service->listPermissions()[0]['code']);
    }

    public function testCreatesCustomPermissionAndAuditInTransaction(): void
    {
        [$service, $db, $connection, $ids, $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturnNull();
        $ids->shouldReceive('generate')->once()->andReturn(71);
        $connection->shouldReceive('insert')->once()->withArgs(fn (string $sql, array $bindings): bool => str_contains($sql, "'custom'") && $bindings === [71, '查看报表', 'reports.view', '查看业务报表'])->andReturnTrue();
        $audit->shouldReceive('append')->once()->with($connection, Mockery::on(fn (array $event): bool => $event['action'] === 'permission.create' && $event['target_id'] === 71));
        self::assertSame(71, $service->createCustom('查看报表', 'reports.view', '查看业务报表', $this->context()));
    }

    public function testSystemPermissionCannotBeEditedOrDisabled(): void
    {
        [$service, $db, $connection, , $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['id' => 1, 'source' => 'system']);
        $audit->shouldNotReceive('append');
        $this->expectException(AdminAuthException::class);
        $service->setStatus(1, false, $this->context());
    }

    public function testUpdatesCustomPermissionAndAudits(): void
    {
        [$service, $db, $connection, , $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->twice()->andReturn((object) ['id' => 71, 'source' => 'custom'], null);
        $connection->shouldReceive('update')->once()->andReturn(1);
        $audit->shouldReceive('append')->once()->with($connection, Mockery::on(fn (array $event): bool => $event['action'] === 'permission.update'));
        $service->updateCustom(71, '导出报表', 'reports.export', '允许导出', $this->context());
        self::addToAssertionCount(1);
    }

    private function dependencies(): array
    {
        $db = Mockery::mock(Db::class); $connection = Mockery::mock(ConnectionInterface::class);
        $ids = Mockery::mock(IdGeneratorInterface::class); $audit = Mockery::mock(AdminAuditService::class);
        return [new AdminPermissionManagementService($db, $ids, $audit), $db, $connection, $ids, $audit];
    }

    /** @return array<string,mixed> */
    private function context(): array { return ['actor_admin_id' => 41, 'actor_username' => 'manager', 'request_method' => 'POST', 'request_path' => '/agent_admin/permissions']; }
}
