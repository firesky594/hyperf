<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\DemoConcurrentService;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Hyperf\Testing\TestCase;
use Mockery;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
class DemoConcurrentServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testRunReturnsSuccessfulMysqlAndRedisResults(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);

        $db->shouldReceive('select')
            ->once()
            ->with('SELECT 1 AS health_check')
            ->andReturn([(object) ['health_check' => 1]]);

        $redis->shouldReceive('incr')
            ->once()
            ->with('demo:concurrent:counter')
            ->andReturn(7);

        $redis->shouldReceive('expire')
            ->once()
            ->with('demo:concurrent:counter', 3600)
            ->andReturn(true);

        $result = (new DemoConcurrentService($db, $redis))->run();

        self::assertTrue($result['ok']);
        self::assertIsFloat($result['elapsed_ms']);
        self::assertTrue($result['tasks']['mysql']['ok']);
        self::assertSame(1, $result['tasks']['mysql']['data']['value']);
        self::assertTrue($result['tasks']['redis']['ok']);
        self::assertSame('demo:concurrent:counter', $result['tasks']['redis']['data']['key']);
        self::assertSame(7, $result['tasks']['redis']['data']['value']);
    }

    public function testRunReturnsTaskErrorWithoutThrowing(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);

        $db->shouldReceive('select')
            ->once()
            ->with('SELECT 1 AS health_check')
            ->andThrow(new RuntimeException('mysql unavailable'));

        $redis->shouldReceive('incr')
            ->once()
            ->with('demo:concurrent:counter')
            ->andReturn(8);

        $redis->shouldReceive('expire')
            ->once()
            ->with('demo:concurrent:counter', 3600)
            ->andReturn(true);

        $result = (new DemoConcurrentService($db, $redis))->run();

        self::assertFalse($result['ok']);
        self::assertFalse($result['tasks']['mysql']['ok']);
        self::assertSame(RuntimeException::class, $result['tasks']['mysql']['error']['type']);
        self::assertSame('mysql unavailable', $result['tasks']['mysql']['error']['message']);
        self::assertTrue($result['tasks']['redis']['ok']);
        self::assertSame(8, $result['tasks']['redis']['data']['value']);
    }

    public function testRunMarksRedisTaskAsFailedWhenExpireReturnsFalse(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);

        $db->shouldReceive('select')
            ->once()
            ->with('SELECT 1 AS health_check')
            ->andReturn([(object) ['health_check' => 1]]);

        $redis->shouldReceive('incr')
            ->once()
            ->with('demo:concurrent:counter')
            ->andReturn(9);

        $redis->shouldReceive('expire')
            ->once()
            ->with('demo:concurrent:counter', 3600)
            ->andReturn(false);

        $result = (new DemoConcurrentService($db, $redis))->run();

        self::assertFalse($result['ok']);
        self::assertTrue($result['tasks']['mysql']['ok']);
        self::assertSame(1, $result['tasks']['mysql']['data']['value']);
        self::assertFalse($result['tasks']['redis']['ok']);
        self::assertSame(RuntimeException::class, $result['tasks']['redis']['error']['type']);
        self::assertSame('redis expire failed', $result['tasks']['redis']['error']['message']);
    }
}
