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

class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AdminAuthService $auth,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses,
        private StdoutLoggerInterface $logger
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
            $this->logInfrastructureFailure($exception);

            return $this->responses->html(
                $this->pages->error($exception->status(), $exception->publicMessage()),
                $exception->status()
            );
        }
    }

    private function logInfrastructureFailure(AdminAuthException $exception): void
    {
        if ($exception->status() !== 503) {
            return;
        }

        $internal = $exception->getPrevious() ?? $exception;
        $this->logger->error('agent_admin.session.infrastructure_failure', [
            'exception_type' => $internal::class,
            'exception' => $internal,
        ]);
    }
}
