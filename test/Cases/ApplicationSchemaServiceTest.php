<?php
declare(strict_types=1);
namespace HyperfTest\Cases;
use App\Service\ApplicationSchemaService;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;
final class ApplicationSchemaServiceTest extends TestCase
{
    protected function tearDown():void{Mockery::close();parent::tearDown();}
    public function testCreatesApplicationSubscriptionQuotaAndAuditTables():void{$db=Mockery::mock(Db::class);$sql=[];$db->shouldReceive('statement')->times(5)->withArgs(function(string $s)use(&$sql):bool{$sql[]=$s;return true;})->andReturnTrue();(new ApplicationSchemaService($db))->ensureSchema();$all=implode("\n",$sql);foreach(['buyer_applications','application_credentials','api_subscriptions','subscription_quotas','quota_audit_logs']as$t){self::assertStringContainsString("CREATE TABLE IF NOT EXISTS `{$t}`",$all);}foreach($sql as$s){foreach(['created_at','updated_at','deleted_at']as$f)self::assertStringContainsString("`{$f}`",$s);}self::assertStringContainsString('secret_hash',$all);self::assertStringNotContainsString('secret_plaintext',$all);}
}
