<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\RedisLock;
use App\Service\RedisLockHandle;
use Hyperf\Redis\Redis;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class RedisLockTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAcquireReturnsHandleWhenRedisSetSucceeds(): void
    {
        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('set')
            ->once()
            ->with('lock:key', Mockery::type('string'), ['nx', 'ex' => 10])
            ->andReturn(true);

        $handle = (new RedisLock($redis))->acquire('lock:key', 10);

        self::assertInstanceOf(RedisLockHandle::class, $handle);
        self::assertSame('lock:key', $handle->key);
        self::assertNotSame('', $handle->value);
    }

    public function testAcquireReturnsNullWhenRedisSetFails(): void
    {
        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('set')
            ->once()
            ->with('lock:key', Mockery::type('string'), ['nx', 'ex' => 10])
            ->andReturn(false);

        $handle = (new RedisLock($redis))->acquire('lock:key', 10);

        self::assertNull($handle);
    }

    public function testReleaseDeletesOnlyMatchingLockValue(): void
    {
        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('eval')
            ->once()
            ->with(
                Mockery::on(static fn (string $script): bool => str_contains($script, 'redis.call("get", KEYS[1])')),
                ['lock:key', 'lock-value'],
                1
            )
            ->andReturn(1);

        $released = (new RedisLock($redis))->release(new RedisLockHandle('lock:key', 'lock-value'));

        self::assertTrue($released);
    }

    public function testReleaseReturnsFalseWhenLockValueDoesNotMatch(): void
    {
        $redis = Mockery::mock(Redis::class);
        $redis->shouldReceive('eval')
            ->once()
            ->with(Mockery::type('string'), ['lock:key', 'lock-value'], 1)
            ->andReturn(0);

        $released = (new RedisLock($redis))->release(new RedisLockHandle('lock:key', 'lock-value'));

        self::assertFalse($released);
    }
}
