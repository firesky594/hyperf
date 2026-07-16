<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DemoConcurrentService;

/** 展示 MySQL 与 Redis 并发访问结果的本地运行环境诊断接口。 */
class DemoConcurrentController extends AbstractController
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param DemoConcurrentService $service 注入的 DemoConcurrentService 依赖。
     * @return void 无返回值。
     */
    public function __construct(private DemoConcurrentService $service)
    {
    }

    /**
     * 处理当前模块的默认入口请求。
     *
     * @return array 返回index结构化数据。
     */
    public function index(): array
    {
        return $this->service->run();
    }
}
