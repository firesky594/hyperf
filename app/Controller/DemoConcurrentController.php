<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DemoConcurrentService;

class DemoConcurrentController extends AbstractController
{
    public function __construct(private DemoConcurrentService $service)
    {
    }

    public function index(): array
    {
        return $this->service->run();
    }
}
