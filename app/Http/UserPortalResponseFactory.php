<?php

declare(strict_types=1);

namespace App\Http;

use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Psr\Http\Message\ResponseInterface as PsrResponse;

use function Hyperf\Support\env;

/** 统一生成用户门户 HTML、跳转、会话 Cookie 和安全响应头。 */
class UserPortalResponseFactory
{
    public function __construct(private ResponseInterface $response) {}
    public function html(string $html, int $status = 200): PsrResponse { return $this->secure($this->response->html($html)->withStatus($status)); }
    public function loginPage(string $html, string $csrf, int $status = 200): PsrResponse { return $this->secure($this->response->withCookie(new Cookie('uniapi_portal_csrf', $csrf, time() + 600, '/portal/login', '', $this->cookieSecure(), true, false, Cookie::SAMESITE_STRICT))->html($html)->withStatus($status)); }
    public function redirect(string $path, int $status = 303): PsrResponse { return $this->secure($this->response->raw('')->withStatus($status)->withHeader('Location', $path)); }
    public function login(string $path, string $token, int $ttl): PsrResponse { return $this->secure($this->response->withCookie(new Cookie('uniapi_user_session', $token, time() + $ttl, '/', '', $this->cookieSecure(), true, false, Cookie::SAMESITE_STRICT))->raw('')->withStatus(303)->withHeader('Location', $path)); }
    public function clear(string $path): PsrResponse { return $this->secure($this->response->withCookie(new Cookie('uniapi_user_session', '', 1, '/', '', $this->cookieSecure(), true, false, Cookie::SAMESITE_STRICT))->raw('')->withStatus(303)->withHeader('Location', $path)); }
    private function secure(PsrResponse $response): PsrResponse { foreach (['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"] as $name => $value) { $response = $response->withHeader($name, $value); } return $response; }
    private function cookieSecure(): bool { return filter_var(env('USER_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN); }
}
