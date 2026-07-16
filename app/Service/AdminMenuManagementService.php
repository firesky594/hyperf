<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

/** 维护后台菜单层级、路由、排序、状态及所需权限。 */
class AdminMenuManagementService
{
    /** 初始化当前组件所需的依赖。 */
    public function __construct(private Db $db, private IdGeneratorInterface $ids, private AdminAuditService $audit) {}

    /** 查询 `listMenus` 方法对应的数据或业务状态。 @return list<array<string,mixed>> */
    public function listMenus(): array
    {
        return array_map(static fn (object|array $row): array => is_object($row) ? get_object_vars($row) : $row, $this->db->select('SELECT am.`id`, am.`parent_id`, am.`name`, am.`icon`, am.`sort_order`, am.`route_path`, am.`permission_id`, ap.`code` AS `permission_code`, am.`status`, am.`created_at`, am.`updated_at` FROM `admin_menus` am LEFT JOIN `admin_permissions` ap ON ap.`id` = am.`permission_id` AND ap.`deleted_at` IS NULL WHERE am.`deleted_at` IS NULL ORDER BY COALESCE(am.`parent_id`, 0), am.`sort_order`, am.`id`'));
    }

    /** 创建 `createMenu` 方法对应的数据或业务状态。 @param array<string,mixed> $context */
    public function createMenu(?int $parentId, string $name, string $icon, int $sortOrder, string $routePath, ?int $permissionId, array $context): int
    {
        [$name, $icon, $routePath] = $this->fields($name, $icon, $routePath);
        return $this->db->transaction(function (ConnectionInterface $connection) use ($parentId, $name, $icon, $sortOrder, $routePath, $permissionId, $context): int {
            $this->validateReferences($connection, $parentId, $permissionId, null);
            $id = $this->ids->generate();
            $connection->insert('INSERT INTO `admin_menus` (`id`, `parent_id`, `name`, `icon`, `sort_order`, `route_path`, `permission_id`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)', [$id, $parentId, $name, $icon, $sortOrder, $routePath, $permissionId]);
            $this->audit->append($connection, $this->event($context, 'menu.create', $id, compact('parentId', 'name', 'icon', 'sortOrder', 'routePath', 'permissionId')));
            return $id;
        });
    }

    /** 更新 `updateMenu` 方法对应的数据或业务状态。 @param array<string,mixed> $context */
    public function updateMenu(int $id, ?int $parentId, string $name, string $icon, int $sortOrder, string $routePath, ?int $permissionId, array $context): void
    {
        [$name, $icon, $routePath] = $this->fields($name, $icon, $routePath);
        $this->db->transaction(function (ConnectionInterface $connection) use ($id, $parentId, $name, $icon, $sortOrder, $routePath, $permissionId, $context): void {
            $this->lockedMenu($connection, $id); $this->validateReferences($connection, $parentId, $permissionId, $id);
            if ($connection->update('UPDATE `admin_menus` SET `parent_id` = ?, `name` = ?, `icon` = ?, `sort_order` = ?, `route_path` = ?, `permission_id` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `deleted_at` IS NULL', [$parentId, $name, $icon, $sortOrder, $routePath, $permissionId, $id]) !== 1) { throw AdminAuthException::unavailable('Unable to update menu.'); }
            $this->audit->append($connection, $this->event($context, 'menu.update', $id, compact('parentId', 'name', 'icon', 'sortOrder', 'routePath', 'permissionId')));
        });
    }

    /** 设置 `setStatus` 方法对应的数据或业务状态。 @param array<string,mixed> $context */
    public function setStatus(int $id, bool $enabled, array $context): void
    {
        $this->db->transaction(function (ConnectionInterface $connection) use ($id, $enabled, $context): void {
            $this->lockedMenu($connection, $id); $status = $enabled ? 1 : 0;
            if ($connection->update('UPDATE `admin_menus` SET `status` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `deleted_at` IS NULL', [$status, $id]) !== 1) { throw AdminAuthException::unavailable('Unable to update menu status.'); }
            $this->audit->append($connection, $this->event($context, 'menu.status', $id, ['status' => $status]));
        });
    }

    /** 执行 `lockedMenu` 方法对应的业务处理。 */
    private function lockedMenu(ConnectionInterface $connection, int $id): object
    {
        $row = $connection->selectOne('SELECT `id` FROM `admin_menus` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$id]);
        if (! is_object($row)) { throw AdminAuthException::validation('Menu does not exist.'); } return $row;
    }

    /** 校验 `validateReferences` 方法对应的数据或业务状态。 */
    private function validateReferences(ConnectionInterface $connection, ?int $parentId, ?int $permissionId, ?int $selfId): void
    {
        if ($parentId !== null) {
            if ($parentId <= 0 || $parentId === $selfId || ! is_object($connection->selectOne('SELECT `id` FROM `admin_menus` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$parentId]))) { throw AdminAuthException::validation('Menu parent is invalid.'); }
        }
        if ($permissionId !== null && ($permissionId <= 0 || ! is_object($connection->selectOne('SELECT `id` FROM `admin_permissions` WHERE `id` = ? AND `status` = 1 AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$permissionId])))) { throw AdminAuthException::validation('Menu permission is invalid.'); }
    }

    /** 执行 `fields` 方法对应的业务处理。 @return array{string,string,string} */
    private function fields(string $name, string $icon, string $routePath): array
    {
        $name = trim($name); $icon = trim($icon); $routePath = trim($routePath);
        if ($name === '' || mb_strlen($name) > 64 || mb_strlen($icon) > 64 || mb_strlen($routePath) > 255 || ($routePath !== '' && ($routePath[0] !== '/' || str_starts_with($routePath, '//')))) { throw AdminAuthException::validation('Menu fields are invalid.'); }
        return [$name, $icon, $routePath];
    }

    /** 执行 `event` 方法对应的业务处理。 @param array<string,mixed> $context @param array<string,mixed> $data @return array<string,mixed> */
    private function event(array $context, string $action, int $id, array $data): array { return $context + ['action' => $action, 'target_type' => 'admin_menu', 'target_id' => $id, 'request_data' => $data, 'result' => 'success', 'http_status' => 200]; }
}
