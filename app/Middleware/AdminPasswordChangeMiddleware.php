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

    public function __construct(private AgentAdminResponseFactory $responses)
    {
    }

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
