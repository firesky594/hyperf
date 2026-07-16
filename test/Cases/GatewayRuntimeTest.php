<?php
declare(strict_types=1);
namespace HyperfTest\Cases;
use App\Service\AppRoleService;use App\Service\GatewaySignatureService;use App\Service\QuotaLimiter;use App\View\GatewayPageRenderer;use Hyperf\Redis\Redis;use Mockery;use PHPUnit\Framework\TestCase;
final class GatewayRuntimeTest extends TestCase{
 protected function tearDown():void{Mockery::close();parent::tearDown();}
 public function testStatusPagesExposeHonestEmptyStates():void{$r=new GatewayPageRenderer();$s=['username'=>'u'];foreach([[$r->routes($s,[]),'当前没有网关路由'],[$r->calls($s,[]),'当前没有调用日志'],[$r->usage($s,[]),'当前没有用量数据'],[$r->nodes($s,['role'=>'gateway']),'gateway']]as[$html,$text])self::assertStringContainsString($text,$html);}
 public function testRoleBoundariesFailClosed():void{$roles=new AppRoleService('gateway');self::assertTrue($roles->allows('gateway'));self::assertFalse($roles->allows('billing'));$this->expectException(\RuntimeException::class);$roles->require('control-plane');}
 public function testSignatureRejectsReplayAndAcceptsCanonicalRequest():void{$redis=Mockery::mock(Redis::class);$redis->shouldReceive('set')->once()->withArgs(fn(string$k,string$v,array$o)=>str_contains($k,'{sub:8}')&&$o['nx']===true)->andReturnTrue();$service=new GatewaySignatureService($redis,300);$ts=time();$body='{}';$sig=hash_hmac('sha256',"POST\n/v1/weather\n{$ts}\nnonce-1\n".hash('sha256',$body),'secret');self::assertTrue($service->verify(8,'secret','POST','/v1/weather',$ts,'nonce-1',$body,$sig));}
 public function testQuotaUsesOneClusterSlotAndLuaWithoutKeyScan():void{$redis=Mockery::mock(Redis::class);$redis->shouldReceive('eval')->once()->withArgs(function(string$sql,array$args,int$keys):bool{self::assertSame(2,$keys);self::assertStringNotContainsString("redis.call('KEYS'",$sql);self::assertStringNotContainsString("redis.call('SCAN'",$sql);self::assertStringContainsString('{sub:9}',$args[0]);self::assertStringContainsString('{sub:9}',$args[1]);return true;})->andReturn([1,4,99]);self::assertTrue((new QuotaLimiter($redis))->consume(9,5,100,60)['allowed']);}
}
