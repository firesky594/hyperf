<?php

declare(strict_types=1);

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

    public function testUnavailablePageEscapesItsMessage(): void
    {
        $html = (new AgentAdminPageRenderer())->unavailable('稍后 <script>alert(1)</script>');

        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('稍后 &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }
}
