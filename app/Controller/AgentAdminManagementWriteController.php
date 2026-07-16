<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AdminAuthException;
use App\Http\AgentAdminResponseFactory;
use App\Service\AdminMenuManagementService;
use App\Service\AdminPermissionManagementService;
use App\Service\AdminRoleManagementService;
use App\Service\AdminUserManagementService;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

final class AgentAdminManagementWriteController extends AbstractController
{
    public function __construct(
        private AdminUserManagementService $administrators,
        private AdminRoleManagementService $roles,
        private AdminPermissionManagementService $permissions,
        private AdminMenuManagementService $menus,
        private AgentAdminResponseFactory $responses,
        private AgentAdminPageRenderer $pages
    ) {}

    public function administratorCreate(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->createAdministrator($this->string('username'), $c)); }
    public function administratorUpdate(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->updateAdministrator($this->int('id'), $this->string('username'), $c)); }
    public function administratorStatus(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->setStatus($this->int('id'), $this->bool('enabled'), $c)); }
    public function administratorRoles(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->assignRoles($this->int('id'), $this->ids('role_ids'), $c)); }
    public function administratorPasswordReset(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->resetPassword($this->int('id'), $c)); }
    public function roleCreate(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->createRole($this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    public function roleUpdate(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->updateRole($this->int('id'), $this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    public function roleStatus(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->setStatus($this->int('id'), $this->bool('enabled'), $c)); }
    public function rolePermissions(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->assignPermissions($this->int('id'), $this->ids('permission_ids'), $c)); }
    public function permissionCreate(): ResponseInterface { return $this->write('/agent_admin/permissions', fn (array $c) => $this->permissions->createCustom($this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    public function permissionUpdate(): ResponseInterface { return $this->write('/agent_admin/permissions', fn (array $c) => $this->permissions->updateCustom($this->int('id'), $this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    public function permissionStatus(): ResponseInterface { return $this->write('/agent_admin/permissions', fn (array $c) => $this->permissions->setStatus($this->int('id'), $this->bool('enabled'), $c)); }
    public function menuCreate(): ResponseInterface { return $this->write('/agent_admin/menus', fn (array $c) => $this->menus->createMenu($this->nullableInt('parent_id'), $this->string('name'), $this->string('icon'), $this->int('sort_order'), $this->string('route_path'), $this->nullableInt('permission_id'), $c)); }
    public function menuUpdate(): ResponseInterface { return $this->write('/agent_admin/menus', fn (array $c) => $this->menus->updateMenu($this->int('id'), $this->nullableInt('parent_id'), $this->string('name'), $this->string('icon'), $this->int('sort_order'), $this->string('route_path'), $this->nullableInt('permission_id'), $c)); }
    public function menuStatus(): ResponseInterface { return $this->write('/agent_admin/menus', fn (array $c) => $this->menus->setStatus($this->int('id'), $this->bool('enabled'), $c)); }

    private function write(string $redirect, callable $operation): ResponseInterface
    {
        try {
            $session = $this->session(); $this->csrf($session); $operation($this->context($session));
            return $this->responses->redirect($redirect, 303);
        } catch (AdminAuthException $exception) {
            return $this->responses->html($this->pages->error($exception->status(), $exception->publicMessage()), $exception->status());
        }
    }

    /** @return array<string,mixed> */
    private function session(): array { $session = $this->request->getAttribute('admin_session'); if (! is_array($session)) { throw AdminAuthException::invalidCredentials(); } return $session; }
    /** @param array<string,mixed> $session */
    private function csrf(array $session): void { $expected = $session['csrf_token'] ?? ''; $actual = $this->request->input('_csrf', ''); if (! is_string($expected) || ! is_string($actual) || $expected === '' || ! hash_equals($expected, $actual)) { throw AdminAuthException::invalidFormToken(); } }
    /** @param array<string,mixed> $session @return array<string,mixed> */
    private function context(array $session): array { return ['request_id' => (string) $this->request->getAttribute('request_id', ''), 'actor_admin_id' => (int) ($session['admin_id'] ?? 0), 'actor_username' => (string) ($session['username'] ?? ''), 'request_method' => 'POST', 'request_path' => $this->request->getUri()->getPath(), 'ip_address' => (string) $this->request->server('remote_addr', ''), 'user_agent' => (string) $this->request->header('user-agent', '')]; }
    private function string(string $key): string { $value = $this->request->input($key, ''); return is_string($value) ? $value : ''; }
    private function int(string $key): int { return (int) $this->request->input($key, 0); }
    private function nullableInt(string $key): ?int { $value = $this->request->input($key, null); return $value === null || $value === '' ? null : (int) $value; }
    private function bool(string $key): bool { return in_array($this->request->input($key, '0'), [1, '1', true, 'true', 'on'], true); }
    /** @return list<int> */
    private function ids(string $key): array { $value = $this->request->input($key, []); if (is_string($value)) { $value = $value === '' ? [] : explode(',', $value); } return is_array($value) ? array_values(array_map('intval', $value)) : []; }
}
