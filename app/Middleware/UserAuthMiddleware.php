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

class UserAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth, private UserPortalResponseFactory $responses) {}
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getCookieParams()['uniapi_user_session'] ?? '';
        if (! is_string($token) || trim($token) === '') { $header = $request->getHeaderLine('Authorization'); $token = preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1 ? trim($matches[1]) : ''; }
        try { $session = $this->auth->resolveToken($token); } catch (AuthException) { return $this->responses->html('认证服务暂不可用', 503); }
        if ($session === null) { return $this->responses->clear('/portal/login'); }
        return $handler->handle($request->withAttribute('user_session', $session)->withAttribute('user_session_token', $token));
    }
}
