<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\AgentAdminResponseFactory;
use App\Rbac\AdminRouteRegistry;
use App\Service\AdminAuthorizationService;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** 根据路由权限定义实时校验管理员授权，缺少定义时失败关闭。 */
final class AdminPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AdminAuthorizationService $authorization,
        private AdminRouteRegistry $routes,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

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
            return $this->responses->html(
                $this->pages->error(403, '路由未配置授权项，访问已拒绝。'),
                403
            );
        }

        $session = $request->getAttribute('admin_session');
        if (! is_array($session) || ! $this->authorization->allows($session, $permissionCode)) {
            return $this->responses->html(
                $this->pages->error(403, '权限不足：' . $permissionCode),
                403
            );
        }

        return $handler->handle($request);
    }
}
