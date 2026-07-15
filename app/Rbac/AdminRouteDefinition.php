<?php

declare(strict_types=1);

namespace App\Rbac;

final class AdminRouteDefinition
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $handler,
        public readonly ?string $permissionCode,
        public readonly ?string $permissionName = null
    ) {
    }
}
