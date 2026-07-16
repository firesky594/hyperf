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
use App\Middleware\AdminAuthMiddleware;
use App\Middleware\AdminPasswordChangeMiddleware;
use App\Middleware\AdminPermissionMiddleware;
use App\Middleware\UserAuthMiddleware;
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'HEAD'], '/', 'App\Controller\IndexController@index');
Router::get('/agent_admin/login', 'App\Controller\AgentAdminAuthController@loginPage');
Router::post('/agent_admin/login', 'App\Controller\AgentAdminAuthController@login');
Router::get('/agent_admin', 'App\Controller\AgentAdminHomeController@index', [
    'middleware' => [AdminAuthMiddleware::class, AdminPasswordChangeMiddleware::class, AdminPermissionMiddleware::class],
]);
foreach ([
    '/agent_admin/administrators' => 'administrators',
    '/agent_admin/roles' => 'roles',
    '/agent_admin/permissions' => 'permissions',
    '/agent_admin/menus' => 'menus',
    '/agent_admin/audit' => 'audit',
] as $path => $action) {
    Router::get($path, 'App\Controller\AgentAdminManagementController@' . $action, [
        'middleware' => [AdminAuthMiddleware::class, AdminPasswordChangeMiddleware::class, AdminPermissionMiddleware::class],
    ]);
}
$adminWriteMiddleware = [AdminAuthMiddleware::class, AdminPasswordChangeMiddleware::class, AdminPermissionMiddleware::class];
foreach ([
    '/agent_admin/administrators/create' => 'administratorCreate',
    '/agent_admin/administrators/update' => 'administratorUpdate',
    '/agent_admin/administrators/status' => 'administratorStatus',
    '/agent_admin/administrators/roles' => 'administratorRoles',
    '/agent_admin/administrators/password-reset' => 'administratorPasswordReset',
    '/agent_admin/roles/create' => 'roleCreate',
    '/agent_admin/roles/update' => 'roleUpdate',
    '/agent_admin/roles/status' => 'roleStatus',
    '/agent_admin/roles/permissions' => 'rolePermissions',
    '/agent_admin/permissions/create' => 'permissionCreate',
    '/agent_admin/permissions/update' => 'permissionUpdate',
    '/agent_admin/permissions/status' => 'permissionStatus',
    '/agent_admin/menus/create' => 'menuCreate',
    '/agent_admin/menus/update' => 'menuUpdate',
    '/agent_admin/menus/status' => 'menuStatus',
] as $path => $action) {
    Router::post($path, 'App\\Controller\\AgentAdminManagementWriteController@' . $action, ['middleware' => $adminWriteMiddleware]);
}
Router::get('/agent_admin/password', 'App\Controller\AgentAdminPasswordController@page', [
    'middleware' => [AdminAuthMiddleware::class, AdminPasswordChangeMiddleware::class],
]);
Router::post('/agent_admin/password', 'App\Controller\AgentAdminPasswordController@change', [
    'middleware' => [AdminAuthMiddleware::class, AdminPasswordChangeMiddleware::class],
]);
Router::post('/agent_admin/logout', 'App\Controller\AgentAdminAuthController@logout', [
    'middleware' => [AdminAuthMiddleware::class, AdminPasswordChangeMiddleware::class],
]);
Router::get('/demo/concurrent', 'App\Controller\DemoConcurrentController@index');
Router::post('/auth/login', 'App\Controller\AuthController@login');
Router::post('/auth/logout', 'App\Controller\AuthController@logout');
Router::addRoute(['GET', 'POST'], '/auth/register-random', 'App\Controller\AuthController@registerRandom');
Router::get('/portal/login', 'App\Controller\UserPortalController@loginPage');
Router::post('/portal/login', 'App\Controller\UserPortalController@login');
foreach (['/workspace' => 'workspace', '/workspace/buyer' => 'buyer', '/workspace/supplier' => 'supplier'] as $path => $action) {
    Router::get($path, 'App\\Controller\\UserPortalController@' . $action, ['middleware' => [UserAuthMiddleware::class]]);
}
foreach (['/workspace/supplier/apply' => 'supplierApply', '/workspace/supplier/update' => 'supplierUpdate', '/portal/logout' => 'logout'] as $path => $action) {
    Router::post($path, 'App\\Controller\\UserPortalController@' . $action, ['middleware' => [UserAuthMiddleware::class]]);
}
foreach (['/workspace/supplier/apis' => 'supplierProducts', '/workspace/supplier/apis/edit' => 'supplierEditor', '/market' => 'market', '/market/detail' => 'detail'] as $path => $action) {
    Router::get($path, 'App\\Controller\\CatalogController@' . $action, ['middleware' => [UserAuthMiddleware::class]]);
}
foreach (['/workspace/supplier/apis/create' => 'create', '/workspace/supplier/apis/save' => 'save', '/workspace/supplier/apis/publish' => 'publish', '/workspace/supplier/apis/unlist' => 'unlist', '/workspace/supplier/apis/next-version' => 'nextVersion'] as $path => $action) {
    Router::post($path, 'App\\Controller\\CatalogController@' . $action, ['middleware' => [UserAuthMiddleware::class]]);
}

Router::get('/favicon.ico', function () {
    return '';
});
