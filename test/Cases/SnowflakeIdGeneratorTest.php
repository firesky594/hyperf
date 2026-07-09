<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Support\SnowflakeIdGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
class SnowflakeIdGeneratorTest extends TestCase
{
    public function testGenerateReturnsIncreasingUniqueIdsInSameMillisecond(): void
    {
        $now = 1720000000000;
        $generator = new SnowflakeIdGenerator(1, static fn () => $now);

        $first = $generator->generate();
        $second = $generator->generate();
        $third = $generator->generate();

        self::assertIsInt($first);
        self::assertGreaterThan($first, $second);
        self::assertGreaterThan($second, $third);
        self::assertSame(3, count(array_unique([$first, $second, $third])));
    }

    public function testGenerateUsesLaterTimestampForLargerIds(): void
    {
        $times = [1720000000000, 1720000000001];
        $generator = new SnowflakeIdGenerator(2, static function () use (&$times): int {
            return array_shift($times);
        });

        $first = $generator->generate();
        $second = $generator->generate();

        self::assertGreaterThan($first, $second);
    }

    public function testGenerateThrowsWhenClockMovesBackwards(): void
    {
        $times = [1720000000001, 1720000000000];
        $generator = new SnowflakeIdGenerator(3, static function () use (&$times): int {
            return array_shift($times);
        });

        $generator->generate();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Clock moved backwards');

        $generator->generate();
    }
}
