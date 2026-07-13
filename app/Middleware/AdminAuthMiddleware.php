<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Service\AdminAuthService;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AdminAuthService $auth,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = trim((string) ($request->getCookieParams()['agent_admin_session'] ?? ''));
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
            return $this->responses->html(
                $this->pages->unavailable($exception->publicMessage()),
                $exception->status()
            );
        }
    }
}
