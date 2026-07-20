<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Rbac\AdminRouteRegistry;
use App\Service\AdminAuthService;
use App\Service\AdminAuthorizationService;
use App\View\AgentAdminPageRenderer;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** 根据路由权限定义实时校验管理员授权，缺少定义时失败关闭。 */
final class AdminPermissionMiddleware implements MiddlewareInterface
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AdminAuthorizationService $authorization 注入的 AdminAuthorizationService 依赖。
     * @param AdminRouteRegistry $routes 注入的 AdminRouteRegistry 依赖。
     * @param AgentAdminPageRenderer $pages 注入的 AgentAdminPageRenderer 依赖。
     * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
     * @param AdminAuthService $auth 注入的 AdminAuthService 依赖。
     * @param StdoutLoggerInterface $logger 日志记录器。
     * @return void 无返回值。
     */
    public function __construct(
        private AdminAuthorizationService $authorization,
        private AdminRouteRegistry $routes,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses,
        private AdminAuthService $auth,
        private StdoutLoggerInterface $logger
    ) {
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
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();
        $permissionCode = null;
        foreach ($this->routes->definitions() as $definition) {
            if ($definition->method === $method && $definition->path === $path) {
                $permissionCode = $definition->permissionCode;
                break;
            }
        }

        if ($permissionCode === null) {
            return $this->deny($request, '路由未配置授权项，访问已拒绝。');
        }

        $session = $request->getAttribute('admin_session');
        if (! is_array($session) || ! $this->authorization->allows($session, $permissionCode)) {
            return $this->deny($request, '权限不足：' . $permissionCode);
        }

        return $handler->handle($request);
    }

    /**
     * 撤销被拒绝管理员的全部会话，并返回清除当前会话 Cookie 的提示页。
     *
     * @param ServerRequestInterface $request 当前请求。
     * @param string $message 权限不足提示。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function deny(ServerRequestInterface $request, string $message): ResponseInterface
    {
        $session = $request->getAttribute('admin_session');
        $adminId = is_array($session) ? (int) ($session['admin_id'] ?? 0) : 0;
        if ($adminId <= 0) {
            return $this->responses->htmlClearingSession($this->pages->error(403, $message), 403);
        }

        try {
            $this->auth->revokeAdminSessions($adminId);
        } catch (AdminAuthException $exception) {
            $internal = $exception->getPrevious() ?? $exception;
            $this->logger->error(
                'agent_admin.permission_denied.session_revocation_failure exception_type={exception_type}',
                ['exception_type' => $internal::class]
            );

            return $this->responses->htmlClearingSession(
                $this->pages->error($exception->status(), $exception->publicMessage()),
                $exception->status()
            );
        }

        return $this->responses->htmlClearingSession($this->pages->error(403, $message), 403);
    }
}
