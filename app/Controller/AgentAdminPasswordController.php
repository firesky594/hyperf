<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Service\AdminPasswordService;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

/** 提供管理员密码修改页面并处理强制改密。 */
class AgentAdminPasswordController extends AbstractController
{
    /** 初始化当前组件所需的依赖。 */
    public function __construct(
        private AdminPasswordService $passwords,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

    /** 渲染当前功能页面。 */
    public function page(): ResponseInterface
    {
        $session = $this->session();

        return $this->responses->html($this->pages->password(
            (string) ($session['csrf_token'] ?? ''),
            ($session['must_change_password'] ?? false) === true
        ));
    }

    /** 修改 `change` 方法对应的数据或业务状态。 */
    public function change(): ResponseInterface
    {
        $session = $this->session();

        try {
            $this->validateCsrf($session);
            $current = $this->request->input('current_password', '');
            $new = $this->request->input('new_password', '');
            $confirmation = $this->request->input('new_password_confirmation', '');
            if (! is_string($current) || ! is_string($new) || ! is_string($confirmation)) {
                throw AdminAuthException::validation('Password change input is invalid.');
            }
            if ($new === '' || ! hash_equals($new, $confirmation)) {
                throw AdminAuthException::validation('New password confirmation does not match.');
            }

            $this->passwords->changePassword((int) ($session['admin_id'] ?? 0), $current, $new);

            return $this->responses->redirectClearingSession('/agent_admin/login', 303);
        } catch (AdminAuthException $exception) {
            return $this->responses->html(
                $this->pages->password(
                    (string) ($session['csrf_token'] ?? ''),
                    ($session['must_change_password'] ?? false) === true,
                    $exception->publicMessage()
                ),
                $exception->status()
            );
        }
    }

    /**
     * 读取并校验当前请求的会话数据。
     * @return array<string,mixed>
     */
    private function session(): array
    {
        $session = $this->request->getAttribute('admin_session');

        return is_array($session) ? $session : [];
    }

    /**
     * 校验 `validateCsrf` 方法对应的数据或业务状态。
     * @param array<string,mixed> $session
     */
    private function validateCsrf(array $session): void
    {
        $expected = $session['csrf_token'] ?? '';
        $submitted = $this->request->input('_csrf', '');
        if (
            ! is_string($expected)
            || ! is_string($submitted)
            || $expected === ''
            || $submitted === ''
            || ! hash_equals($expected, $submitted)
        ) {
            throw AdminAuthException::invalidFormToken();
        }
    }
}
