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

namespace App\Controller;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Service\AdminAuthService;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

class AgentAdminAuthController extends AbstractController
{
    public function __construct(
        private AdminAuthService $auth,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

    public function loginPage(): ResponseInterface
    {
        $sessionToken = trim((string) ($this->request->getCookieParams()['agent_admin_session'] ?? ''));

        if ($sessionToken !== '') {
            try {
                if ($this->auth->resolveSession($sessionToken) !== null) {
                    return $this->responses->redirect('/agent_admin');
                }
            } catch (AdminAuthException $exception) {
                return $this->errorPage($exception);
            }
        }

        $formToken = $this->newFormToken();

        return $this->responses->loginPage(
            $this->pages->login($formToken),
            $formToken
        );
    }

    public function login(): ResponseInterface
    {
        try {
            $cookieToken = (string) ($this->request->getCookieParams()['agent_admin_login_csrf'] ?? '');
            $formToken = (string) $this->request->input('_csrf', '');
            if ($cookieToken === '' || $formToken === '' || ! hash_equals($cookieToken, $formToken)) {
                throw AdminAuthException::invalidFormToken();
            }

            $result = $this->auth->login(
                (string) $this->request->input('username', ''),
                (string) $this->request->input('password', ''),
                (string) $this->request->server('remote_addr', 'unknown')
            );

            return $this->responses->redirectWithSession(
                '/agent_admin',
                $result['token'],
                $result['session']['expires_at']
            );
        } catch (AdminAuthException $exception) {
            $replacementToken = $this->newFormToken();

            return $this->responses->loginPage(
                $this->pages->login(
                    $replacementToken,
                    (string) $this->request->input('username', ''),
                    $exception->publicMessage()
                ),
                $replacementToken,
                $exception->status()
            );
        }
    }

    public function logout(): ResponseInterface
    {
        try {
            $session = $this->request->getAttribute('admin_session');
            $sessionCsrf = is_array($session) ? (string) ($session['csrf_token'] ?? '') : '';
            $formCsrf = (string) $this->request->input('_csrf', '');
            if ($sessionCsrf === '' || $formCsrf === '' || ! hash_equals($sessionCsrf, $formCsrf)) {
                throw AdminAuthException::invalidFormToken();
            }

            $this->auth->logout((string) $this->request->getAttribute('admin_session_token', ''));

            return $this->responses->redirectClearingSession('/agent_admin/login', 303);
        } catch (AdminAuthException $exception) {
            return $this->errorPage($exception);
        }
    }

    private function newFormToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function errorPage(AdminAuthException $exception): ResponseInterface
    {
        return $this->responses->html(
            $this->pages->error($exception->status(), $exception->publicMessage()),
            $exception->status()
        );
    }
}
