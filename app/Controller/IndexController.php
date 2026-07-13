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
