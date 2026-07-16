<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AuthException;
use App\Service\CatalogService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class CatalogServiceTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }

    public function testCreatesProductAndInitialDraftVersionInOneTransaction(): void
    {
        [$service, $db, $connection, $ids] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturnNull(); $ids->shouldReceive('generate')->twice()->andReturn(10, 11);
        $connection->shouldReceive('insert')->twice()->withArgs(fn (string $sql): bool => str_contains($sql, 'api_products') || str_contains($sql, 'api_versions'))->andReturnTrue();
        self::assertSame(['product_id' => 10, 'version_id' => 11], $service->createProduct(3, '天气 API', 'weather', '天气查询'));
    }

    public function testPublishedVersionCannotBeEdited(): void
    {
        [$service, $db, $connection] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['id' => 11, 'status' => 'published']);
        $connection->shouldNotReceive('update'); $connection->shouldNotReceive('insert');
        $this->expectException(AuthException::class);
        $service->saveDraft(3, 10, 11, '天气 API', '天气查询', 'v1', '# 文档', 250000, 'CNY', [['method' => 'GET', 'path' => '/weather', 'name' => '查询天气', 'description' => '']]);
    }

    public function testPublishRequiresCompleteDraftAndAtomicallySetsCurrentVersion(): void
    {
        [$service, $db, $connection] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->times(3)->andReturn((object) ['id' => 11, 'api_product_id' => 10, 'status' => 'draft', 'name' => '天气 API', 'summary' => '天气查询'], (object) ['id' => 1], (object) ['id' => 1]);
        $connection->shouldReceive('update')->twice()->andReturn(1);
        $service->publish(3, 11); self::addToAssertionCount(1);
    }

    public function testMarketQueriesOnlyPublishedProductAndCurrentPublishedVersion(): void
    {
        [$service, $db] = $this->dependencies();
        $db->shouldReceive('select')->once()->withArgs(function (string $sql): bool { self::assertStringContainsString("p.`status` = 'published'", $sql); self::assertStringContainsString("v.`status` = 'published'", $sql); self::assertStringContainsString('current_published_version_id', $sql); return true; })->andReturn([]);
        self::assertSame([], $service->market());
    }

    public function testSavingPriceWritesPermanentAuditInSameTransaction(): void
    {
        [$service, $db, $connection, $ids] = $this->dependencies();
        $db->shouldReceive('transaction')->once()->andReturnUsing(fn (callable $callback) => $callback($connection));
        $connection->shouldReceive('selectOne')->once()->andReturn((object) ['id' => 11, 'status' => 'draft', 'unit_price_micros' => 100000]);
        $connection->shouldReceive('update')->twice()->andReturn(1);
        $ids->shouldReceive('generate')->times(4)->andReturn(21, 22, 23, 24);
        $auditSeen = false;
        $connection->shouldReceive('insert')->times(4)->withArgs(function (string $sql) use (&$auditSeen): bool { if (str_contains($sql, 'api_price_audit_logs')) { $auditSeen = true; } return true; })->andReturnTrue();
        $service->saveDraft(3,10,11,'天气 API','天气查询','v1','# 文档',250000,'CNY',[['method'=>'GET','path'=>'/weather','name'=>'查询天气','description'=>'']]);
        self::assertTrue($auditSeen);
    }

    private function dependencies(): array
    {
        $db = Mockery::mock(Db::class); $connection = Mockery::mock(ConnectionInterface::class); $ids = Mockery::mock(IdGeneratorInterface::class);
        return [new CatalogService($db, $ids), $db, $connection, $ids];
    }
}
