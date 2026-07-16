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
    /** 初始化当前组件所需的依赖。 */
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

    /** 执行 `administrators` 方法对应的业务处理。 */
    public function administrators(): ResponseInterface
    {
        return $this->page('administrators', $this->administrators->listAdministrators());
    }

    /** 执行 `roles` 方法对应的业务处理。 */
    public function roles(): ResponseInterface
    {
        return $this->page('roles', $this->roles->listRoles());
    }

    /** 执行 `permissions` 方法对应的业务处理。 */
    public function permissions(): ResponseInterface
    {
        return $this->page('permissions', $this->permissions->listPermissions());
    }

    /** 执行 `menus` 方法对应的业务处理。 */
    public function menus(): ResponseInterface
    {
        return $this->page('menus', $this->menus->listMenus());
    }

    /** 记录审计 `audit` 方法对应的数据或业务状态。 */
    public function audit(): ResponseInterface
    {
        return $this->page('audit', $this->audit->search((string) $this->request->input('action', '')));
    }

    /** 渲染当前功能页面。 @param list<array<string,mixed>> $rows */
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
