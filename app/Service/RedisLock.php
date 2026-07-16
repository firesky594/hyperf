<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Redis\Redis;
use InvalidArgumentException;

/** 基于 Redis NX 锁和随机令牌提供安全的分布式互斥锁。 */
class RedisLock
{
    private const RELEASE_SCRIPT = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
end
return 0
LUA;

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Redis $redis Redis 客户端实例。
     * @return void 无返回值。
     */
    public function __construct(private Redis $redis)
    {
    }

    /**
     * 尝试获取分布式锁并返回锁句柄。
     *
     * @param string $key 缓存、锁或凭据键。
     * @param int $ttl 数据或锁的有效秒数。
     * @return ?RedisLockHandle 查询成功时返回对应数据，不存在时返回 null。
     * @throws \InvalidArgumentException 传入参数不符合约束时抛出。
     */
    public function acquire(string $key, int $ttl): ?RedisLockHandle
    {
        if ($ttl <= 0) {
            throw new InvalidArgumentException('Lock ttl must be greater than zero.');
        }

        $value = bin2hex(random_bytes(16));
        $result = $this->redis->set($key, $value, ['nx', 'ex' => $ttl]);

        if ($result !== true && $result !== 'OK') {
            return null;
        }

        return new RedisLockHandle($key, $value);
    }

    /**
     * 校验所有权令牌并释放分布式锁。
     *
     * @param RedisLockHandle $handle 传入的 RedisLockHandle 实例，用于处理release。
     * @return bool 条件满足时返回 true，否则返回 false。
     */
    public function release(RedisLockHandle $handle): bool
    {
        return (int) $this->redis->eval(self::RELEASE_SCRIPT, [$handle->key, $handle->value], 1) === 1;
    }
}
