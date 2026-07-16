<?php

declare(strict_types=1);

namespace App\Support;

use Hyperf\Contract\IdGeneratorInterface;
use InvalidArgumentException;
use RuntimeException;

use function Hyperf\Support\env;

class SnowflakeIdGenerator implements IdGeneratorInterface
{
    private const EPOCH = 1704067200000;
    private const WORKER_ID_BITS = 10;
    private const SEQUENCE_BITS = 12;
    private const MAX_WORKER_ID = (1 << self::WORKER_ID_BITS) - 1;
    private const MAX_SEQUENCE = (1 << self::SEQUENCE_BITS) - 1;

    private int $lastTimestamp = -1;

    private int $sequence = 0;

    /**
     * 初始化当前组件所需的依赖。
     * 初始化雪花 ID 生成器。
     *
     * workerId 用于区分不同实例，默认读取 SNOWFLAKE_WORKER_ID。
     * timeProvider 仅用于测试时注入固定毫秒时间，生产环境不传。
     *
     * @param null|int $workerId 工作节点 ID，取值范围 0 到 1023；为空时读取环境变量。
     * @param null|callable():int $timeProvider 毫秒时间提供器；为空时使用 microtime(true)。
     * @throws InvalidArgumentException workerId 超出可用范围时抛出。
     */
    public function __construct(
        private ?int $workerId = null,
        private mixed $timeProvider = null
    ) {
        $this->workerId = $workerId ?? (int) env('SNOWFLAKE_WORKER_ID', 1);

        if ($this->workerId < 0 || $this->workerId > self::MAX_WORKER_ID) {
            throw new InvalidArgumentException(sprintf(
                'Snowflake worker id must be between 0 and %d.',
                self::MAX_WORKER_ID
            ));
        }
    }

    /**
     * 生成 `generate` 方法对应的数据或业务状态。
     * 生成一个雪花 ID。
     *
     * ID 由毫秒时间戳、workerId 和同毫秒序列号组成。
     * 同一毫秒内连续生成时递增序列号；序列号用尽后等待下一毫秒。
     *
     * @return int 可直接写入 MySQL BIGINT UNSIGNED 的雪花 ID。
     * @throws RuntimeException 检测到系统时间回拨时抛出，避免生成重复 ID。
     */
    public function generate(): int
    {
        $timestamp = $this->currentTimeMillis();

        if ($timestamp < $this->lastTimestamp) {
            throw new RuntimeException(sprintf(
                'Clock moved backwards. Refusing to generate id for %d milliseconds.',
                $this->lastTimestamp - $timestamp
            ));
        }

        if ($timestamp === $this->lastTimestamp) {
            $this->sequence = ($this->sequence + 1) & self::MAX_SEQUENCE;
            if ($this->sequence === 0) {
                $timestamp = $this->waitUntilNextMillis($timestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        return (($timestamp - self::EPOCH) << (self::WORKER_ID_BITS + self::SEQUENCE_BITS))
            | ($this->workerId << self::SEQUENCE_BITS)
            | $this->sequence;
    }

    /**
     * 执行 `currentTimeMillis` 方法对应的业务处理。
     * 获取当前毫秒时间。
     *
     * 测试环境可以通过 timeProvider 控制返回值；生产环境使用系统时间。
     *
     * @return int 当前毫秒时间戳。
     */
    private function currentTimeMillis(): int
    {
        if (is_callable($this->timeProvider)) {
            return (int) ($this->timeProvider)();
        }

        return (int) floor(microtime(true) * 1000);
    }

    /**
     * 执行 `waitUntilNextMillis` 方法对应的业务处理。
     * 等待时间进入下一毫秒。
     *
     * 当同一毫秒内序列号耗尽时调用，确保下一个 ID 使用新的时间片。
     *
     * @param int $timestamp 当前已使用的毫秒时间戳。
     * @return int 大于传入时间戳的下一毫秒时间戳。
     */
    private function waitUntilNextMillis(int $timestamp): int
    {
        $next = $this->currentTimeMillis();
        while ($next <= $timestamp) {
            usleep(1000);
            $next = $this->currentTimeMillis();
        }

        return $next;
    }
}
