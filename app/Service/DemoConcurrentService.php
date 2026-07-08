<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Coroutine\Parallel;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use RuntimeException;
use Throwable;

class DemoConcurrentService
{
    private const REDIS_KEY = 'demo:concurrent:counter';

    public function __construct(
        private Db $db,
        private Redis $redis
    ) {
    }

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

    private function elapsedMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
