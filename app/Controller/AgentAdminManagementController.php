<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\AgentAdminResponseFactory;
use App\View\AgentAdminPageRenderer;
use Psr\Http\Message\ResponseInterface;

final class AgentAdminManagementController extends AbstractController
{
    public function __construct(
        private AgentAdminPageRenderer $pages,
        private AgentAdminResponseFactory $responses
    ) {
    }

    public function administrators(): ResponseInterface
    {
        return $this->page('administrators');
    }

    public function roles(): ResponseInterface
    {
        return $this->page('roles');
    }

    public function permissions(): ResponseInterface
    {
        return $this->page('permissions');
    }

    public function menus(): ResponseInterface
    {
        return $this->page('menus');
    }

    public function audit(): ResponseInterface
    {
        return $this->page('audit');
    }

    private function page(string $module): ResponseInterface
    {
        $session = $this->request->getAttribute('admin_session');

        return $this->responses->html($this->pages->management(
            $module,
            is_array($session) ? $session : []
        ));
    }
}
