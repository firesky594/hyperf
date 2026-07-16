<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

/** 维护后台自定义权限及系统权限的可编辑属性。 */
class AdminPermissionManagementService
{
    /** 初始化当前组件所需的依赖。 */
    public function __construct(private Db $db, private IdGeneratorInterface $ids, private AdminAuditService $audit) {}

    /** 查询 `listPermissions` 方法对应的数据或业务状态。 @return list<array<string,mixed>> */
    public function listPermissions(): array
    {
        return array_map(static fn (object|array $row): array => is_object($row) ? get_object_vars($row) : $row,
            $this->db->select('SELECT `id`, `name`, `code`, `source`, `route_method`, `route_path`, `description`, `status`, `created_at`, `updated_at` FROM `admin_permissions` WHERE `deleted_at` IS NULL ORDER BY `source` DESC, `code` ASC'));
    }

    /** 创建 `createCustom` 方法对应的数据或业务状态。 @param array<string,mixed> $context */
    public function createCustom(string $name, string $code, string $description, array $context): int
    {
        [$name, $code, $description] = $this->fields($name, $code, $description);
        return $this->db->transaction(function (ConnectionInterface $connection) use ($name, $code, $description, $context): int {
            if ($connection->selectOne('SELECT `id` FROM `admin_permissions` WHERE `code` = ? LIMIT 1 FOR UPDATE', [$code]) !== null) {
                throw AdminAuthException::validation('Permission code already exists.');
            }
            $id = $this->ids->generate();
            $connection->insert('INSERT INTO `admin_permissions` (`id`, `name`, `code`, `source`, `route_method`, `route_path`, `description`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (?, ?, ?, \'custom\', NULL, NULL, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)', [$id, $name, $code, $description]);
            $this->audit->append($connection, $this->event($context, 'permission.create', $id, ['name' => $name, 'code' => $code, 'description' => $description]));
            return $id;
        });
    }

    /** 更新 `updateCustom` 方法对应的数据或业务状态。 @param array<string,mixed> $context */
    public function updateCustom(int $id, string $name, string $code, string $description, array $context): void
    {
        [$name, $code, $description] = $this->fields($name, $code, $description);
        $this->db->transaction(function (ConnectionInterface $connection) use ($id, $name, $code, $description, $context): void {
            $this->lockedCustom($connection, $id);
            if ($connection->selectOne('SELECT `id` FROM `admin_permissions` WHERE `code` = ? AND `id` <> ? LIMIT 1 FOR UPDATE', [$code, $id]) !== null) {
                throw AdminAuthException::validation('Permission code already exists.');
            }
            if ($connection->update('UPDATE `admin_permissions` SET `name` = ?, `code` = ?, `description` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `source` = \'custom\' AND `deleted_at` IS NULL', [$name, $code, $description, $id]) !== 1) {
                throw AdminAuthException::unavailable('Unable to update permission.');
            }
            $this->audit->append($connection, $this->event($context, 'permission.update', $id, ['name' => $name, 'code' => $code, 'description' => $description]));
        });
    }

    /** 设置 `setStatus` 方法对应的数据或业务状态。 @param array<string,mixed> $context */
    public function setStatus(int $id, bool $enabled, array $context): void
    {
        $this->db->transaction(function (ConnectionInterface $connection) use ($id, $enabled, $context): void {
            $this->lockedCustom($connection, $id);
            $status = $enabled ? 1 : 0;
            if ($connection->update('UPDATE `admin_permissions` SET `status` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `source` = \'custom\' AND `deleted_at` IS NULL', [$status, $id]) !== 1) {
                throw AdminAuthException::unavailable('Unable to update permission status.');
            }
            $this->audit->append($connection, $this->event($context, 'permission.status', $id, ['status' => $status]));
        });
    }

    /** 执行 `lockedCustom` 方法对应的业务处理。 */
    private function lockedCustom(ConnectionInterface $connection, int $id): object
    {
        if ($id <= 0) { throw AdminAuthException::validation(); }
        $row = $connection->selectOne('SELECT `id`, `source` FROM `admin_permissions` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$id]);
        if (! is_object($row)) { throw AdminAuthException::validation('Permission does not exist.'); }
        if ((string) $row->source !== 'custom') { throw AdminAuthException::validation('System permissions are managed by route synchronization.'); }
        return $row;
    }

    /** 执行 `fields` 方法对应的业务处理。 @return array{string,string,string} */
    private function fields(string $name, string $code, string $description): array
    {
        $name = trim($name); $code = trim($code); $description = trim($description);
        if ($name === '' || mb_strlen($name) > 96 || preg_match('/^[a-z][a-z0-9._-]{2,127}$/D', $code) !== 1 || mb_strlen($description) > 255) { throw AdminAuthException::validation('Permission fields are invalid.'); }
        return [$name, $code, $description];
    }

    /** 执行 `event` 方法对应的业务处理。 @param array<string,mixed> $context @param array<string,mixed> $data @return array<string,mixed> */
    private function event(array $context, string $action, int $id, array $data): array
    { return $context + ['action' => $action, 'target_type' => 'admin_permission', 'target_id' => $id, 'request_data' => $data, 'result' => 'success', 'http_status' => 200]; }
}
