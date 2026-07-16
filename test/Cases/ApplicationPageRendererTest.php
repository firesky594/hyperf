<?php
declare(strict_types=1);
namespace HyperfTest\Cases;
use App\View\ApplicationPageRenderer;
use PHPUnit\Framework\TestCase;
final class ApplicationPageRendererTest extends TestCase
{
    private array $session=['username'=>'buyer'];
    public function testApplicationsShowsCreateAndHonestEmptyState():void{$html=(new ApplicationPageRenderer())->applications($this->session,[],'csrf');self::assertStringContainsString('应用与密钥',$html);self::assertStringContainsString('/workspace/buyer/apps/create',$html);self::assertStringContainsString('当前还没有应用',$html);}
    public function testOneTimeSecretIsClearlyMarked():void{$html=(new ApplicationPageRenderer())->secret($this->session,'app_live_secret','/workspace/buyer/apps');self::assertStringContainsString('仅显示一次',$html);self::assertStringContainsString('app_live_secret',$html);self::assertSame(1,substr_count($html,'<h1'));}
    public function testSubscriptionsAndQuotaPagesHaveRealEmptyStates():void{$r=new ApplicationPageRenderer();self::assertStringContainsString('当前没有订阅',$r->subscriptions($this->session,[],'csrf'));self::assertStringContainsString('当前没有可调整额度的采购方',$r->quotas($this->session,[],'csrf'));}
}
