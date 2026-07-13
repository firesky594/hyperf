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
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'HEAD'], '/', 'App\Controller\IndexController@index');
Router::get('/agent_admin/login', 'App\Controller\AgentAdminAuthController@loginPage');
Router::post('/agent_admin/login', 'App\Controller\AgentAdminAuthController@login');
Router::get('/agent_admin', 'App\Controller\AgentAdminHomeController@index', [
    'middleware' => [App\Middleware\AdminAuthMiddleware::class],
]);
Router::post('/agent_admin/logout', 'App\Controller\AgentAdminAuthController@logout', [
    'middleware' => [App\Middleware\AdminAuthMiddleware::class],
]);
Router::get('/demo/concurrent', 'App\Controller\DemoConcurrentController@index');
Router::post('/auth/login', 'App\Controller\AuthController@login');
Router::post('/auth/logout', 'App\Controller\AuthController@logout');
Router::addRoute(['GET', 'POST'], '/auth/register-random', 'App\Controller\AuthController@registerRandom');

Router::get('/favicon.ico', function () {
    return '';
});
