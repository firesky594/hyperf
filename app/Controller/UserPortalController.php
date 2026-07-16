<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Http\UserPortalResponseFactory;
use App\Service\AuthService;
use App\Service\UserIdentityService;
use App\View\UserPortalPageRenderer;
use Psr\Http\Message\ResponseInterface;

final class UserPortalController extends AbstractController
{
    public function __construct(private AuthService $auth, private UserIdentityService $identities, private UserPortalPageRenderer $pages, private UserPortalResponseFactory $responses) {}
    public function loginPage(): ResponseInterface { $csrf = bin2hex(random_bytes(32)); return $this->responses->loginPage($this->pages->login($csrf), $csrf); }
    public function login(): ResponseInterface
    {
        $cookie = $this->request->getCookieParams()['uniapi_portal_csrf'] ?? ''; $form = $this->request->input('_csrf', '');
        if (! is_string($cookie) || ! is_string($form) || $cookie === '' || ! hash_equals($cookie, $form)) { $csrf = bin2hex(random_bytes(32)); return $this->responses->loginPage($this->pages->login($csrf, '请求验证失败。'), $csrf, 419); }
        try { $result = $this->auth->login((string) $this->request->input('username', ''), (string) $this->request->input('password', '')); return $this->responses->login('/workspace', $result['token'], $result['expires_in']); }
        catch (AuthException $exception) { $csrf = bin2hex(random_bytes(32)); return $this->responses->loginPage($this->pages->login($csrf, $exception->publicMessage()), $csrf, $exception->status()); }
    }
    public function workspace(): ResponseInterface { return $this->render('overview'); }
    public function buyer(): ResponseInterface { return $this->render('buyer'); }
    public function supplier(): ResponseInterface { return $this->render('supplier'); }
    public function supplierApply(): ResponseInterface { return $this->supplierWrite(false); }
    public function supplierUpdate(): ResponseInterface { return $this->supplierWrite(true); }
    public function logout(): ResponseInterface
    {
        $session = $this->session(); if (! $this->csrf($session)) { return $this->responses->html('请求验证失败。', 419); }
        $token = $this->request->getAttribute('user_session_token', ''); $this->auth->logout(is_string($token) ? $token : ''); return $this->responses->clear('/portal/login');
    }
    private function render(string $active): ResponseInterface { $session = $this->session(); return $this->responses->html($this->pages->workspace($session, $this->identities->workspace((int) $session['user_id']), (string) ($session['csrf_token'] ?? ''), $active)); }
    private function supplierWrite(bool $update): ResponseInterface
    {
        $session = $this->session(); if (! $this->csrf($session)) { return $this->responses->html('请求验证失败。', 419); }
        try { $args = [(int) $session['user_id'], (string) $this->request->input('company_name', ''), (string) $this->request->input('contact_name', ''), (string) $this->request->input('contact_email', '')]; $update ? $this->identities->updateSupplier(...$args) : $this->identities->applySupplier(...$args); return $this->responses->redirect('/workspace/supplier'); }
        catch (AuthException $exception) { return $this->responses->html($exception->publicMessage(), $exception->status()); }
    }
    /** @return array<string,mixed> */
    private function session(): array { $session = $this->request->getAttribute('user_session'); if (! is_array($session)) { throw AuthException::invalidCredentials(); } return $session; }
    /** @param array<string,mixed> $session */
    private function csrf(array $session): bool { $expected = $session['csrf_token'] ?? ''; $actual = $this->request->input('_csrf', ''); return is_string($expected) && is_string($actual) && $expected !== '' && hash_equals($expected, $actual); }
}
