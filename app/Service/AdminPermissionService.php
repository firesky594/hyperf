<?php

declare(strict_types=1);

namespace App\Service;

use App\Rbac\AdminRouteDefinition;
use App\Rbac\AdminRouteRegistry;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

/** 将代码内权限定义同步到数据库并保护系统权限边界。 */
class AdminPermissionService
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @param AdminRouteRegistry $routes 注入的 AdminRouteRegistry 依赖。
     * @param IdGeneratorInterface $ids 注入的 IdGeneratorInterface 依赖。
     * @return void 无返回值。
     */
    public function __construct(
        private Db $db,
        private AdminRouteRegistry $routes,
        private IdGeneratorInterface $ids
    ) {
    }

    /**
     * 同步system权限列表。
     *
     * @return array{created: 返回syncSystem权限列表结构化数据。
     */
    public function syncSystemPermissions(): array
    {
        $definitions = [];
        foreach ($this->routes->definitions() as $definition) {
            if ($definition->permissionCode !== null) {
                $definitions[$definition->permissionCode] = $definition;
            }
        }

        return $this->db->transaction(function (ConnectionInterface $connection) use ($definitions): array {
            $existing = [];
            foreach ($connection->select(
                'SELECT `code`, `source`, `status`, `deleted_at` FROM `admin_permissions`'
            ) as $row) {
                $existing[(string) $row->code] = $row;
            }

            $result = ['created' => 0, 'restored' => 0, 'disabled' => 0, 'skipped_custom' => 0];
            foreach ($definitions as $code => $definition) {
                $row = $existing[$code] ?? null;
                if ($row !== null && (string) $row->source === 'custom') {
                    ++$result['skipped_custom'];
                    continue;
                }

                if ($row === null) {
                    $this->insertSystemPermission($connection, $definition);
                    ++$result['created'];
                    continue;
                }

                if ((int) $row->status !== 1 || $row->deleted_at !== null) {
                    $connection->update(
                        'UPDATE `admin_permissions` SET `name` = ?, `route_method` = ?, `route_path` = ?, '
                        . '`status` = 1, `deleted_at` = NULL, `updated_at` = CURRENT_TIMESTAMP WHERE `code` = ? AND `source` = \'system\'',
                        [$definition->permissionName ?? $code, $definition->method, $definition->path, $code]
                    );
                    ++$result['restored'];
                }
            }

            foreach ($existing as $code => $row) {
                if ((string) $row->source !== 'system' || isset($definitions[$code]) || (int) $row->status !== 1) {
                    continue;
                }

                $connection->update(
                    'UPDATE `admin_permissions` SET `status` = 0, `updated_at` = CURRENT_TIMESTAMP '
                    . 'WHERE `code` = ? AND `source` = \'system\'',
                    [$code]
                );
                ++$result['disabled'];
            }

            return $result;
        });
    }

    /**
     * 处理insertSystem权限。
     *
     * @param ConnectionInterface $connection 传入的 ConnectionInterface 实例，用于处理insertSystem权限。
     * @param AdminRouteDefinition $definition 传入的 AdminRouteDefinition 实例，用于处理insertSystem权限。
     * @return void 无返回值。
     */
    private function insertSystemPermission(ConnectionInterface $connection, AdminRouteDefinition $definition): void
    {
        $connection->insert(
            'INSERT INTO `admin_permissions` (`id`, `name`, `code`, `source`, `route_method`, `route_path`, '
            . '`description`, `status`, `created_at`, `updated_at`, `deleted_at`) '
            . 'VALUES (?, ?, ?, \'system\', ?, ?, \'\', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)',
            [
                $this->ids->generate(),
                $definition->permissionName ?? $definition->permissionCode,
                $definition->permissionCode,
                $definition->method,
                $definition->path,
            ]
        );
    }
}
