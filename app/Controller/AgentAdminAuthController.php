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
     * 渲染登录页面。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
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

    /**
     * 校验凭据并建立登录会话。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
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

    /**
     * 注销当前登录会话。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
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

    /**
     * 生成新的表单令牌。
     *
     * @return string 返回new表单令牌字符串结果。
     */
    private function newFormToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 处理错误页面页面。
     *
     * @param AdminAuthException $exception 传入的 AdminAuthException 实例，用于处理错误页面页面。
     * @param string $event 当前监听到的事件对象。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function errorPage(AdminAuthException $exception, string $event): ResponseInterface
    {
        $this->logInfrastructureFailure($exception, $event);

        return $this->responses->html(
            $this->pages->error($exception->status(), $exception->publicMessage()),
            $exception->status()
        );
    }

    /**
     * 校验登录输入参数。
     *
     * @param string $username 登录用户名。
     * @param string $password 登录密码明文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
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

    /**
     * 处理log基础设施异常Failure。
     *
     * @param AdminAuthException $exception 传入的 AdminAuthException 实例，用于处理log基础设施异常Failure。
     * @param string $event 当前监听到的事件对象。
     * @return void 无返回值。
     */
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
