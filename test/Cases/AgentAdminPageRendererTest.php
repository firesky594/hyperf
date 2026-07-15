<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Cases;

use App\View\AgentAdminPageRenderer;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AgentAdminPageRendererTest extends TestCase
{
    public function testLoginEscapesDynamicValuesAndDefinesTheFormContract(): void
    {
        $html = (new AgentAdminPageRenderer())->login(
            'csrf-"token"',
            '<admin>',
            '错误 <script>alert("x")</script>'
        );

        self::assertStringContainsString('action="/agent_admin/login"', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('name="_csrf" value="csrf-&quot;token&quot;"', $html);
        self::assertStringContainsString('name="username"', $html);
        self::assertStringContainsString('autocomplete="username"', $html);
        self::assertStringContainsString('value="&lt;admin&gt;"', $html);
        self::assertStringContainsString('name="password"', $html);
        self::assertStringContainsString('autocomplete="current-password"', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('错误 &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testLoginIsAccessibleResponsiveAndSelfContained(): void
    {
        $html = (new AgentAdminPageRenderer())->login('csrf-token');

        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('href="#main-content"', $html);
        self::assertStringContainsString('<main id="main-content"', $html);
        self::assertStringContainsString('<label for="username"', $html);
        self::assertStringContainsString('<label for="password"', $html);
        self::assertStringContainsString(':focus-visible', $html);
        self::assertStringContainsString('min-height: 44px', $html);
        self::assertStringContainsString('@media (max-width:', $html);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
        self::assertStringContainsString('system-ui', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<link', $html);
        self::assertStringNotContainsString('@import', $html);
        self::assertStringNotContainsString('http://', $html);
        self::assertStringNotContainsString('https://', $html);
        self::assertStringNotContainsString('单一管理员', $html);
    }

    public function testLongLoginValuesCanWrapInsideNarrowPanel(): void
    {
        $username = str_repeat('u', 64);
        $error = str_repeat('E', 320);

        $html = (new AgentAdminPageRenderer())->login('csrf-token', $username, $error);

        self::assertStringContainsString('value="' . $username . '"', $html);
        self::assertStringContainsString($error, $html);
        $this->assertCssRuleAllowsUnbrokenValues($html, '\.alert');
    }

    public function testHomeRendersEscapedSessionOverviewInShanghaiTime(): void
    {
        $html = (new AgentAdminPageRenderer())->home([
            'admin_id' => '<1>',
            'username' => '<root>',
            'issued_at' => 1783918800,
            'expires_at' => 1783926000,
            'csrf_token' => 'logout-"token"',
        ]);

        self::assertStringContainsString('Agent Admin', $html);
        self::assertSame(1, substr_count($html, '>总览<'));
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('&lt;root&gt;', $html);
        self::assertStringContainsString('&lt;1&gt;', $html);
        self::assertStringContainsString('2026-07-13 13:00:00', $html);
        self::assertStringContainsString('2026-07-13 15:00:00', $html);
        self::assertStringContainsString('Asia/Shanghai', $html);
        self::assertStringContainsString('Hyperf 3.1', $html);
        self::assertStringContainsString('独立管理员门禁', $html);
        self::assertStringContainsString('统一权限层级', $html);
        self::assertStringContainsString('RBAC', $html);
        self::assertStringNotContainsString('单管理员', $html);
        self::assertStringNotContainsString('单一管理员', $html);
        self::assertStringContainsString('action="/agent_admin/logout"', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('name="_csrf" value="logout-&quot;token&quot;"', $html);
        self::assertStringNotContainsString('<root>', $html);
    }

    public function testHomePresentsUniApiMarketplaceControlPlaneWithoutInventingLiveMetrics(): void
    {
        $html = (new AgentAdminPageRenderer())->home([
            'admin_id' => '594',
            'username' => 'welkin',
            'issued_at' => 1783918800,
            'expires_at' => 1783926000,
            'csrf_token' => 'logout-token',
        ]);

        self::assertStringContainsString('<title>系统总览 · UniAPI</title>', $html);
        self::assertStringContainsString('统一接口平台', $html);
        self::assertStringContainsString('API 市场', $html);
        self::assertStringContainsString('采购方应用', $html);
        self::assertStringContainsString('供应商工作台', $html);
        self::assertStringContainsString('调用与计量', $html);
        self::assertStringContainsString('账单与结算', $html);
        self::assertStringContainsString('分布式节点', $html);
        self::assertStringContainsString('尚未接入实时数据', $html);
        self::assertStringContainsString('aria-label="平台功能导航"', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('@media (max-width: 640px)', $html);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $html);
        self::assertStringNotContainsString('1,280,391', $html);
        self::assertSame(1, substr_count($html, '<h1'));
    }

    public function testManagementPagesExposeRbacNavigationAndHonestEmptyStates(): void
    {
        $renderer = new AgentAdminPageRenderer();
        $session = [
            'admin_id' => '594',
            'username' => 'welkin',
            'issued_at' => 1783918800,
            'expires_at' => 1783926000,
            'csrf_token' => 'logout-token',
        ];
        $pages = [
            'administrators' => ['管理员管理', '/agent_admin/administrators'],
            'roles' => ['角色管理', '/agent_admin/roles'],
            'permissions' => ['权限管理', '/agent_admin/permissions'],
            'menus' => ['菜单管理', '/agent_admin/menus'],
            'audit' => ['操作日志', '/agent_admin/audit'],
        ];

        foreach ($pages as $key => [$heading, $path]) {
            $html = $renderer->management($key, $session);
            self::assertStringContainsString('<h1 id="management-heading">' . $heading . '</h1>', $html);
            self::assertStringContainsString('href="' . $path . '" aria-current="page"', $html);
            self::assertStringContainsString('尚未接入数据库数据', $html);
            self::assertStringContainsString('数据接入后将在此处显示', $html);
            self::assertStringContainsString('name="_csrf" value="logout-token"', $html);
            self::assertSame(1, substr_count($html, '<h1'));
        }

        $home = $renderer->home($session);
        foreach ($pages as [, $path]) {
            self::assertStringContainsString('href="' . $path . '"', $home);
        }
    }

    public function testUnavailablePageEscapesItsMessage(): void
    {
        $html = (new AgentAdminPageRenderer())->unavailable('稍后 <script>alert(1)</script>');

        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('>503<', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('稍后 &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testErrorPageReflectsFormTokenFailureInsteadOfClaimingServiceOutage(): void
    {
        $html = (new AgentAdminPageRenderer())->error(419, 'Invalid form token.');

        self::assertStringContainsString('>419<', $html);
        self::assertStringContainsString('请求验证失败', $html);
        self::assertStringContainsString('Invalid form token.', $html);
        self::assertStringNotContainsString('>503<', $html);
        self::assertStringNotContainsString('后台服务暂不可用', $html);
    }

    public function testLongOperatorValuesCanWrapInsideNarrowOverview(): void
    {
        $username = str_repeat('u', 64);
        $adminId = str_repeat('9', 128);

        $html = (new AgentAdminPageRenderer())->home([
            'admin_id' => $adminId,
            'username' => $username,
            'issued_at' => 1783918800,
            'expires_at' => 1783926000,
            'csrf_token' => 'logout-token',
        ]);

        self::assertStringContainsString('<strong>' . $username . '</strong>', $html);
        self::assertStringContainsString('<small>ID / ' . $adminId . '</small>', $html);
        $this->assertCssRuleAllowsUnbrokenValues($html, '\.operator-chip');
        $this->assertCssRuleAllowsUnbrokenValues(
            $html,
            '\.operator-chip span,\s*\.operator-chip strong,\s*\.operator-chip small'
        );
    }

    public function testDynamicValuesEscapeApostrophesAmpersandsAndMalformedUtf8(): void
    {
        $malformedValue = "value'&\xC3(";
        $escapedValue = 'value&#039;&amp;' . "\u{FFFD}(";
        $renderer = new AgentAdminPageRenderer();

        $login = $renderer->login($malformedValue, $malformedValue, $malformedValue);
        self::assertStringContainsString('name="_csrf" value="' . $escapedValue . '"', $login);
        self::assertStringContainsString('name="username" type="text" value="' . $escapedValue . '"', $login);
        self::assertStringContainsString('role="alert" aria-live="assertive">' . $escapedValue, $login);

        $home = $renderer->home([
            'admin_id' => $malformedValue,
            'username' => $malformedValue,
            'issued_at' => 1783918800,
            'expires_at' => 1783926000,
            'csrf_token' => $malformedValue,
        ]);
        self::assertStringContainsString('<strong>' . $escapedValue . '</strong>', $home);
        self::assertStringContainsString('<small>ID / ' . $escapedValue . '</small>', $home);
        self::assertStringContainsString('name="_csrf" value="' . $escapedValue . '"', $home);

        $unavailable = $renderer->unavailable($malformedValue);
        self::assertStringContainsString('role="alert">' . $escapedValue . '</p>', $unavailable);
    }

    private function assertCssRuleAllowsUnbrokenValues(string $html, string $selectorPattern): void
    {
        $pattern = '~' . $selectorPattern
            . '\s*\{(?=[^}]*min-width:\s*0\s*;)(?=[^}]*overflow-wrap:\s*anywhere\s*;)[^}]*\}~s';

        self::assertSame(1, preg_match($pattern, $html), 'Expected responsive wrapping rule for ' . $selectorPattern);
    }
}
