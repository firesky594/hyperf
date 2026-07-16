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

namespace App\Controller;

use App\Http\AgentAdminResponseFactory;
use Psr\Http\Message\ResponseInterface;

/** 将站点根入口统一引导到后台管理员登录页。 */
class IndexController extends AbstractController
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
     * @return void 无返回值。
     */
    public function __construct(private AgentAdminResponseFactory $responses)
    {
    }

    /**
     * 处理当前模块的默认入口请求。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function index(): ResponseInterface
    {
        return $this->responses->redirect('/agent_admin/login');
    }
}
