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
use App\Service\AdminPasswordService;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Framework\Logger\StdoutLogger;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpMessage\Server\Response as ServerResponse;
use Hyperf\Testing\Client;
use Hyperf\Testing\TestCase;
use Mockery;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

use function Hyperf\Support\make;

/**
 * @internal
 * @coversNothing
 */
class AgentAdminControllerTest extends TestCase
{
    public function testPasswordPageRendersSessionBoundForm(): void
    {
        $token = str_repeat('6', 64);
        $session = $this->session(true);
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);

        $response = $this->request('GET', '/agent_admin/password', [], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('action="/agent_admin/password"', (string) $response->getBody());
        self::assertStringContainsString('name="_csrf" value="' . $session['csrf_token'] . '"', (string) $response->getBody());
        self::assertStringContainsString('首次登录', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    public function testPasswordChangeClearsSessionAndRedirectsToLogin(): void
    {
        $token = str_repeat('5', 64);
        $session = $this->session(true);
        $auth = $this->mock(AdminAuthService::class);
        $passwords = $this->mock(AdminPasswordService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);
        $passwords->shouldReceive('changePassword')->once()->with(
            $session['admin_id'],
            'temporary-password',
            'new-strong-password'
        );

        $response = $this->request('POST', '/agent_admin/password', [
            '_csrf' => $session['csrf_token'],
            'current_password' => 'temporary-password',
            'new_password' => 'new-strong-password',
            'new_password_confirmation' => 'new-strong-password',
        ], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
        self::assertSame('', $this->cookie($response, '/agent_admin', 'agent_admin_session')->getValue());
    }

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

    public function testLoginPageTreatsNonStringSessionCookieAsMissing(): void
    {
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->never();

        $response = $this->request('GET', '/agent_admin/login', [], [
            'agent_admin_session' => ['bad'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('action="/agent_admin/login"', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    public function testLoginPageAuthInfrastructureErrorReturnsSecured503(): void
    {
        $token = str_repeat('b', 64);
        $previous = new RuntimeException('session redis unavailable');
        $auth = $this->mock(AdminAuthService::class);
        $logger = $this->spy(StdoutLoggerInterface::class);
        $auth->shouldReceive('resolveSession')
            ->once()
            ->with($token)
            ->andThrow(AdminAuthException::unavailable('Administrator session unavailable.', $previous));
        $response = $this->request('GET', '/agent_admin/login', [], [
            'agent_admin_session' => $token,
        ]);

        $this->assertInfrastructureLogged($logger, 'agent_admin.login_page.infrastructure_failure');

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertStringContainsString('Administrator session unavailable.', (string) $response->getBody());
        self::assertSame([], $this->cookies($response));
        $this->assertSecurityHeaders($response);
    }

    public function testRuntimeStdoutLogContainsSafeInfrastructureCauseType(): void
    {
        $token = str_repeat('9', 64);
        $previous = new RuntimeException('redis failed password=must-not-be-logged');
        $auth = $this->mock(AdminAuthService::class);
        $output = new BufferedOutput();
        $config = $this->getContainer()->get(ConfigInterface::class);
        $this->instance(StdoutLoggerInterface::class, new StdoutLogger($config, $output));
        $auth->shouldReceive('resolveSession')
            ->once()
            ->with($token)
            ->andThrow(AdminAuthException::unavailable('Administrator session unavailable.', $previous));

        $response = $this->request('GET', '/agent_admin/login', [], [
            'agent_admin_session' => $token,
        ]);

        $logged = $output->fetch();
        self::assertStringContainsString(
            '[ERROR] agent_admin.login_page.infrastructure_failure exception_type=RuntimeException',
            $logged
        );
        self::assertStringNotContainsString('must-not-be-logged', $logged);
        self::assertSame(503, $response->getStatusCode());
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

    /**
     * @dataProvider missingLoginFieldProvider
     * @param array<string,string> $form
     */
    public function testMissingLoginFieldsReturn422WithoutCallingAuthentication(array $form): void
    {
        $csrf = str_repeat('8', 64);
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('login')->never();

        $response = $this->request('POST', '/agent_admin/login', [
            '_csrf' => $csrf,
            ...$form,
        ], [
            'agent_admin_login_csrf' => $csrf,
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('Invalid administrator input.', (string) $response->getBody());
        self::assertStringNotContainsString('missing-field-secret', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    /**
     * @return iterable<string,array{array<string,string>}>
     */
    public static function missingLoginFieldProvider(): iterable
    {
        yield 'missing username' => [[
            'password' => 'missing-field-secret',
        ]];
        yield 'missing password' => [[
            'username' => 'route-admin',
        ]];
    }

    /**
     * @dataProvider nonStringLoginFieldProvider
     * @param array<string,mixed> $overrides
     */
    public function testNonStringLoginFieldsAreRejectedWithoutCallingAuthentication(
        array $overrides,
        int $status,
        string $message
    ): void {
        $csrf = str_repeat('8', 64);
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('login')->never();

        $response = $this->request('POST', '/agent_admin/login', [
            '_csrf' => $csrf,
            'username' => 'route-admin',
            'password' => 'array-field-secret',
            ...$overrides,
        ], [
            'agent_admin_login_csrf' => $csrf,
        ]);

        self::assertSame($status, $response->getStatusCode());
        self::assertStringContainsString($message, (string) $response->getBody());
        self::assertStringNotContainsString('array-field-secret', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    /**
     * @return iterable<string,array{array<string,mixed>,int,string}>
     */
    public static function nonStringLoginFieldProvider(): iterable
    {
        yield 'array csrf' => [[
            '_csrf' => ['bad'],
        ], 419, 'Invalid form token.'];
        yield 'array username' => [[
            'username' => ['bad'],
        ], 422, 'Invalid administrator input.'];
        yield 'array password' => [[
            'password' => ['bad'],
        ], 422, 'Invalid administrator input.'];
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
        self::assertStringContainsString('统一接口平台', (string) $response->getBody());
        self::assertStringContainsString($session['username'], (string) $response->getBody());
        self::assertStringContainsString('name="_csrf" value="' . $session['csrf_token'] . '"', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    public function testValidSessionCanOpenEveryRbacManagementPage(): void
    {
        $token = str_repeat('8', 64);
        $session = $this->session();
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->times(5)->with($token)->andReturn($session);
        $pages = [
            '/agent_admin/administrators' => '管理员管理',
            '/agent_admin/roles' => '角色管理',
            '/agent_admin/permissions' => '权限管理',
            '/agent_admin/menus' => '菜单管理',
            '/agent_admin/audit' => '操作日志',
        ];

        foreach ($pages as $path => $heading) {
            $response = $this->request('GET', $path, [], ['agent_admin_session' => $token]);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString($heading, (string) $response->getBody());
            self::assertStringContainsString('尚未接入数据库数据', (string) $response->getBody());
            $this->assertSecurityHeaders($response);
        }
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

    public function testNonStringLogoutCsrfReturns419WithoutDeletingSession(): void
    {
        $token = str_repeat('3', 64);
        $session = $this->session();
        $auth = $this->mock(AdminAuthService::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);
        $auth->shouldReceive('logout')->never();

        $response = $this->request('POST', '/agent_admin/logout', [
            '_csrf' => ['bad'],
        ], [
            'agent_admin_session' => $token,
        ]);

        self::assertSame(419, $response->getStatusCode());
        self::assertStringContainsString('Invalid form token.', (string) $response->getBody());
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
        $previous = new RuntimeException('logout redis unavailable');
        $auth = $this->mock(AdminAuthService::class);
        $logger = $this->spy(StdoutLoggerInterface::class);
        $auth->shouldReceive('resolveSession')->once()->with($token)->andReturn($session);
        $auth->shouldReceive('logout')
            ->once()
            ->with($token)
            ->andThrow(AdminAuthException::unavailable('Administrator logout unavailable.', $previous));
        $response = $this->request('POST', '/agent_admin/logout', [
            '_csrf' => $session['csrf_token'],
        ], [
            'agent_admin_session' => $token,
        ]);

        $this->assertInfrastructureLogged($logger, 'agent_admin.logout.infrastructure_failure');

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertStringContainsString('Administrator logout unavailable.', (string) $response->getBody());
        self::assertSame([], $this->cookies($response));
        $this->assertSecurityHeaders($response);
    }

    /**
     * @param array<string,mixed> $form
     * @param array<string,mixed> $cookies
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
    private function session(bool $mustChangePassword = false): array
    {
        $issuedAt = time();

        return [
            'admin_id' => 41,
            'username' => 'route-admin',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + 7200,
            'csrf_token' => str_repeat('7', 64),
            'is_super_admin' => true,
            'must_change_password' => $mustChangePassword,
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

    private function assertInfrastructureLogged(
        StdoutLoggerInterface $logger,
        string $event
    ): void {
        $logger->shouldHaveReceived('error')->once()->with(
            $event . ' exception_type={exception_type}',
            Mockery::on(static fn (array $context): bool => $context === [
                'exception_type' => RuntimeException::class,
            ])
        );
    }
}
