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
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Http\Message\ResponseInterface;

/** 处理后台管理员登录、退出与会话 Cookie。 */
class AgentAdminAuthController extends AbstractController
{
    public function __construct(
        private AdminAuthService $auth,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses,
        private StdoutLoggerInterface $logger
    ) {
    }

    public function loginPage(): ResponseInterface
    {
        $rawSessionToken = $this->request->getCookieParams()['agent_admin_session'] ?? '';
        $sessionToken = is_string($rawSessionToken) ? trim($rawSessionToken) : '';

        if ($sessionToken !== '') {
            try {
                if ($this->auth->resolveSession($sessionToken) !== null) {
                    return $this->responses->redirect('/agent_admin');
                }
            } catch (AdminAuthException $exception) {
                return $this->errorPage($exception, 'agent_admin.login_page.infrastructure_failure');
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
        $rawUsername = $this->request->input('username', '');
        $username = is_string($rawUsername) ? $rawUsername : '';

        try {
            $cookieToken = $this->request->getCookieParams()['agent_admin_login_csrf'] ?? '';
            $formToken = $this->request->input('_csrf', '');
            if (
                ! is_string($cookieToken)
                || ! is_string($formToken)
                || $cookieToken === ''
                || $formToken === ''
                || ! hash_equals($cookieToken, $formToken)
            ) {
                throw AdminAuthException::invalidFormToken();
            }

            $rawPassword = $this->request->input('password', '');
            if (! is_string($rawUsername) || ! is_string($rawPassword)) {
                throw AdminAuthException::validation();
            }

            $password = $rawPassword;
            $this->validateLoginInput($username, $password);

            $rawClientIp = $this->request->server('remote_addr', 'unknown');
            $clientIp = is_string($rawClientIp) && $rawClientIp !== '' ? $rawClientIp : 'unknown';

            $result = $this->auth->login(
                $username,
                $password,
                $clientIp
            );

            return $this->responses->redirectWithSession(
                '/agent_admin',
                $result['token'],
                $result['session']['expires_at']
            );
        } catch (AdminAuthException $exception) {
            $this->logInfrastructureFailure($exception, 'agent_admin.login.infrastructure_failure');
            $replacementToken = $this->newFormToken();

            return $this->responses->loginPage(
                $this->pages->login(
                    $replacementToken,
                    $username,
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
            $sessionCsrf = is_array($session) ? ($session['csrf_token'] ?? '') : '';
            $formCsrf = $this->request->input('_csrf', '');
            if (
                ! is_string($sessionCsrf)
                || ! is_string($formCsrf)
                || $sessionCsrf === ''
                || $formCsrf === ''
                || ! hash_equals($sessionCsrf, $formCsrf)
            ) {
                throw AdminAuthException::invalidFormToken();
            }

            $rawSessionToken = $this->request->getAttribute('admin_session_token', '');
            $this->auth->logout(is_string($rawSessionToken) ? $rawSessionToken : '');

            return $this->responses->redirectClearingSession('/agent_admin/login', 303);
        } catch (AdminAuthException $exception) {
            return $this->errorPage($exception, 'agent_admin.logout.infrastructure_failure');
        }
    }

    private function newFormToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function errorPage(AdminAuthException $exception, string $event): ResponseInterface
    {
        $this->logInfrastructureFailure($exception, $event);

        return $this->responses->html(
            $this->pages->error($exception->status(), $exception->publicMessage()),
            $exception->status()
        );
    }

    private function validateLoginInput(string $username, string $password): void
    {
        if (
            preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username) !== 1
            || $password === ''
            || strlen($password) > 4096
        ) {
            throw AdminAuthException::validation();
        }
    }

    private function logInfrastructureFailure(AdminAuthException $exception, string $event): void
    {
        if ($exception->status() !== 503) {
            return;
        }

        $internal = $exception->getPrevious() ?? $exception;
        $this->logger->error($event . ' exception_type={exception_type}', [
            'exception_type' => $internal::class,
        ]);
    }
}
