<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Controller\AbstractController;
use App\Controller\AuthController;
use App\Exception\AuthException;
use App\Service\AuthService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use ReflectionProperty;

/**
 * @internal
 * @coversNothing
 */
class AuthControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLoginReturnsAuthServicePayload(): void
    {
        $payload = [
            'token' => 'token-123',
            'token_type' => 'Bearer',
            'expires_in' => 7200,
            'user' => ['id' => 1, 'username' => 'demo'],
        ];
        $service = Mockery::mock(AuthService::class);
        $request = Mockery::mock(RequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $request->shouldReceive('input')->once()->with('username', '')->andReturn('demo');
        $request->shouldReceive('input')->once()->with('password', '')->andReturn('secret');
        $service->shouldReceive('login')->once()->with('demo', 'secret')->andReturn($payload);
        $response->shouldReceive('json')->once()->with($payload)->andReturn($psrResponse);

        $result = $this->controller($service, $request, $response)->login();

        self::assertSame($psrResponse, $result);
    }

    public function testLogoutUsesBearerTokenBeforeInputToken(): void
    {
        $service = Mockery::mock(AuthService::class);
        $request = Mockery::mock(RequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $request->shouldReceive('header')->once()->with('authorization', '')->andReturn('Bearer token-123');
        $request->shouldReceive('input')->never();
        $service->shouldReceive('logout')->once()->with('token-123')->andReturn(['ok' => true]);
        $response->shouldReceive('json')->once()->with(['ok' => true])->andReturn($psrResponse);

        $result = $this->controller($service, $request, $response)->logout();

        self::assertSame($psrResponse, $result);
    }

    public function testAuthExceptionBecomesJsonErrorResponse(): void
    {
        $service = Mockery::mock(AuthService::class);
        $request = Mockery::mock(RequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $request->shouldReceive('input')->once()->with('username', '')->andReturn('demo');
        $request->shouldReceive('input')->once()->with('password', '')->andReturn('wrong');
        $service->shouldReceive('login')->once()->with('demo', 'wrong')->andThrow(AuthException::invalidCredentials());
        $response->shouldReceive('json')
            ->once()
            ->with([
                'error' => [
                    'message' => 'Invalid username or password.',
                    'status' => 401,
                ],
            ])
            ->andReturn($psrResponse);
        $psrResponse->shouldReceive('withStatus')->once()->with(401)->andReturn($psrResponse);

        $result = $this->controller($service, $request, $response)->login();

        self::assertSame($psrResponse, $result);
    }

    public function testRegisterRandomReturnsSingleUserWithCreatedStatus(): void
    {
        $payload = [
            'status' => 'registered',
            'user' => ['id' => 1, 'username' => 'test_1_abcd', 'password' => 'secret-1'],
        ];
        $service = Mockery::mock(AuthService::class);
        $request = Mockery::mock(RequestInterface::class);
        $response = Mockery::mock(ResponseInterface::class);
        $psrResponse = Mockery::mock(PsrResponseInterface::class);

        $service->shouldReceive('registerRandom')->once()->withNoArgs()->andReturn($payload);
        $response->shouldReceive('json')->once()->with($payload)->andReturn($psrResponse);
        $psrResponse->shouldReceive('withStatus')->once()->with(201)->andReturn($psrResponse);

        $result = $this->controller($service, $request, $response)->registerRandom();

        self::assertSame($psrResponse, $result);
    }

    private function controller(AuthService $service, RequestInterface $request, ResponseInterface $response): AuthController
    {
        $controller = new AuthController($service);
        $requestProperty = new ReflectionProperty(AbstractController::class, 'request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($controller, $request);

        $responseProperty = new ReflectionProperty(AbstractController::class, 'response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($controller, $response);

        return $controller;
    }
}
