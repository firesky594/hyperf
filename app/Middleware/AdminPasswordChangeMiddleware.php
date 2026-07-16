<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\AgentAdminResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** 强制使用临时密码登录的管理员先完成改密，再访问后台功能。 */
class AdminPasswordChangeMiddleware implements MiddlewareInterface
{
    private const ALLOWED_PATHS = [
        '/agent_admin/password',
        '/agent_admin/logout',
    ];

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
     * @return void 无返回值。
     */
    public function __construct(private AgentAdminResponseFactory $responses)
    {
    }

    /**
     * 处理监听到的事件。
     *
     * @param ServerRequestInterface $request 当前 HTTP 请求。
     * @param RequestHandlerInterface $handler 后续请求处理器。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute('admin_session');
        if (! is_array($session)) {
            return $this->responses->redirectClearingSession('/agent_admin/login');
        }

        if (
            ($session['must_change_password'] ?? false) === true
            && ! in_array($request->getUri()->getPath(), self::ALLOWED_PATHS, true)
        ) {
            return $this->responses->redirect('/agent_admin/password');
        }

        return $handler->handle($request);
    }
}
