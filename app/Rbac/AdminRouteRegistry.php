<?php

declare(strict_types=1);

namespace App\Rbac;

final class AdminRouteRegistry
{
    /**
     * @return list<AdminRouteDefinition>
     */
    public function definitions(): array
    {
        return [
            $this->route('GET', '/agent_admin/login', 'App\\Controller\\AgentAdminAuthController@loginPage'),
            $this->route('POST', '/agent_admin/login', 'App\\Controller\\AgentAdminAuthController@login'),
            $this->route('GET', '/agent_admin/password', 'App\\Controller\\AgentAdminPasswordController@page'),
            $this->route('POST', '/agent_admin/password', 'App\\Controller\\AgentAdminPasswordController@change'),
            $this->route('POST', '/agent_admin/logout', 'App\\Controller\\AgentAdminAuthController@logout'),
            $this->route('GET', '/agent_admin', 'App\\Controller\\AgentAdminHomeController@index', 'dashboard.view', '查看系统总览'),
            $this->route('GET', '/agent_admin/administrators', 'App\\Controller\\AgentAdminUserController@index', 'administrators.view', '查看管理员'),
            $this->route('POST', '/agent_admin/administrators/create', 'App\\Controller\\AgentAdminUserController@create', 'administrators.create', '创建管理员'),
            $this->route('POST', '/agent_admin/administrators/update', 'App\\Controller\\AgentAdminUserController@update', 'administrators.update', '编辑管理员'),
            $this->route('POST', '/agent_admin/administrators/status', 'App\\Controller\\AgentAdminUserController@status', 'administrators.status', '启停管理员'),
            $this->route('POST', '/agent_admin/administrators/roles', 'App\\Controller\\AgentAdminUserController@roles', 'administrators.roles', '分配管理员角色'),
            $this->route('POST', '/agent_admin/administrators/password-reset', 'App\\Controller\\AgentAdminUserController@resetPassword', 'administrators.password-reset', '重置管理员密码'),
            $this->route('GET', '/agent_admin/roles', 'App\\Controller\\AgentAdminRoleController@index', 'roles.view', '查看角色'),
            $this->route('POST', '/agent_admin/roles/create', 'App\\Controller\\AgentAdminRoleController@create', 'roles.create', '创建角色'),
            $this->route('POST', '/agent_admin/roles/update', 'App\\Controller\\AgentAdminRoleController@update', 'roles.update', '编辑角色'),
            $this->route('POST', '/agent_admin/roles/status', 'App\\Controller\\AgentAdminRoleController@status', 'roles.status', '启停角色'),
            $this->route('POST', '/agent_admin/roles/permissions', 'App\\Controller\\AgentAdminRoleController@permissions', 'roles.permissions', '分配角色权限'),
            $this->route('GET', '/agent_admin/permissions', 'App\\Controller\\AgentAdminPermissionController@index', 'permissions.view', '查看权限'),
            $this->route('POST', '/agent_admin/permissions/create', 'App\\Controller\\AgentAdminPermissionController@create', 'permissions.create', '创建自定义权限'),
            $this->route('POST', '/agent_admin/permissions/update', 'App\\Controller\\AgentAdminPermissionController@update', 'permissions.update', '编辑自定义权限'),
            $this->route('POST', '/agent_admin/permissions/status', 'App\\Controller\\AgentAdminPermissionController@status', 'permissions.status', '启停权限'),
            $this->route('POST', '/agent_admin/permissions/sync', 'App\\Controller\\AgentAdminPermissionController@sync', 'permissions.sync', '同步系统权限'),
            $this->route('GET', '/agent_admin/menus', 'App\\Controller\\AgentAdminMenuController@index', 'menus.view', '查看菜单'),
            $this->route('POST', '/agent_admin/menus/create', 'App\\Controller\\AgentAdminMenuController@create', 'menus.create', '创建菜单'),
            $this->route('POST', '/agent_admin/menus/update', 'App\\Controller\\AgentAdminMenuController@update', 'menus.update', '编辑菜单'),
            $this->route('POST', '/agent_admin/menus/status', 'App\\Controller\\AgentAdminMenuController@status', 'menus.status', '启停菜单'),
            $this->route('GET', '/agent_admin/audit', 'App\\Controller\\AgentAdminAuditController@index', 'audit.view', '查看操作日志'),
        ];
    }

    private function route(
        string $method,
        string $path,
        string $handler,
        ?string $permissionCode = null,
        ?string $permissionName = null
    ): AdminRouteDefinition {
        return new AdminRouteDefinition($method, $path, $handler, $permissionCode, $permissionName);
    }
}
