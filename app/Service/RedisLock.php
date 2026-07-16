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

    public function __construct(private Redis $redis)
    {
    }

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

    public function release(RedisLockHandle $handle): bool
    {
        return (int) $this->redis->eval(self::RELEASE_SCRIPT, [$handle->key, $handle->value], 1) === 1;
    }
}
