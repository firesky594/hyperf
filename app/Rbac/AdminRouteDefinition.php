<?php

declare(strict_types=1);

namespace App\Rbac;

/** 描述一条后台路由及其对应的系统权限编码和名称。 */
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
