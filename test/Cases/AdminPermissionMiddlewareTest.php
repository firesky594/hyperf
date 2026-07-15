<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Http\AgentAdminResponseFactory;
use App\Middleware\AdminPermissionMiddleware;
use App\Rbac\AdminRouteRegistry;
use App\Service\AdminAuthorizationService;
use App\View\AgentAdminPageRenderer;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response as ServerResponse;
use Hyperf\HttpServer\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

/** @internal @coversNothing */
final class AdminPermissionMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAllowedRouteCallsNextHandlerWithRoutePermission(): void
    {
        $session = ['admin_id' => 41, 'is_super_admin' => false];
        $request = (new Request('GET', '/agent_admin/roles'))->withAttribute('admin_session', $session);
        $expected = new ServerResponse();
        $authorization = Mockery::mock(AdminAuthorizationService::class);
        $authorization->shouldReceive('allows')->once()->with($session, 'roles.view')->andReturnTrue();
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($request)->andReturn($expected);

        self::assertSame($expected, $this->middleware($authorization)->process($request, $handler));
    }

    public function testDeniedRouteReturnsSecure403WithoutCallingHandler(): void
    {
        $session = ['admin_id' => 41, 'is_super_admin' => false];
        $request = (new Request('GET', '/agent_admin/audit'))->withAttribute('admin_session', $session);
        $authorization = Mockery::mock(AdminAuthorizationService::class);
        $authorization->shouldReceive('allows')->once()->with($session, 'audit.view')->andReturnFalse();
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->never();

        $response = $this->middleware($authorization)->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('权限不足', (string) $response->getBody());
        self::assertStringContainsString('audit.view', (string) $response->getBody());
    }

    public function testProtectedRouteWithoutPermissionMetadataFailsClosed(): void
    {
        $authorization = Mockery::mock(AdminAuthorizationService::class);
        $authorization->shouldNotReceive('allows');
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->never();

        $response = $this->middleware($authorization)->process(
            (new Request('GET', '/agent_admin/unregistered'))->withAttribute('admin_session', ['admin_id' => 41]),
            $handler
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('路由未配置授权项', (string) $response->getBody());
    }

    private function middleware(AdminAuthorizationService $authorization): AdminPermissionMiddleware
    {
        return new AdminPermissionMiddleware(
            $authorization,
            new AdminRouteRegistry(),
            new AgentAdminPageRenderer(),
            new AgentAdminResponseFactory(new Response(new ServerResponse()))
        );
    }
}
