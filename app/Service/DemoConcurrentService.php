<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Coroutine\Parallel;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use RuntimeException;
use Throwable;

/** 并发探测 MySQL 与 Redis，用于验证本地运行环境连接状态。 */
class DemoConcurrentService
{
    private const REDIS_KEY = 'demo:concurrent:counter';

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @param Redis $redis Redis 客户端实例。
     * @return void 无返回值。
     */
    public function __construct(
        private Db $db,
        private Redis $redis
    ) {
    }

    /**
     * 执行当前服务的核心流程。
     *
     * @return array 返回run结构化数据。
     */
    public function run(): array
    {
        $startedAt = microtime(true);
        $parallel = new Parallel();

        $parallel->add(fn () => $this->measure('mysql', fn () => $this->checkMysql()), 'mysql');
        $parallel->add(fn () => $this->measure('redis', fn () => $this->checkRedis()), 'redis');

        $tasks = $parallel->wait();

        return [
            'ok' => ($tasks['mysql']['ok'] ?? false) && ($tasks['redis']['ok'] ?? false),
            'elapsed_ms' => $this->elapsedMs($startedAt),
            'tasks' => $tasks,
        ];
    }

    /**
     * 检查mysql。
     *
     * @return array 返回checkMysql结构化数据。
     */
    private function checkMysql(): array
    {
        $rows = $this->db->select('SELECT 1 AS health_check');
        $first = $rows[0] ?? [];

        if (is_object($first)) {
            $first = get_object_vars($first);
        }

        return [
            'value' => (int) ($first['health_check'] ?? 0),
        ];
    }

    /**
     * 检查redis。
     *
     * @return array 返回checkRedis结构化数据。
     * @throws \RuntimeException 运行环境或业务状态不满足要求时抛出。
     */
    private function checkRedis(): array
    {
        $value = $this->redis->incr(self::REDIS_KEY);
        if ($this->redis->expire(self::REDIS_KEY, 3600) === false) {
            throw new RuntimeException('redis expire failed');
        }

        return [
            'key' => self::REDIS_KEY,
            'value' => (int) $value,
        ];
    }

    /**
     * 处理measure。
     *
     * @param string $name 业务对象名称。
     * @param callable $callback 用于执行指定处理逻辑的回调。
     * @return array 返回measure结构化数据。
     */
    private function measure(string $name, callable $callback): array
    {
        $startedAt = microtime(true);

        try {
            $data = $callback();

            return [
                'ok' => true,
                'name' => $name,
                'elapsed_ms' => $this->elapsedMs($startedAt),
                'data' => $data,
            ];
        } catch (Throwable $throwable) {
            return [
                'ok' => false,
                'name' => $name,
                'elapsed_ms' => $this->elapsedMs($startedAt),
                'error' => [
                    'type' => $throwable::class,
                    'message' => $throwable->getMessage(),
                ],
            ];
        }
    }

    /**
     * 处理elapsedMs。
     *
     * @param float $startedAt startedAt数值。
     * @return float 返回elapsedMs处理结果。
     */
    private function elapsedMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
