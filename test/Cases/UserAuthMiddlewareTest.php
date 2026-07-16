<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Http\UserPortalResponseFactory;
use App\Middleware\UserAuthMiddleware;
use App\Service\AuthService;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\HttpMessage\Server\Request;

/** @internal @coversNothing */
final class UserAuthMiddlewareTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }

    public function testCookieSessionAddsUserAttribute(): void
    {
        $auth = Mockery::mock(AuthService::class); $responses = Mockery::mock(UserPortalResponseFactory::class); $handler = Mockery::mock(RequestHandlerInterface::class); $response = Mockery::mock(ResponseInterface::class);
        $auth->shouldReceive('resolveToken')->once()->with('portal-token')->andReturn(['user_id' => 7, 'username' => 'buyer_7']);
        $handler->shouldReceive('handle')->once()->withArgs(fn ($request): bool => $request->getAttribute('user_session')['user_id'] === 7)->andReturn($response);
        $request = (new Request('GET', '/workspace'))->withCookieParams(['uniapi_user_session' => 'portal-token']);
        self::assertSame($response, (new UserAuthMiddleware($auth, $responses))->process($request, $handler));
    }
}
