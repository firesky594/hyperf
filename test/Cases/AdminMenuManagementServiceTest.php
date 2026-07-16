<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\AdminAuditService;
use App\Service\AdminMenuManagementService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class AdminMenuManagementServiceTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }

    public function testListsMenuTreeData(): void
    {
        [$service, $db] = $this->dependencies();
        $db->shouldReceive('select')->once()->andReturn([(object) ['id' => 1, 'parent_id' => null, 'name' => '控制台', 'permission_code' => null]]);
        self::assertSame('控制台', $service->listMenus()[0]['name']);
    }

    public function testCreatesMenuWithValidParentAndPermissionInTransaction(): void
    {
        [$service, $db, $connection, $ids, $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->twice()->andReturn((object) ['id' => 2], (object) ['id' => 31]);
        $ids->shouldReceive('generate')->once()->andReturn(81);
        $connection->shouldReceive('insert')->once()->andReturnTrue();
        $audit->shouldReceive('append')->once()->with($connection, Mockery::on(fn (array $event): bool => $event['action'] === 'menu.create'));
        self::assertSame(81, $service->createMenu(2, '角色', 'shield', 20, '/agent_admin/roles', 31, $this->context()));
    }

    public function testUpdatesAndDisablesMenuWithAudit(): void
    {
        [$service, $db, $connection, , $audit] = $this->dependencies();
        $db->shouldReceive('transaction')->twice()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->twice()->andReturn((object) ['id' => 81], (object) ['id' => 81]);
        $connection->shouldReceive('update')->twice()->andReturn(1);
        $audit->shouldReceive('append')->twice();
        $service->updateMenu(81, null, '角色管理', 'shield', 21, '/agent_admin/roles', null, $this->context());
        $service->setStatus(81, false, $this->context());
        self::addToAssertionCount(2);
    }

    private function dependencies(): array
    {
        $db = Mockery::mock(Db::class); $connection = Mockery::mock(ConnectionInterface::class);
        $ids = Mockery::mock(IdGeneratorInterface::class); $audit = Mockery::mock(AdminAuditService::class);
        return [new AdminMenuManagementService($db, $ids, $audit), $db, $connection, $ids, $audit];
    }

    /** @return array<string,mixed> */
    private function context(): array { return ['actor_admin_id' => 41, 'actor_username' => 'manager', 'request_method' => 'POST', 'request_path' => '/agent_admin/menus']; }
}
