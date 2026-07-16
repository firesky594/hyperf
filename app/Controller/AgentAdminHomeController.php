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
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

/** 渲染管理员登录后的后台首页。 */
class AgentAdminHomeController extends AbstractController
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AgentAdminPageRenderer $pages 注入的 AgentAdminPageRenderer 依赖。
     * @param AgentAdminResponseFactory $responses 注入的 AgentAdminResponseFactory 依赖。
     * @return void 无返回值。
     */
    public function __construct(
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

    /**
     * 处理当前模块的默认入口请求。
     *
     * @return ResponseInterface 当前请求对应的 HTTP 响应。
     */
    public function index(): ResponseInterface
    {
        $session = $this->request->getAttribute('admin_session');

        return $this->responses->html(
            $this->pages->home(is_array($session) ? $session : [])
        );
    }
}
