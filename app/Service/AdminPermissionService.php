<?php

declare(strict_types=1);

namespace App\Service;

use App\Rbac\AdminRouteDefinition;
use App\Rbac\AdminRouteRegistry;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

class AdminPermissionService
{
    public function __construct(
        private Db $db,
        private AdminRouteRegistry $routes,
        private IdGeneratorInterface $ids
    ) {
    }

    /**
     * @return array{created: int, restored: int, disabled: int, skipped_custom: int}
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
