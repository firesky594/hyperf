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
     * @param null|callable():int $timeProvider
     */
    public function __construct(
        private ?int $workerId = null,
        private mixed $timeProvider = null
    ) {
        $this->workerId = $workerId ?? (int) env('SNOWFLAKE_WORKER_ID', 1);

        if ($this->workerId < 0 || $this->workerId > self::MAX_WORKER_ID) {
            throw new InvalidArgumentException(sprintf('Snowflake worker id must be between 0 and %d.', self::MAX_WORKER_ID));
        }
    }

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

    private function currentTimeMillis(): int
    {
        if (is_callable($this->timeProvider)) {
            return (int) ($this->timeProvider)();
        }

        return (int) floor(microtime(true) * 1000);
    }

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
