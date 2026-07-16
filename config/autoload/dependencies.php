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
// 项目主键统一由雪花 ID 生成器提供，避免依赖数据库自增序列。
return [
    Hyperf\Contract\IdGeneratorInterface::class => App\Support\SnowflakeIdGenerator::class,
];
