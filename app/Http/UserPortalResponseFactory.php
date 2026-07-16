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
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param ResponseInterface $response 待处理的 HTTP 响应。
     * @return void 无返回值。
     */
    public function __construct(private ResponseInterface $response) {}
    /**
     * 生成带安全响应头的 HTML 响应。
     *
     * @param string $html 待写入响应的 HTML 内容。
     * @param int $status 目标业务状态。
     * @return PsrResponse 返回HTML 内容处理结果。
     */
    public function html(string $html, int $status = 200): PsrResponse { return $this->secure($this->response->html($html)->withStatus($status)); }
    /**
     * 渲染登录页面。
     *
     * @param string $html 待写入响应的 HTML 内容。
     * @param string $csrf 用于防止跨站请求伪造的令牌。
     * @param int $status 目标业务状态。
     * @return PsrResponse 返回登录页面处理结果。
     */
    public function loginPage(string $html, string $csrf, int $status = 200): PsrResponse { return $this->secure($this->response->withCookie(new Cookie('uniapi_portal_csrf', $csrf, time() + 600, '/portal/login', '', $this->cookieSecure(), true, false, Cookie::SAMESITE_STRICT))->html($html)->withStatus($status)); }
    /**
     * 生成安全的 HTTP 跳转响应。
     *
     * @param string $path 请求路径。
     * @param int $status 目标业务状态。
     * @return PsrResponse 返回跳转响应处理结果。
     */
    public function redirect(string $path, int $status = 303): PsrResponse { return $this->secure($this->response->raw('')->withStatus($status)->withHeader('Location', $path)); }
    /**
     * 校验凭据并建立登录会话。
     *
     * @param string $path 请求路径。
     * @param string $token 待校验或撤销的访问令牌。
     * @param int $ttl 数据或锁的有效秒数。
     * @return PsrResponse 返回登录处理结果。
     */
    public function login(string $path, string $token, int $ttl): PsrResponse { return $this->secure($this->response->withCookie(new Cookie('uniapi_user_session', $token, time() + $ttl, '/', '', $this->cookieSecure(), true, false, Cookie::SAMESITE_STRICT))->raw('')->withStatus(303)->withHeader('Location', $path)); }
    /**
     * 处理clear。
     *
     * @param string $path 请求路径。
     * @return PsrResponse 返回clear处理结果。
     */
    public function clear(string $path): PsrResponse { return $this->secure($this->response->withCookie(new Cookie('uniapi_user_session', '', 1, '/', '', $this->cookieSecure(), true, false, Cookie::SAMESITE_STRICT))->raw('')->withStatus(303)->withHeader('Location', $path)); }
    /**
     * 处理secure。
     *
     * @param PsrResponse $response 待处理的 HTTP 响应。
     * @return PsrResponse 返回secure处理结果。
     */
    private function secure(PsrResponse $response): PsrResponse { foreach (['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer', 'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'"] as $name => $value) { $response = $response->withHeader($name, $value); } return $response; }
    /**
     * 处理CookieSecure。
     *
     * @return bool 条件满足时返回 true，否则返回 false。
     */
    private function cookieSecure(): bool { return filter_var(env('USER_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN); }
}
