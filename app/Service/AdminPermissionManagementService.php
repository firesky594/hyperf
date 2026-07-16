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
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @param IdGeneratorInterface $ids 注入的 IdGeneratorInterface 依赖。
     * @param AdminAuditService $audit 注入的 AdminAuditService 依赖。
     * @return void 无返回值。
     */
    public function __construct(private Db $db, private IdGeneratorInterface $ids, private AdminAuditService $audit) {}

    /**
     * 查询权限列表。
     *
     * @return list<array<string,mixed>> 返回list权限列表结构化数据。
     */
    public function listPermissions(): array
    {
        return array_map(static fn (object|array $row): array => is_object($row) ? get_object_vars($row) : $row,
            $this->db->select('SELECT `id`, `name`, `code`, `source`, `route_method`, `route_path`, `description`, `status`, `created_at`, `updated_at` FROM `admin_permissions` WHERE `deleted_at` IS NULL ORDER BY `source` DESC, `code` ASC'));
    }

    /**
     * 创建custom。
     *
     * @param string $name 业务对象名称。
     * @param string $code code字符串。
     * @param string $description description字符串。
     * @param array<string,mixed> $context 当前操作的审计上下文。
     * @return int 返回createCustom整数结果。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
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

    /**
     * 更新custom。
     *
     * @param int $id 标识数值。
     * @param string $name 业务对象名称。
     * @param string $code code字符串。
     * @param string $description description字符串。
     * @param array<string,mixed> $context 当前操作的审计上下文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
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

    /**
     * 设置状态。
     *
     * @param int $id 标识数值。
     * @param bool $enabled 控制enabled行为的布尔标记。
     * @param array<string,mixed> $context 当前操作的审计上下文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
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

    /**
     * 处理lockedCustom。
     *
     * @param ConnectionInterface $connection 传入的 ConnectionInterface 实例，用于处理lockedCustom。
     * @param int $id 标识数值。
     * @return object 返回lockedCustom处理结果。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function lockedCustom(ConnectionInterface $connection, int $id): object
    {
        if ($id <= 0) { throw AdminAuthException::validation(); }
        $row = $connection->selectOne('SELECT `id`, `source` FROM `admin_permissions` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE', [$id]);
        if (! is_object($row)) { throw AdminAuthException::validation('Permission does not exist.'); }
        if ((string) $row->source !== 'custom') { throw AdminAuthException::validation('System permissions are managed by route synchronization.'); }
        return $row;
    }

    /**
     * 处理字段。
     *
     * @param string $name 业务对象名称。
     * @param string $code code字符串。
     * @param string $description description字符串。
     * @return array{string,string,string} 返回字段结构化数据。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function fields(string $name, string $code, string $description): array
    {
        $name = trim($name); $code = trim($code); $description = trim($description);
        if ($name === '' || mb_strlen($name) > 96 || preg_match('/^[a-z][a-z0-9._-]{2,127}$/D', $code) !== 1 || mb_strlen($description) > 255) { throw AdminAuthException::validation('Permission fields are invalid.'); }
        return [$name, $code, $description];
    }

    /**
     * 处理事件。
     *
     * @param array<string,mixed> $context 当前操作的审计上下文。
     * @param string $action 待执行的操作标识。
     * @param int $id 标识数值。
     * @param array<string,mixed> $data 待处理的业务数据。
     * @return array<string,mixed> 返回事件结构化数据。
     */
    private function event(array $context, string $action, int $id, array $data): array
    { return $context + ['action' => $action, 'target_type' => 'admin_permission', 'target_id' => $id, 'request_data' => $data, 'result' => 'success', 'http_status' => 200]; }
}
