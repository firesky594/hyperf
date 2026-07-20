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

/** 统一生成后台 HTML、跳转、会话 Cookie 和安全响应头。 */
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

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param ResponseInterface $response 待处理的 HTTP 响应。
     * @return void 无返回值。
     */
    public function __construct(private ResponseInterface $response)
    {
    }

    /**
     * 渲染登录页面。
     *
     * @param string $html 待写入响应的 HTML 内容。
     * @param string $csrfToken 用于防止跨站请求伪造的令牌。
     * @param int $status 目标业务状态。
     * @return PsrResponseInterface 当前请求对应的 HTTP 响应。
     */
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

    /**
     * 生成带安全响应头的 HTML 响应。
     *
     * @param string $html 待写入响应的 HTML 内容。
     * @param int $status 目标业务状态。
     * @return PsrResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function html(string $html, int $status = 200): PsrResponseInterface
    {
        return $this->secure($this->response->html($html)->withStatus($status));
    }

    /**
     * 生成清除后台会话 Cookie 的安全 HTML 响应。
     *
     * @param string $html 待写入响应的 HTML 内容。
     * @param int $status 目标业务状态。
     * @return PsrResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function htmlClearingSession(string $html, int $status = 200): PsrResponseInterface
    {
        $cookie = $this->cookie(self::SESSION_COOKIE, '', 1, self::SESSION_PATH);

        return $this->secure(
            $this->response
                ->withCookie($cookie)
                ->html($html)
                ->withStatus($status)
        );
    }

    /**
     * 生成安全的 HTTP 跳转响应。
     *
     * @param string $path 请求路径。
     * @param int $status 目标业务状态。
     * @return PsrResponseInterface 当前请求对应的 HTTP 响应。
     */
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

    /**
     * 处理跳转响应With会话。
     *
     * @param string $path 请求路径。
     * @param string $token 待校验或撤销的访问令牌。
     * @param int $expiresAt Cookie 的过期时间戳。
     * @return PsrResponseInterface 当前请求对应的 HTTP 响应。
     */
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

    /**
     * 处理跳转响应Clearing会话。
     *
     * @param string $path 请求路径。
     * @param int $status 目标业务状态。
     * @return PsrResponseInterface 当前请求对应的 HTTP 响应。
     */
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

    /**
     * 生成受保护的会话 Cookie。
     *
     * @param string $name 业务对象名称。
     * @param string $value 待写入或校验的值。
     * @param int $expiresAt Cookie 的过期时间戳。
     * @param string $path 请求路径。
     * @return Cookie 返回Cookie处理结果。
     */
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

    /**
     * 处理CookieSecure。
     *
     * @return bool 条件满足时返回 true，否则返回 false。
     */
    private function cookieSecure(): bool
    {
        return filter_var(env('ADMIN_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * 处理登录CSRFTtl。
     *
     * @return int 返回登录CSRFTtl整数结果。
     */
    private function loginCsrfTtl(): int
    {
        return max(1, (int) env('ADMIN_LOGIN_CSRF_TTL', self::DEFAULT_LOGIN_CSRF_TTL));
    }

    /**
     * 处理assertRelativePath。
     *
     * @param string $path 请求路径。
     * @return void 无返回值。
     * @throws \InvalidArgumentException 传入参数不符合约束时抛出。
     */
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

    /**
     * 处理secure。
     *
     * @param PsrResponseInterface $response 待处理的 HTTP 响应。
     * @return PsrResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function secure(PsrResponseInterface $response): PsrResponseInterface
    {
        foreach (self::SECURITY_HEADERS as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
