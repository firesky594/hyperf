<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\UserIdentityService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class UserIdentityServiceTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }

    public function testWorkspaceKeepsBuyerAndSupplierIdentitiesIndependent(): void
    {
        $db = Mockery::mock(Db::class); $ids = Mockery::mock(IdGeneratorInterface::class);
        $db->shouldReceive('selectOne')->twice()->andReturn((object) ['id' => 11, 'display_name' => '采购账户', 'status' => 1], (object) ['id' => 12, 'company_name' => '接口公司', 'status' => 'approved']);
        $workspace = (new UserIdentityService($db, $ids))->workspace(7);
        self::assertSame(11, $workspace['buyer']['id']); self::assertSame(12, $workspace['supplier']['id']);
    }

    public function testApplySupplierWritesOnlySupplierProfileInTransaction(): void
    {
        $db = Mockery::mock(Db::class); $connection = Mockery::mock(ConnectionInterface::class); $ids = Mockery::mock(IdGeneratorInterface::class);
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturnNull(); $ids->shouldReceive('generate')->once()->andReturn(12);
        $connection->shouldReceive('insert')->once()->withArgs(fn (string $sql, array $bindings): bool => str_contains($sql, 'supplier_profiles') && ! str_contains($sql, 'buyer_profiles') && $bindings === [12, 7, '接口公司', '张三', 'contact@example.com'])->andReturnTrue();
        self::assertSame(12, (new UserIdentityService($db, $ids))->applySupplier(7, '接口公司', '张三', 'contact@example.com'));
    }
}
