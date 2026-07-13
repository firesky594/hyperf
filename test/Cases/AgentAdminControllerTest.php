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
use App\Service\AdminAuthService;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpMessage\Server\Response as ServerResponse;
use Hyperf\Testing\Client;
use Hyperf\Testing\TestCase;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

/**
 * @internal
 * @coversNothing
 */
class AgentAdminControllerTest extends TestCase
{
    public function testLoginPageRendersHtmlAndSetsFreshCsrfCookie(): void
    {
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->never();

        $response = $this->request('GET', '/agent_admin/login');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('action="/agent_admin/login"', (string) $response->getBody());
        $csrf = $this->cookie($response, '/agent_admin/login', 'agent_admin_login_csrf');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $csrf->getValue());
        self::assertStringContainsString('name="_csrf" value="' . $csrf->getValue() . '"', (string) $response->getBody());
        self::assertTrue($csrf->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $csrf->getSameSite());
        $this->assertSecurityHeaders($response);
    }

    public function testLoginPageRedirectsExistingValidSession(): void
    {
        $token = str_repeat('a', 64);
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($this->session());

        $response = $this->request('GET', '/agent_admin/login', [], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/agent_admin', $response->getHeaderLine('Location'));
        self::assertSame([], $this->cookies($response));
        $this->assertSecurityHeaders($response);
    }

    public function testLoginPageAuthInfrastructureErrorReturnsSecured503(): void
    {
        $token = str_repeat('b', 64);
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')
            ->once()
            ->with($token)
            ->andThrow(AdminAuthException::unavailable('Administrator session unavailable.'));

        $response = $this->request('GET', '/agent_admin/login', [], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertStringContainsString('Administrator session unavailable.', (string) $response->getBody());
        self::assertSame([], $this->cookies($response));
        $this->assertSecurityHeaders($response);
    }

    public function testBadLoginCsrfReturns419WithoutAuthenticatingOrRetainingPassword(): void
    {
        $oldCsrf = str_repeat('c', 64);
        $password = 'do-not-repeat-this-password';
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('login')->never();
        $auth->shouldReceive('resolveSession')->never();

        $response = $this->request('POST', '/agent_admin/login', [
            '_csrf' => str_repeat('d', 64),
            'username' => 'route-admin',
            'password' => $password,
        ], [
            'agent_admin_login_csrf' => $oldCsrf,
        ]);

        self::assertSame(419, $response->getStatusCode());
        self::assertStringContainsString('Invalid form token.', (string) $response->getBody());
        self::assertStringContainsString('name="username" type="text" value="route-admin"', (string) $response->getBody());
        self::assertStringNotContainsString($password, (string) $response->getBody());
        $newCsrf = $this->cookie($response, '/agent_admin/login', 'agent_admin_login_csrf')->getValue();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $newCsrf);
        self::assertNotSame($oldCsrf, $newCsrf);
        self::assertStringContainsString('name="_csrf" value="' . $newCsrf . '"', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    public function testLoginAuthErrorRotatesCsrfAndPreservesOnlyUsername(): void
    {
        $oldCsrf = str_repeat('e', 64);
        $password = 'still-not-rendered';
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('login')
            ->once()
            ->with('route-admin', $password, '127.0.0.1')
            ->andThrow(AdminAuthException::invalidCredentials());

        $response = $this->request('POST', '/agent_admin/login', [
            '_csrf' => $oldCsrf,
            'username' => 'route-admin',
            'password' => $password,
        ], [
            'agent_admin_login_csrf' => $oldCsrf,
        ]);

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('Invalid username or password.', (string) $response->getBody());
        self::assertStringContainsString('name="username" type="text" value="route-admin"', (string) $response->getBody());
        self::assertStringNotContainsString($password, (string) $response->getBody());
        $newCsrf = $this->cookie($response, '/agent_admin/login', 'agent_admin_login_csrf')->getValue();
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $newCsrf);
        self::assertNotSame($oldCsrf, $newCsrf);
        $this->assertSecurityHeaders($response);
    }

    public function testValidLoginReturns303WithSessionCookie(): void
    {
        $csrf = str_repeat('f', 64);
        $session = $this->session();
        $token = str_repeat('1', 64);
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('login')
            ->once()
            ->with('route-admin', 'correct-password', '127.0.0.1')
            ->andReturn([
                'token' => $token,
                'expires_in' => 7200,
                'session' => $session,
            ]);

        $response = $this->request('POST', '/agent_admin/login', [
            '_csrf' => $csrf,
            'username' => 'route-admin',
            'password' => 'correct-password',
        ], [
            'agent_admin_login_csrf' => $csrf,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/agent_admin', $response->getHeaderLine('Location'));
        $cookie = $this->cookie($response, '/agent_admin', 'agent_admin_session');
        self::assertSame($token, $cookie->getValue());
        self::assertSame($session['expires_at'], $cookie->getExpiresTime());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        $this->assertSecurityHeaders($response);
    }

    public function testUnauthenticatedHomeRedirectsAndClearsSessionCookie(): void
    {
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->never();

        $response = $this->request('GET', '/agent_admin');

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
        self::assertTrue($this->cookie($response, '/agent_admin', 'agent_admin_session')->isCleared());
        $this->assertSecurityHeaders($response);
    }

    public function testValidSessionRendersProtectedHome(): void
    {
        $token = str_repeat('2', 64);
        $session = $this->session();
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);

        $response = $this->request('GET', '/agent_admin', [], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('运行控制台', (string) $response->getBody());
        self::assertStringContainsString($session['username'], (string) $response->getBody());
        self::assertStringContainsString('name="_csrf" value="' . $session['csrf_token'] . '"', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    public function testBadLogoutCsrfReturns419WithoutDeletingSession(): void
    {
        $token = str_repeat('3', 64);
        $session = $this->session();
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);
        $auth->shouldReceive('logout')->never();

        $response = $this->request('POST', '/agent_admin/logout', [
            '_csrf' => str_repeat('4', 64),
        ], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(419, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertStringContainsString('>419<', (string) $response->getBody());
        self::assertStringNotContainsString('>503<', (string) $response->getBody());
        self::assertStringContainsString('Invalid form token.', (string) $response->getBody());
        self::assertSame([], $this->cookies($response));
        $this->assertSecurityHeaders($response);
    }

    public function testLogoutDeletesExactMiddlewareTokenAndClearsCookieWith303(): void
    {
        $token = str_repeat('5', 64);
        $session = $this->session();
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);
        $auth->shouldReceive('logout')->once()->with($token)->andReturnNull();

        $response = $this->request('POST', '/agent_admin/logout', [
            '_csrf' => $session['csrf_token'],
        ], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
        self::assertTrue($this->cookie($response, '/agent_admin', 'agent_admin_session')->isCleared());
        $this->assertSecurityHeaders($response);
    }

    public function testLogoutInfrastructureErrorReturns503WithoutClearingCookie(): void
    {
        $token = str_repeat('6', 64);
        $session = $this->session();
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);
        $auth->shouldReceive('logout')
            ->once()
            ->with($token)
            ->andThrow(AdminAuthException::unavailable('Administrator logout unavailable.'));

        $response = $this->request('POST', '/agent_admin/logout', [
            '_csrf' => $session['csrf_token'],
        ], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertStringContainsString('Administrator logout unavailable.', (string) $response->getBody());
        self::assertSame([], $this->cookies($response));
        $this->assertSecurityHeaders($response);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,string> $cookies
     */
    private function request(string $method, string $path, array $form = [], array $cookies = []): ResponseInterface
    {
        $client = make(Client::class);
        $request = $client->initRequest($method, $path, ['form_params' => $form])
            ->withCookieParams($cookies);

        return $client->sendRequest($request);
    }

    /**
     * @return array{admin_id:int,username:string,issued_at:int,expires_at:int,csrf_token:string}
     */
    private function session(): array
    {
        $issuedAt = time();

        return [
            'admin_id' => 41,
            'username' => 'route-admin',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + 7200,
            'csrf_token' => str_repeat('7', 64),
        ];
    }

    private function cookie(ResponseInterface $response, string $path, string $name): Cookie
    {
        $cookies = $this->cookies($response);
        self::assertArrayHasKey('', $cookies);
        self::assertArrayHasKey($path, $cookies['']);
        self::assertArrayHasKey($name, $cookies[''][$path]);

        return $cookies[''][$path][$name];
    }

    /**
     * @return array<string,array<string,array<string,Cookie>>>
     */
    private function cookies(ResponseInterface $response): array
    {
        self::assertInstanceOf(ServerResponse::class, $response);

        return $response->getCookies();
    }

    private function assertSecurityHeaders(ResponseInterface $response): void
    {
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertNotSame('', $response->getHeaderLine('Content-Security-Policy'));
    }
}
