<?php

declare(strict_types=1);

namespace App\Service;

/** 保存一次 Redis 锁的键和所有权令牌，供安全释放时校验。 */
class RedisLockHandle
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param string $key 缓存、锁或凭据键。
     * @param string $value 待写入或校验的值。
     * @return void 无返回值。
     */
    public function __construct(
        public readonly string $key,
        public readonly string $value
    ) {
    }
}
