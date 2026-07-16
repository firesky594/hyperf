<?php

declare(strict_types=1);

namespace App\Rbac;

/** 描述一条后台路由及其对应的系统权限编码和名称。 */
final class AdminRouteDefinition
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param string $method HTTP 请求方法。
     * @param string $path 请求路径。
     * @param string $handler 后续请求处理器。
     * @param ?string $permissionCode 传入的 ?string 实例，用于初始化当前组件所需的依赖。
     * @param ?string $permissionName 传入的 ?string 实例，用于初始化当前组件所需的依赖。
     * @return void 无返回值。
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $handler,
        public readonly ?string $permissionCode,
        public readonly ?string $permissionName = null
    ) {
    }
}
