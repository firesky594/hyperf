<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Http\AgentAdminResponseFactory;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpMessage\Server\Response as ServerResponse;
use Hyperf\HttpServer\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * @internal
 * @coversNothing
 */
class AgentAdminResponseFactoryTest extends TestCase
{
    private const CONTENT_SECURITY_POLICY = "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";

    /** @var false|string */
    private $originalCookieSecure;

    /** @var false|string */
    private $originalLoginCsrfTtl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCookieSecure = getenv('ADMIN_COOKIE_SECURE');
        $this->originalLoginCsrfTtl = getenv('ADMIN_LOGIN_CSRF_TTL');
    }

    protected function tearDown(): void
    {
        if ($this->originalCookieSecure === false) {
            putenv('ADMIN_COOKIE_SECURE');
        } else {
            putenv('ADMIN_COOKIE_SECURE=' . $this->originalCookieSecure);
        }

        if ($this->originalLoginCsrfTtl === false) {
            putenv('ADMIN_LOGIN_CSRF_TTL');
        } else {
            putenv('ADMIN_LOGIN_CSRF_TTL=' . $this->originalLoginCsrfTtl);
        }

        parent::tearDown();
    }

    public function testLoginPageSetsShortLivedStrictCsrfCookieAndSecurityHeaders(): void
    {
        putenv('ADMIN_COOKIE_SECURE=false');
        putenv('ADMIN_LOGIN_CSRF_TTL=900');
        $before = time();

        $response = $this->factory()->loginPage('<p>login</p>', 'csrf-token', 422);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('<p>login</p>', (string) $response->getBody());
        $this->assertSecurityHeaders($response);

        $cookie = $this->cookie($response, '/agent_admin/login', 'agent_admin_login_csrf');
        self::assertSame('csrf-token', $cookie->getValue());
        self::assertSame('/agent_admin/login', $cookie->getPath());
        self::assertGreaterThanOrEqual($before + 900, $cookie->getExpiresTime());
        self::assertLessThanOrEqual(time() + 900, $cookie->getExpiresTime());
        self::assertTrue($cookie->isHttpOnly());
        self::assertFalse($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function testHtmlResponseUsesRequestedStatusAndSecurityHeaders(): void
    {
        $response = $this->factory()->html('<h1>Unavailable</h1>', 503);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('<h1>Unavailable</h1>', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    public function testLoginCsrfCookieUsesSixHundredSecondDefaultTtl(): void
    {
        putenv('ADMIN_LOGIN_CSRF_TTL');
        $before = time();

        $response = $this->factory()->loginPage('<p>login</p>', 'csrf-token');

        $cookie = $this->cookie($response, '/agent_admin/login', 'agent_admin_login_csrf');
        self::assertGreaterThanOrEqual($before + 600, $cookie->getExpiresTime());
        self::assertLessThanOrEqual(time() + 600, $cookie->getExpiresTime());
    }

    public function testRedirectWithSessionIsRelativeSecureAndStrict(): void
    {
        putenv('ADMIN_COOKIE_SECURE=true');
        $expiresAt = time() + 7200;

        $response = $this->factory()->redirectWithSession('/agent_admin', 'session-token', $expiresAt);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/agent_admin', $response->getHeaderLine('Location'));
        self::assertSame('', (string) $response->getBody());
        $this->assertSecurityHeaders($response);

        $cookie = $this->cookie($response, '/agent_admin', 'agent_admin_session');
        self::assertSame('session-token', $cookie->getValue());
        self::assertSame($expiresAt, $cookie->getExpiresTime());
        self::assertSame('/agent_admin', $cookie->getPath());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function testRedirectClearingSessionExpiresAnEmptyCookie(): void
    {
        putenv('ADMIN_COOKIE_SECURE=true');

        $response = $this->factory()->redirectClearingSession('/agent_admin/login', 303);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
        $this->assertSecurityHeaders($response);

        $cookie = $this->cookie($response, '/agent_admin', 'agent_admin_session');
        self::assertSame('', $cookie->getValue());
        self::assertTrue($cookie->isCleared());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function testPlainRedirectUsesAnEmptyRawResponse(): void
    {
        $response = $this->factory()->redirect('/agent_admin/login', 307);

        self::assertSame(307, $response->getStatusCode());
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
        self::assertSame('', (string) $response->getBody());
        $this->assertSecurityHeaders($response);
    }

    public function testRedirectRejectsExternalOrMalformedLocations(): void
    {
        $factory = $this->factory();

        foreach (['https://example.test', '//example.test', 'agent_admin', "/agent_admin\r\nX-Test: injected"] as $path) {
            try {
                $factory->redirect($path);
                self::fail('Expected an invalid relative redirect path to be rejected: ' . $path);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testFactoryResponsesRemainIsolatedWhenInstanceIsReused(): void
    {
        $factory = $this->factory();

        $session = $factory->redirectWithSession('/agent_admin', 'session-token', time() + 7200);
        self::assertSame(303, $session->getStatusCode());
        self::assertSame('/agent_admin', $session->getHeaderLine('Location'));
        self::assertSame('', (string) $session->getBody());
        $this->cookie($session, '/agent_admin', 'agent_admin_session');

        $html = $factory->html('<p>fresh html</p>');
        self::assertSame(200, $html->getStatusCode());
        self::assertSame('', $html->getHeaderLine('Location'));
        self::assertSame('text/html; charset=utf-8', $html->getHeaderLine('Content-Type'));
        self::assertSame('<p>fresh html</p>', (string) $html->getBody());
        $this->assertNoCookies($html);
        $this->assertSecurityHeaders($html);

        $login = $factory->loginPage('<p>fresh login</p>', 'fresh-csrf', 201);
        self::assertSame(201, $login->getStatusCode());
        self::assertSame('', $login->getHeaderLine('Location'));
        self::assertSame('<p>fresh login</p>', (string) $login->getBody());
        $this->cookie($login, '/agent_admin/login', 'agent_admin_login_csrf');
        self::assertInstanceOf(ServerResponse::class, $login);
        self::assertArrayNotHasKey('/agent_admin', $login->getCookies()[''] ?? []);
        $this->assertSecurityHeaders($login);

        $redirect = $factory->redirect('/agent_admin/login', 307);
        self::assertSame(307, $redirect->getStatusCode());
        self::assertSame('/agent_admin/login', $redirect->getHeaderLine('Location'));
        self::assertSame('text/plain; charset=utf-8', $redirect->getHeaderLine('Content-Type'));
        self::assertSame('', (string) $redirect->getBody());
        $this->assertNoCookies($redirect);
        $this->assertSecurityHeaders($redirect);
    }

    private function factory(): AgentAdminResponseFactory
    {
        return new AgentAdminResponseFactory(new Response(new ServerResponse()));
    }

    private function assertSecurityHeaders(PsrResponseInterface $response): void
    {
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        self::assertSame(self::CONTENT_SECURITY_POLICY, $response->getHeaderLine('Content-Security-Policy'));
    }

    private function cookie(PsrResponseInterface $response, string $path, string $name): Cookie
    {
        self::assertInstanceOf(ServerResponse::class, $response);
        $cookies = $response->getCookies();
        self::assertArrayHasKey('', $cookies);
        self::assertArrayHasKey($path, $cookies['']);
        self::assertArrayHasKey($name, $cookies[''][$path]);

        return $cookies[''][$path][$name];
    }

    private function assertNoCookies(PsrResponseInterface $response): void
    {
        self::assertInstanceOf(ServerResponse::class, $response);
        self::assertSame([], $response->getCookies());
    }
}
