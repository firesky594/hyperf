<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Http\AgentAdminResponseFactory;
use App\Middleware\AdminPasswordChangeMiddleware;
use Hyperf\HttpMessage\Server\Request;
use Hyperf\HttpMessage\Server\Response as ServerResponse;
use Hyperf\HttpServer\Response;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @internal
 * @coversNothing
 */
class AdminPasswordChangeMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testForcedPasswordChangeRedirectsOtherAdminPages(): void
    {
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->never();
        $request = (new Request('GET', '/agent_admin'))
            ->withAttribute('admin_session', ['must_change_password' => true]);

        $response = $this->middleware()->process($request, $handler);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/agent_admin/password', $response->getHeaderLine('Location'));
    }

    /**
     * @dataProvider allowedRequestProvider
     */
    public function testForcedPasswordChangeAllowsPasswordAndLogoutRoutes(string $method, string $path): void
    {
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $expected = new ServerResponse();
        $handler->shouldReceive('handle')->once()->andReturn($expected);
        $request = (new Request($method, $path))
            ->withAttribute('admin_session', ['must_change_password' => true]);

        self::assertSame($expected, $this->middleware()->process($request, $handler));
    }

    public static function allowedRequestProvider(): iterable
    {
        yield ['GET', '/agent_admin/password'];
        yield ['POST', '/agent_admin/password'];
        yield ['POST', '/agent_admin/logout'];
    }

    public function testCompletedPasswordChangeAllowsRequest(): void
    {
        $handler = Mockery::mock(RequestHandlerInterface::class);
        $expected = new ServerResponse();
        $handler->shouldReceive('handle')->once()->andReturn($expected);
        $request = (new Request('GET', '/agent_admin'))
            ->withAttribute('admin_session', ['must_change_password' => false]);

        self::assertSame($expected, $this->middleware()->process($request, $handler));
    }

    private function middleware(): AdminPasswordChangeMiddleware
    {
        return new AdminPasswordChangeMiddleware(
            new AgentAdminResponseFactory(new Response(new ServerResponse()))
        );
    }
}
