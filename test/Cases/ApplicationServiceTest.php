<?php
declare(strict_types=1);
namespace HyperfTest\Cases;
use App\Service\ApplicationService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;
final class ApplicationServiceTest extends TestCase
{
    protected function tearDown():void{Mockery::close();parent::tearDown();}
    public function testCreateReturnsSecretOnceAndStoresOnlyHash():void{$db=Mockery::mock(Db::class);$c=Mockery::mock(ConnectionInterface::class);$ids=Mockery::mock(IdGeneratorInterface::class);$db->shouldReceive('transaction')->once()->andReturnUsing(fn($f)=>$f($c));$ids->shouldReceive('generate')->twice()->andReturn(10,11);$seen='';$c->shouldReceive('insert')->twice()->withArgs(function(string $sql,array $args)use(&$seen):bool{if(str_contains($sql,'application_credentials')){$seen=(string)$args[2];self::assertStringNotContainsString('app_live_',$seen);}return true;})->andReturnTrue();$result=(new ApplicationService($db,$ids))->create(3,'天气应用');self::assertSame(10,$result['application_id']);self::assertStringStartsWith('app_live_',$result['secret']);self::assertNotSame('',$seen);}
    public function testQuotaUpdateWritesAuditInSameTransaction():void{$db=Mockery::mock(Db::class);$c=Mockery::mock(ConnectionInterface::class);$ids=Mockery::mock(IdGeneratorInterface::class);$db->shouldReceive('transaction')->once()->andReturnUsing(fn($f)=>$f($c));$c->shouldReceive('selectOne')->once()->andReturn((object)['id'=>8,'qps_limit'=>10,'period_limit'=>1000]);$c->shouldReceive('update')->once()->andReturn(1);$ids->shouldReceive('generate')->once()->andReturn(99);$c->shouldReceive('insert')->once()->withArgs(fn(string$sql)=>str_contains($sql,'quota_audit_logs'))->andReturnTrue();(new ApplicationService($db,$ids))->updateQuota(7,8,20,2000);self::addToAssertionCount(1);}
}
