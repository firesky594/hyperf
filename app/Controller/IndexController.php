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
    public function __construct(private AgentAdminResponseFactory $responses)
    {
    }

    public function index(): ResponseInterface
    {
        return $this->responses->redirect('/agent_admin/login');
    }
}
