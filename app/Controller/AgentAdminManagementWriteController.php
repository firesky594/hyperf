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

/** 处理后台管理员、角色、权限和菜单的受控写操作。 */
final class AgentAdminManagementWriteController extends AbstractController
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AdminUserManagementService $administrators 注入的 AdminUserManagementService 依赖。
     * @param AdminRoleManagementService $roles 注入的 AdminRoleManagementService 依赖。
     * @param AdminPermissionManagementService $permissions 注入的 AdminPermissionManagementService 依赖。
     * @param AdminMenuManagementService $menus 注入的 AdminMenuManagementService 依赖。
     * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
     * @param AgentAdminPageRenderer $pages 注入的 AgentAdminPageRenderer 依赖。
     * @return void 无返回值。
     */
    public function __construct(
        private AdminUserManagementService $administrators,
        private AdminRoleManagementService $roles,
        private AdminPermissionManagementService $permissions,
        private AdminMenuManagementService $menus,
        private AgentAdminResponseFactory $responses,
        private AgentAdminPageRenderer $pages
    ) {}

    /**
     * 处理管理员Create。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function administratorCreate(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->createAdministrator($this->string('username'), $c)); }
    /**
     * 处理管理员Update。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function administratorUpdate(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->updateAdministrator($this->int('id'), $this->string('username'), $c)); }
    /**
     * 处理管理员状态。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function administratorStatus(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->setStatus($this->int('id'), $this->bool('enabled'), $c)); }
    /**
     * 处理管理员角色列表。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function administratorRoles(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->assignRoles($this->int('id'), $this->ids('role_ids'), $c)); }
    /**
     * 处理管理员密码Reset。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function administratorPasswordReset(): ResponseInterface { return $this->write('/agent_admin/administrators', fn (array $c) => $this->administrators->resetPassword($this->int('id'), $c)); }
    /**
     * 处理角色Create。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function roleCreate(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->createRole($this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    /**
     * 处理角色Update。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function roleUpdate(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->updateRole($this->int('id'), $this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    /**
     * 处理角色状态。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function roleStatus(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->setStatus($this->int('id'), $this->bool('enabled'), $c)); }
    /**
     * 处理角色权限列表。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function rolePermissions(): ResponseInterface { return $this->write('/agent_admin/roles', fn (array $c) => $this->roles->assignPermissions($this->int('id'), $this->ids('permission_ids'), $c)); }
    /**
     * 处理权限Create。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function permissionCreate(): ResponseInterface { return $this->write('/agent_admin/permissions', fn (array $c) => $this->permissions->createCustom($this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    /**
     * 处理权限Update。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function permissionUpdate(): ResponseInterface { return $this->write('/agent_admin/permissions', fn (array $c) => $this->permissions->updateCustom($this->int('id'), $this->string('name'), $this->string('code'), $this->string('description'), $c)); }
    /**
     * 处理权限状态。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function permissionStatus(): ResponseInterface { return $this->write('/agent_admin/permissions', fn (array $c) => $this->permissions->setStatus($this->int('id'), $this->bool('enabled'), $c)); }
    /**
     * 处理菜单Create。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function menuCreate(): ResponseInterface { return $this->write('/agent_admin/menus', fn (array $c) => $this->menus->createMenu($this->nullableInt('parent_id'), $this->string('name'), $this->string('icon'), $this->int('sort_order'), $this->string('route_path'), $this->nullableInt('permission_id'), $c)); }
    /**
     * 处理菜单Update。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function menuUpdate(): ResponseInterface { return $this->write('/agent_admin/menus', fn (array $c) => $this->menus->updateMenu($this->int('id'), $this->nullableInt('parent_id'), $this->string('name'), $this->string('icon'), $this->int('sort_order'), $this->string('route_path'), $this->nullableInt('permission_id'), $c)); }
    /**
     * 处理菜单状态。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function menuStatus(): ResponseInterface { return $this->write('/agent_admin/menus', fn (array $c) => $this->menus->setStatus($this->int('id'), $this->bool('enabled'), $c)); }

    /**
     * 统一执行带校验和异常处理的写操作。
     *
     * @param string $redirect 操作完成后的跳转地址。
     * @param callable $operation 需要统一执行的业务回调。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function write(string $redirect, callable $operation): ResponseInterface
    {
        try {
            $session = $this->session(); $this->csrf($session); $operation($this->context($session));
            return $this->responses->redirect($redirect, 303);
        } catch (AdminAuthException $exception) {
            return $this->responses->html($this->pages->error($exception->status(), $exception->publicMessage()), $exception->status());
        }
    }

    /**
     * 读取并校验当前请求的会话数据。
     *
     * @return array<string,mixed> 返回会话结构化数据。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function session(): array { $session = $this->request->getAttribute('admin_session'); if (! is_array($session)) { throw AdminAuthException::invalidCredentials(); } return $session; }
    /**
     * 读取当前会话的 CSRF 令牌。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function csrf(array $session): void { $expected = $session['csrf_token'] ?? ''; $actual = $this->request->input('_csrf', ''); if (! is_string($expected) || ! is_string($actual) || $expected === '' || ! hash_equals($expected, $actual)) { throw AdminAuthException::invalidFormToken(); } }
    /**
     * 处理操作上下文。
     *
     * @param array<string,mixed> $session 当前登录会话数据。
     * @return array<string,mixed> 返回操作上下文结构化数据。
     */
    private function context(array $session): array { return ['request_id' => (string) $this->request->getAttribute('request_id', ''), 'actor_admin_id' => (int) ($session['admin_id'] ?? 0), 'actor_username' => (string) ($session['username'] ?? ''), 'request_method' => 'POST', 'request_path' => $this->request->getUri()->getPath(), 'ip_address' => (string) $this->request->server('remote_addr', ''), 'user_agent' => (string) $this->request->header('user-agent', '')]; }
    /**
     * 处理string。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return string 返回string字符串结果。
     */
    private function string(string $key): string { $value = $this->request->input($key, ''); return is_string($value) ? $value : ''; }
    /**
     * 处理int。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return int 返回int整数结果。
     */
    private function int(string $key): int { return (int) $this->request->input($key, 0); }
    /**
     * 处理nullableInt。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return ?int 查询成功时返回对应数据，不存在时返回 null。
     */
    private function nullableInt(string $key): ?int { $value = $this->request->input($key, null); return $value === null || $value === '' ? null : (int) $value; }
    /**
     * 处理bool。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return bool 条件满足时返回 true，否则返回 false。
     */
    private function bool(string $key): bool { return in_array($this->request->input($key, '0'), [1, '1', true, 'true', 'on'], true); }
    /**
     * 处理标识列表。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return list<int> 返回标识列表结构化数据。
     */
    private function ids(string $key): array { $value = $this->request->input($key, []); if (is_string($value)) { $value = $value === '' ? [] : explode(',', $value); } return is_array($value) ? array_values(array_map('intval', $value)) : []; }
}
