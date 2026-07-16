<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Http\UserPortalResponseFactory;
use App\Service\AuthService;
use App\Service\UserIdentityService;
use App\View\UserPortalPageRenderer;
use Psr\Http\Message\ResponseInterface;

/** 处理用户门户登录、工作台和供应商身份申请维护。 */
final class UserPortalController extends AbstractController
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AuthService $auth 注入的 AuthService 依赖。
     * @param UserIdentityService $identities 注入的 UserIdentityService 依赖。
     * @param UserPortalPageRenderer $pages 注入的 UserPortalPageRenderer 依赖。
     * @param UserPortalResponseFactory $responses 注入的 UserPortalResponseFactory 依赖。
     * @return void 无返回值。
     */
    public function __construct(private AuthService $auth, private UserIdentityService $identities, private UserPortalPageRenderer $pages, private UserPortalResponseFactory $responses) {}
    /**
     * 渲染登录页面。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function loginPage(): ResponseInterface { $csrf = bin2hex(random_bytes(32)); return $this->responses->loginPage($this->pages->login($csrf), $csrf); }
    /**
     * 校验凭据并建立登录会话。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function login(): ResponseInterface
    {
        $cookie = $this->request->getCookieParams()['uniapi_portal_csrf'] ?? ''; $form = $this->request->input('_csrf', '');
        if (! is_string($cookie) || ! is_string($form) || $cookie === '' || ! hash_equals($cookie, $form)) { $csrf = bin2hex(random_bytes(32)); return $this->responses->loginPage($this->pages->login($csrf, '请求验证失败。'), $csrf, 419); }
        try { $result = $this->auth->login($this->stringInput('username'), $this->stringInput('password')); return $this->responses->login('/workspace', $result['token'], $result['expires_in']); }
        catch (AuthException $exception) { $csrf = bin2hex(random_bytes(32)); return $this->responses->loginPage($this->pages->login($csrf, $exception->publicMessage()), $csrf, $exception->status()); }
    }
    /**
     * 处理workspace。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function workspace(): ResponseInterface { return $this->render('overview'); }
    /**
     * 处理采购方。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function buyer(): ResponseInterface { return $this->render('buyer'); }
    /**
     * 处理供应商。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function supplier(): ResponseInterface { return $this->render('supplier'); }
    /**
     * 处理供应商Apply。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function supplierApply(): ResponseInterface { return $this->supplierWrite(false); }
    /**
     * 处理供应商Update。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function supplierUpdate(): ResponseInterface { return $this->supplierWrite(true); }
    /**
     * 注销当前登录会话。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function logout(): ResponseInterface
    {
        $session = $this->session(); if (! $this->csrf($session)) { return $this->responses->html('请求验证失败。', 419); }
        $token = $this->request->getAttribute('user_session_token', ''); $this->auth->logout(is_string($token) ? $token : ''); return $this->responses->clear('/portal/login');
    }
    /**
     * 处理render。
     *
     * @param string $active 当前激活的导航项。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function render(string $active): ResponseInterface { $session = $this->session(); return $this->responses->html($this->pages->workspace($session, $this->identities->workspace((int) $session['user_id']), (string) ($session['csrf_token'] ?? ''), $active)); }
    /**
     * 处理供应商Write。
     *
     * @param bool $update 控制update行为的布尔标记。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function supplierWrite(bool $update): ResponseInterface
    {
        $session = $this->session(); if (! $this->csrf($session)) { return $this->responses->html('请求验证失败。', 419); }
        try { $args = [(int) $session['user_id'], $this->stringInput('company_name'), $this->stringInput('contact_name'), $this->stringInput('contact_email')]; $update ? $this->identities->updateSupplier(...$args) : $this->identities->applySupplier(...$args); return $this->responses->redirect('/workspace/supplier'); }
        catch (AuthException $exception) { return $this->responses->html($exception->publicMessage(), $exception->status()); }
    }
    /**
     * 读取并校验当前请求的会话数据。
     *
     * @return array<string,mixed> 返回会话结构化数据。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function session(): array { $session = $this->request->getAttribute('user_session'); if (! is_array($session)) { throw AuthException::invalidCredentials(); } return $session; }
    /**
     * 读取当前会话的 CSRF 令牌。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @return bool 条件满足时返回 true，否则返回 false。
     */
    private function csrf(array $session): bool { $expected = $session['csrf_token'] ?? ''; $actual = $this->request->input('_csrf', ''); return is_string($expected) && is_string($actual) && $expected !== '' && hash_equals($expected, $actual); }
    /**
     * 处理string输入参数。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return string 返回string输入参数字符串结果。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function stringInput(string $key): string { $value=$this->request->input($key,'');if(!is_string($value)){throw AuthException::badRequest('请求字段格式错误。');}return $value; }
}
