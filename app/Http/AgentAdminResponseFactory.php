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

namespace App\Http;

use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpServer\Contract\ResponseInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

use function Hyperf\Support\env;

class AgentAdminResponseFactory
{
    private const LOGIN_CSRF_COOKIE = 'agent_admin_login_csrf';

    private const LOGIN_CSRF_PATH = '/agent_admin/login';

    private const DEFAULT_LOGIN_CSRF_TTL = 600;

    private const SESSION_COOKIE = 'agent_admin_session';

    private const SESSION_PATH = '/agent_admin';

    private const CONTENT_SECURITY_POLICY = "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";

    private const SECURITY_HEADERS = [
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
    ];

    public function __construct(private ResponseInterface $response)
    {
    }

    public function loginPage(string $html, string $csrfToken, int $status = 200): PsrResponseInterface
    {
        $cookie = $this->cookie(
            self::LOGIN_CSRF_COOKIE,
            $csrfToken,
            time() + $this->loginCsrfTtl(),
            self::LOGIN_CSRF_PATH
        );

        return $this->secure(
            $this->response
                ->withCookie($cookie)
                ->html($html)
                ->withStatus($status)
        );
    }

    public function html(string $html, int $status = 200): PsrResponseInterface
    {
        return $this->secure($this->response->html($html)->withStatus($status));
    }

    public function redirect(string $path, int $status = 302): PsrResponseInterface
    {
        $this->assertRelativePath($path);

        return $this->secure(
            $this->response
                ->raw('')
                ->withStatus($status)
                ->withHeader('Location', $path)
        );
    }

    public function redirectWithSession(string $path, string $token, int $expiresAt): PsrResponseInterface
    {
        $this->assertRelativePath($path);
        $cookie = $this->cookie(self::SESSION_COOKIE, $token, $expiresAt, self::SESSION_PATH);

        return $this->secure(
            $this->response
                ->withCookie($cookie)
                ->raw('')
                ->withStatus(303)
                ->withHeader('Location', $path)
        );
    }

    public function redirectClearingSession(string $path, int $status = 302): PsrResponseInterface
    {
        $this->assertRelativePath($path);
        $cookie = $this->cookie(self::SESSION_COOKIE, '', 1, self::SESSION_PATH);

        return $this->secure(
            $this->response
                ->withCookie($cookie)
                ->raw('')
                ->withStatus($status)
                ->withHeader('Location', $path)
        );
    }

    private function cookie(string $name, string $value, int $expiresAt, string $path): Cookie
    {
        return new Cookie(
            name: $name,
            value: $value,
            expire: $expiresAt,
            path: $path,
            secure: $this->cookieSecure(),
            httpOnly: true,
            sameSite: Cookie::SAMESITE_STRICT
        );
    }

    private function cookieSecure(): bool
    {
        return filter_var(env('ADMIN_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function loginCsrfTtl(): int
    {
        return max(1, (int) env('ADMIN_LOGIN_CSRF_TTL', self::DEFAULT_LOGIN_CSRF_TTL));
    }

    private function assertRelativePath(string $path): void
    {
        if (
            $path === ''
            || $path[0] !== '/'
            || str_starts_with($path, '//')
            || str_starts_with($path, '/\\')
            || preg_match('/[\x00-\x20\x7f]/', $path) === 1
        ) {
            throw new InvalidArgumentException('Agent administrator redirects must use a valid relative path.');
        }
    }

    private function secure(PsrResponseInterface $response): PsrResponseInterface
    {
        foreach (self::SECURITY_HEADERS as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
