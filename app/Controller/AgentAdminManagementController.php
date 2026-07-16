<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\AgentAdminResponseFactory;
use App\Service\AdminAuditQueryService;
use App\Service\AdminMenuManagementService;
use App\Service\AdminPermissionManagementService;
use App\Service\AdminRoleManagementService;
use App\Service\AdminUserManagementService;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

/** 提供管理员、角色、权限、菜单和审计日志的后台查询页面。 */
final class AgentAdminManagementController extends AbstractController
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AgentAdminPageRenderer $pages 注入的 AgentAdminPageRenderer 依赖。
     * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
     * @param AdminUserManagementService $administrators 注入的 AdminUserManagementService 依赖。
     * @param AdminRoleManagementService $roles 注入的 AdminRoleManagementService 依赖。
     * @param AdminPermissionManagementService $permissions 注入的 AdminPermissionManagementService 依赖。
     * @param AdminMenuManagementService $menus 注入的 AdminMenuManagementService 依赖。
     * @param AdminAuditQueryService $audit 注入的 AdminAuditQueryService 依赖。
     * @return void 无返回值。
     */
    public function __construct(
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses,
        private AdminUserManagementService $administrators,
        private AdminRoleManagementService $roles,
        private AdminPermissionManagementService $permissions,
        private AdminMenuManagementService $menus,
        private AdminAuditQueryService $audit
    ) {
    }

    /**
     * 处理管理员列表。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function administrators(): ResponseInterface
    {
        return $this->page('administrators', $this->administrators->listAdministrators());
    }

    /**
     * 处理角色列表。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function roles(): ResponseInterface
    {
        return $this->page('roles', $this->roles->listRoles());
    }

    /**
     * 处理权限列表。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function permissions(): ResponseInterface
    {
        return $this->page('permissions', $this->permissions->listPermissions());
    }

    /**
     * 处理菜单列表。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function menus(): ResponseInterface
    {
        return $this->page('menus', $this->menus->listMenus());
    }

    /**
     * 处理审计记录。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function audit(): ResponseInterface
    {
        return $this->page('audit', $this->audit->search((string) $this->request->input('action', '')));
    }

    /**
     * 渲染当前功能页面。
     *
     * @param string $module 后台管理模块标识。
     * @param list<array<string,mixed>> $rows 数据库查询结果列表。
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    private function page(string $module, array $rows): ResponseInterface
    {
        $session = $this->request->getAttribute('admin_session');

        return $this->responses->html($this->pages->management(
            $module,
            is_array($session) ? $session : [],
            $rows
        ));
    }
}
