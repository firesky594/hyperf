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

namespace App\Middleware;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Service\AdminAuthService;
use App\View\AgentAdminPageRenderer;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** 校验后台会话并把管理员登录态注入当前请求。 */
class AdminAuthMiddleware implements MiddlewareInterface
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AdminAuthService $auth 注入的 AdminAuthService 依赖。
     * @param AgentAdminPageRenderer $pages 注入的 AgentAdminPageRenderer 依赖。
     * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
     * @param StdoutLoggerInterface $logger 日志记录器。
     * @return void 无返回值。
     */
    public function __construct(
        private AdminAuthService $auth,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses,
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
        $rawToken = $request->getCookieParams()['agent_admin_session'] ?? '';
        $token = is_string($rawToken) ? trim($rawToken) : '';
        if ($token === '') {
            return $this->responses->redirectClearingSession('/agent_admin/login');
        }

        try {
            $session = $this->auth->resolveSession($token);
            if ($session === null) {
                return $this->responses->redirectClearingSession('/agent_admin/login');
            }

            return $handler->handle(
                $request
                    ->withAttribute('admin_session', $session)
                    ->withAttribute('admin_session_token', $token)
            );
        } catch (AdminAuthException $exception) {
            $this->logInfrastructureFailure($exception);

            return $this->responses->html(
                $this->pages->error($exception->status(), $exception->publicMessage()),
                $exception->status()
            );
        }
    }

    /**
     * 处理log基础设施异常Failure。
     *
     * @param AdminAuthException $exception 传入的 AdminAuthException 实例，用于处理log基础设施异常Failure。
     * @return void 无返回值。
     */
    private function logInfrastructureFailure(AdminAuthException $exception): void
    {
        if ($exception->status() !== 503) {
            return;
        }

        $internal = $exception->getPrevious() ?? $exception;
        $this->logger->error('agent_admin.session.infrastructure_failure exception_type={exception_type}', [
            'exception_type' => $internal::class,
        ]);
    }
}
