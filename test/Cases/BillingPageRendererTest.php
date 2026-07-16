<?php
declare(strict_types=1);namespace HyperfTest\Cases;use App\View\BillingPageRenderer;use PHPUnit\Framework\TestCase;
final class BillingPageRendererTest extends TestCase{public function testAllBillingSurfacesHaveHonestEmptyStates():void{$r=new BillingPageRenderer();$s=['username'=>'u'];foreach([[$r->buyerInvoices($s,[],'c'),'当前没有账单'],[$r->paymentProof($s,1,'c'),'付款凭证'],[$r->adminConfirmations([], 'c'),'当前没有待确认付款'],[$r->supplierSettlements($s,[]),'当前没有结算单']]as[$h,$t])self::assertStringContainsString($t,$h);}}
