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
    public function __construct(
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

    public function index(): ResponseInterface
    {
        $session = $this->request->getAttribute('admin_session');

        return $this->responses->html(
            $this->pages->home(is_array($session) ? $session : [])
        );
    }
}
