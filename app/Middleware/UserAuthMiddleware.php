<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\AuthException;
use App\Http\UserPortalResponseFactory;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** 解析用户 Bearer Token 或会话 Cookie，并向后续请求注入登录态。 */
class UserAuthMiddleware implements MiddlewareInterface
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AuthService $auth 注入的 AuthService 依赖。
     * @param UserPortalResponseFactory $responses 注入的 UserPortalResponseFactory 依赖。
     * @return void 无返回值。
     */
    public function __construct(private AuthService $auth, private UserPortalResponseFactory $responses) {}
    /**
     * 处理监听到的事件。
     *
     * @param ServerRequestInterface $request 当前 HTTP 请求。
     * @param RequestHandlerInterface $handler 后续请求处理器。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getCookieParams()['uniapi_user_session'] ?? '';
        if (! is_string($token) || trim($token) === '') { $header = $request->getHeaderLine('Authorization'); $token = preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1 ? trim($matches[1]) : ''; }
        try { $session = $this->auth->resolveToken($token); } catch (AuthException) { return $this->responses->html('认证服务暂不可用', 503); }
        if ($session === null) { return $this->responses->clear('/portal/login'); }
        return $handler->handle($request->withAttribute('user_session', $session)->withAttribute('user_session_token', $token));
    }
}
