<?php

declare(strict_types=1);

namespace App\Service;

/** 保存一次 Redis 锁的键和所有权令牌，供安全释放时校验。 */
class RedisLockHandle
{
    public function __construct(
        public readonly string $key,
        public readonly string $value
    ) {
    }
}
