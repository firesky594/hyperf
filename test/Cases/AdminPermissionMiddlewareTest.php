<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Middleware\AdminPermissionMiddleware;
use App\Rbac\AdminRouteRegistry;
use App\Service\AdminAuthService;
use App\Service\AdminAuthorizationService;
use App\View\AgentAdminPageRenderer;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response as ServerResponse;
use Hyperf\HttpServer\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

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
        $auth = Mockery::mock(AdminAuthService::class);
        $auth->shouldNotReceive('revokeAdminSessions');
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($request)->andReturn($expected);

        self::assertSame($expected, $this->middleware($authorization, $auth)->process($request, $handler));
    }

    public function testDeniedRouteReturnsSecure403WithoutCallingHandler(): void
    {
        $session = ['admin_id' => 41, 'is_super_admin' => false];
        $request = (new Request('GET', '/agent_admin/audit'))->withAttribute('admin_session', $session);
        $authorization = Mockery::mock(AdminAuthorizationService::class);
        $authorization->shouldReceive('allows')->once()->with($session, 'audit.view')->andReturnFalse();
        $auth = Mockery::mock(AdminAuthService::class);
        $auth->shouldReceive('revokeAdminSessions')->once()->with(41);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->never();

        $response = $this->middleware($authorization, $auth)->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('权限不足', (string) $response->getBody());
        self::assertStringContainsString('audit.view', (string) $response->getBody());
        self::assertTrue($this->sessionCookie($response)->isCleared());
    }

    public function testProtectedRouteWithoutPermissionMetadataFailsClosed(): void
    {
        $authorization = Mockery::mock(AdminAuthorizationService::class);
        $authorization->shouldNotReceive('allows');
        $auth = Mockery::mock(AdminAuthService::class);
        $auth->shouldReceive('revokeAdminSessions')->once()->with(41);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->never();

        $response = $this->middleware($authorization, $auth)->process(
            (new Request('GET', '/agent_admin/unregistered'))->withAttribute('admin_session', ['admin_id' => 41]),
            $handler
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('路由未配置授权项', (string) $response->getBody());
        self::assertTrue($this->sessionCookie($response)->isCleared());
    }

    public function testRedisFailureReturns503AndClearsCurrentCookie(): void
    {
        $session = ['admin_id' => 41, 'is_super_admin' => false];
        $request = (new Request('GET', '/agent_admin/audit'))->withAttribute('admin_session', $session);
        $authorization = Mockery::mock(AdminAuthorizationService::class);
        $authorization->shouldReceive('allows')->once()->with($session, 'audit.view')->andReturnFalse();
        $previous = new RuntimeException('redis unavailable');
        $auth = Mockery::mock(AdminAuthService::class);
        $auth->shouldReceive('revokeAdminSessions')
            ->once()
            ->with(41)
            ->andThrow(AdminAuthException::unavailable('Session store unavailable.', $previous));
        $logger = Mockery::mock(StdoutLoggerInterface::class);
        $logger->shouldReceive('error')->once()->with(
            'agent_admin.permission_denied.session_revocation_failure exception_type={exception_type}',
            ['exception_type' => RuntimeException::class]
        );
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->never();

        $response = $this->middleware($authorization, $auth, $logger)->process($request, $handler);

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('Session store unavailable.', (string) $response->getBody());
        self::assertTrue($this->sessionCookie($response)->isCleared());
    }

    private function middleware(
        AdminAuthorizationService $authorization,
        ?AdminAuthService $auth = null,
        ?StdoutLoggerInterface $logger = null
    ): AdminPermissionMiddleware {
        return new AdminPermissionMiddleware(
            $authorization,
            new AdminRouteRegistry(),
            new AgentAdminPageRenderer(),
            new AgentAdminResponseFactory(new Response(new ServerResponse())),
            $auth ?? Mockery::mock(AdminAuthService::class)->shouldIgnoreMissing(),
            $logger ?? Mockery::mock(StdoutLoggerInterface::class)->shouldIgnoreMissing()
        );
    }

    private function sessionCookie(ResponseInterface $response): Cookie
    {
        self::assertInstanceOf(ServerResponse::class, $response);
        $cookies = $response->getCookies();
        self::assertArrayHasKey('', $cookies);
        self::assertArrayHasKey('/agent_admin', $cookies['']);
        self::assertArrayHasKey('agent_admin_session', $cookies['']['/agent_admin']);

        return $cookies['']['/agent_admin']['agent_admin_session'];
    }
}
