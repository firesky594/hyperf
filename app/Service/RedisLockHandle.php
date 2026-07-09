<?php

declare(strict_types=1);

namespace App\Service;

class RedisLockHandle
{
    public function __construct(
        public readonly string $key,
        public readonly string $value
    ) {
    }
}
