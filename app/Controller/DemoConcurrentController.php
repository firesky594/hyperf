<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DemoConcurrentService;

/** 展示 MySQL 与 Redis 并发访问结果的本地运行环境诊断接口。 */
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
