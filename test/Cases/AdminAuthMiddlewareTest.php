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

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Middleware\AdminAuthMiddleware;
use App\Service\AdminAuthService;
use App\View\AgentAdminPageRenderer;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response as ServerResponse;
use Hyperf\HttpServer\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
class AdminAuthMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testMissingSessionRedirectsAndDoesNotCallHandler(): void
    {
        $auth = Mockery::mock(AdminAuthService::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $auth->shouldReceive('resolveSession')->never();
        $handler->shouldReceive('handle')->never();

        $response = $this->middleware($auth)->process(
            new Request('GET', '/agent_admin'),
            $handler
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('', $this->sessionCookie($response)->getValue());
    }

    public function testValidSessionAddsRequestAttributesAndCallsHandler(): void
    {
        $token = str_repeat('a', 64);
        $session = $this->session();
        $request = (new Request('GET', '/agent_admin'))
            ->withCookieParams(['agent_admin_session' => $token]);
        $expectedResponse = new ServerResponse();
        $auth = Mockery::mock(AdminAuthService::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);

        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);
        $handler->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(static function (ServerRequestInterface $handledRequest) use ($session, $token): bool {
                return $handledRequest->getAttribute('admin_session') === $session
                    && $handledRequest->getAttribute('admin_session_token') === $token;
            }))
            ->andReturn($expectedResponse);

        $response = $this->middleware($auth)->process($request, $handler);

        self::assertSame($expectedResponse, $response);
    }

    public function testInvalidSessionRedirectsAndDoesNotCallHandler(): void
    {
        $token = str_repeat('b', 64);
        $request = (new Request('GET', '/agent_admin'))
            ->withCookieParams(['agent_admin_session' => $token]);
        $auth = Mockery::mock(AdminAuthService::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);

        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturnNull();
        $handler->shouldReceive('handle')->never();

        $response = $this->middleware($auth)->process($request, $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
        self::assertSame('', $this->sessionCookie($response)->getValue());
    }

    public function testRedisFailureReturns503Page(): void
    {
        $token = str_repeat('c', 64);
        $previous = new RuntimeException('session redis unavailable');
        $request = (new Request('GET', '/agent_admin'))
            ->withCookieParams(['agent_admin_session' => $token]);
        $auth = Mockery::mock(AdminAuthService::class);
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $logger = Mockery::mock(StdoutLoggerInterface::class);

        $auth->shouldReceive('resolveSession')
            ->once()
            ->with($token)
            ->andThrow(AdminAuthException::unavailable('Session store unavailable.', $previous));
        $handler->shouldReceive('handle')->never();
        $logger->shouldReceive('error')->once()->with(
            'agent_admin.session.infrastructure_failure',
            Mockery::on(static fn (array $context): bool => $context === [
                'exception_type' => RuntimeException::class,
                'exception' => $previous,
            ])
        );

        $response = $this->middleware($auth, $logger)->process($request, $handler);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringContainsString('>503<', (string) $response->getBody());
        self::assertStringContainsString('Session store unavailable.', (string) $response->getBody());
    }

    private function middleware(
        AdminAuthService $auth,
        ?StdoutLoggerInterface $logger = null
    ): AdminAuthMiddleware {
        return new AdminAuthMiddleware(
            $auth,
            new AgentAdminPageRenderer(),
            new AgentAdminResponseFactory(new Response(new ServerResponse())),
            $logger ?? Mockery::mock(StdoutLoggerInterface::class)->shouldIgnoreMissing()
        );
    }

    /**
     * @return array{admin_id:int,username:string,issued_at:int,expires_at:int,csrf_token:string}
     */
    private function session(): array
    {
        return [
            'admin_id' => 7,
            'username' => 'agent-admin',
            'issued_at' => 1_700_000_000,
            'expires_at' => 1_700_007_200,
            'csrf_token' => str_repeat('d', 64),
        ];
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
