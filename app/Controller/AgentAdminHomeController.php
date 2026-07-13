<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\AgentAdminResponseFactory;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

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
