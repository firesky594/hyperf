<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\View\UserPortalPageRenderer;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class UserPortalPageRendererTest extends TestCase
{
    public function testWorkspaceShowsIdentitySwitchAndHonestSupplierState(): void
    {
        $html = (new UserPortalPageRenderer())->workspace(['user_id' => 7, 'username' => 'buyer_7'], ['buyer' => ['id' => 11, 'display_name' => '采购账户', 'status' => 1], 'supplier' => null], 'csrf-token');
        self::assertStringContainsString('采购方工作台', $html); self::assertStringContainsString('供应商工作台', $html);
        self::assertStringContainsString('/workspace/buyer', $html); self::assertStringContainsString('/workspace/supplier', $html);
        self::assertStringContainsString('尚未申请供应商身份', $html); self::assertStringContainsString('name="_csrf" value="csrf-token"', $html);
        self::assertSame(1, substr_count($html, '<h1'));
    }

    public function testLoginPageContainsAccessibleCredentialForm(): void
    {
        $html = (new UserPortalPageRenderer())->login('csrf-token');
        self::assertStringContainsString('action="/portal/login"', $html); self::assertStringContainsString('autocomplete="username"', $html);
        self::assertStringContainsString('autocomplete="current-password"', $html); self::assertSame(1, substr_count($html, '<h1'));
    }
}
