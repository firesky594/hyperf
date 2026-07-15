<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Service\AdminPasswordService;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

class AgentAdminPasswordController extends AbstractController
{
    public function __construct(
        private AdminPasswordService $passwords,
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

    public function page(): ResponseInterface
    {
        $session = $this->session();

        return $this->responses->html($this->pages->password(
            (string) ($session['csrf_token'] ?? ''),
            ($session['must_change_password'] ?? false) === true
        ));
    }

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
     * @return array<string,mixed>
     */
    private function session(): array
    {
        $session = $this->request->getAttribute('admin_session');

        return is_array($session) ? $session : [];
    }

    /**
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
