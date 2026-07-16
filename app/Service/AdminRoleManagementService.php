<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

class AdminRoleManagementService
{
    public function __construct(
        private Db $db,
        private IdGeneratorInterface $ids,
        private AdminAuditService $audit
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function listRoles(): array
    {
        $rows = $this->db->select(<<<'SQL'
SELECT ar.`id`, ar.`name`, ar.`code`, ar.`description`, ar.`status`, ar.`created_at`, ar.`updated_at`,
       GROUP_CONCAT(ap.`id` ORDER BY ap.`id`) AS `permission_ids`,
       GROUP_CONCAT(ap.`code` ORDER BY ap.`id`) AS `permission_codes`
FROM `admin_roles` ar
LEFT JOIN `admin_role_permissions` arp ON arp.`role_id` = ar.`id` AND arp.`deleted_at` IS NULL
LEFT JOIN `admin_permissions` ap ON ap.`id` = arp.`permission_id` AND ap.`deleted_at` IS NULL
WHERE ar.`deleted_at` IS NULL
GROUP BY ar.`id`, ar.`name`, ar.`code`, ar.`description`, ar.`status`, ar.`created_at`, ar.`updated_at`
ORDER BY ar.`created_at` ASC, ar.`id` ASC
SQL);

        return array_map(static function (object|array $row): array {
            $data = is_object($row) ? get_object_vars($row) : $row;
            $permissionIds = (string) ($data['permission_ids'] ?? '');
            $permissionCodes = (string) ($data['permission_codes'] ?? '');
            $data['permission_ids'] = $permissionIds === '' ? [] : array_map('intval', explode(',', $permissionIds));
            $data['permission_codes'] = $permissionCodes === '' ? [] : explode(',', $permissionCodes);

            return $data;
        }, $rows);
    }

    /** @param array<string,mixed> $auditContext */
    public function createRole(string $name, string $code, string $description, array $auditContext): int
    {
        [$name, $code, $description] = $this->validatedRoleFields($name, $code, $description);

        return $this->db->transaction(function (ConnectionInterface $connection) use (
            $name,
            $code,
            $description,
            $auditContext
        ): int {
            if ($connection->selectOne(
                'SELECT `id` FROM `admin_roles` WHERE `code` = ? LIMIT 1 FOR UPDATE',
                [$code]
            ) !== null) {
                throw AdminAuthException::validation('Role code already exists.');
            }
            $roleId = $this->ids->generate();
            $connection->insert(
                'INSERT INTO `admin_roles` (`id`, `name`, `code`, `description`, `status`, '
                . '`created_at`, `updated_at`, `deleted_at`) '
                . 'VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)',
                [$roleId, $name, $code, $description]
            );
            $this->audit->append($connection, $this->event(
                $auditContext,
                'role.create',
                $roleId,
                ['name' => $name, 'code' => $code, 'description' => $description]
            ));

            return $roleId;
        });
    }

    /** @param array<string,mixed> $auditContext */
    public function updateRole(
        int $roleId,
        string $name,
        string $code,
        string $description,
        array $auditContext
    ): void {
        if ($roleId <= 0) {
            throw AdminAuthException::validation();
        }
        [$name, $code, $description] = $this->validatedRoleFields($name, $code, $description);
        $this->db->transaction(function (ConnectionInterface $connection) use (
            $roleId,
            $name,
            $code,
            $description,
            $auditContext
        ): void {
            $this->lockedRole($connection, $roleId);
            if ($connection->selectOne(
                'SELECT `id` FROM `admin_roles` WHERE `code` = ? AND `id` <> ? LIMIT 1 FOR UPDATE',
                [$code, $roleId]
            ) !== null) {
                throw AdminAuthException::validation('Role code already exists.');
            }
            if ($connection->update(
                'UPDATE `admin_roles` SET `name` = ?, `code` = ?, `description` = ?, '
                . '`updated_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `deleted_at` IS NULL',
                [$name, $code, $description, $roleId]
            ) !== 1) {
                throw AdminAuthException::unavailable('Unable to update role.');
            }
            $this->audit->append($connection, $this->event(
                $auditContext,
                'role.update',
                $roleId,
                ['name' => $name, 'code' => $code, 'description' => $description]
            ));
        });
    }

    /** @param array<string,mixed> $auditContext */
    public function setStatus(int $roleId, bool $enabled, array $auditContext): void
    {
        if ($roleId <= 0) {
            throw AdminAuthException::validation();
        }
        $status = $enabled ? 1 : 0;
        $this->db->transaction(function (ConnectionInterface $connection) use ($roleId, $status, $auditContext): void {
            $this->lockedRole($connection, $roleId);
            if ($connection->update(
                'UPDATE `admin_roles` SET `status` = ?, `updated_at` = CURRENT_TIMESTAMP '
                . 'WHERE `id` = ? AND `deleted_at` IS NULL',
                [$status, $roleId]
            ) !== 1) {
                throw AdminAuthException::unavailable('Unable to update role status.');
            }
            $this->audit->append($connection, $this->event(
                $auditContext,
                'role.status',
                $roleId,
                ['status' => $status]
            ));
        });
    }

    /** @param list<int> $permissionIds @param array<string,mixed> $auditContext */
    public function assignPermissions(int $roleId, array $permissionIds, array $auditContext): void
    {
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));
        sort($permissionIds);
        if ($roleId <= 0 || in_array(0, $permissionIds, true)) {
            throw AdminAuthException::validation('Role permissions are invalid.');
        }

        $this->db->transaction(function (ConnectionInterface $connection) use (
            $roleId,
            $permissionIds,
            $auditContext
        ): void {
            $this->lockedRole($connection, $roleId);
            if ($permissionIds !== []) {
                $placeholders = implode(',', array_fill(0, count($permissionIds), '?'));
                $permissions = $connection->select(
                    'SELECT `id` FROM `admin_permissions` WHERE `id` IN (' . $placeholders . ') '
                    . 'AND `status` = 1 AND `deleted_at` IS NULL FOR UPDATE',
                    $permissionIds
                );
                if (count($permissions) !== count($permissionIds)) {
                    throw AdminAuthException::validation('One or more permissions are unavailable.');
                }
            }
            $connection->update(
                'UPDATE `admin_role_permissions` SET `deleted_at` = CURRENT_TIMESTAMP, '
                . '`updated_at` = CURRENT_TIMESTAMP WHERE `role_id` = ? AND `deleted_at` IS NULL',
                [$roleId]
            );
            foreach ($permissionIds as $permissionId) {
                $connection->insert(
                    'INSERT INTO `admin_role_permissions` (`id`, `role_id`, `permission_id`, '
                    . '`created_at`, `updated_at`, `deleted_at`) '
                    . 'VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL) '
                    . 'ON DUPLICATE KEY UPDATE `deleted_at` = NULL, `updated_at` = CURRENT_TIMESTAMP',
                    [$this->ids->generate(), $roleId, $permissionId]
                );
            }
            $this->audit->append($connection, $this->event(
                $auditContext,
                'role.permissions',
                $roleId,
                ['permission_ids' => $permissionIds]
            ));
        });
    }

    private function lockedRole(ConnectionInterface $connection, int $roleId): object
    {
        $role = $connection->selectOne(
            'SELECT `id` FROM `admin_roles` WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE',
            [$roleId]
        );
        if (! is_object($role)) {
            throw AdminAuthException::validation('Role does not exist.');
        }

        return $role;
    }

    /** @return array{string,string,string} */
    private function validatedRoleFields(string $name, string $code, string $description): array
    {
        $name = trim($name);
        $code = trim($code);
        $description = trim($description);
        if ($name === '' || mb_strlen($name) > 64
            || preg_match('/^[a-z][a-z0-9._-]{2,63}$/D', $code) !== 1
            || mb_strlen($description) > 255) {
            throw AdminAuthException::validation('Role fields are invalid.');
        }

        return [$name, $code, $description];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $requestData
     * @return array<string,mixed>
     */
    private function event(array $context, string $action, int $targetId, array $requestData): array
    {
        return $context + [
            'action' => $action,
            'target_type' => 'admin_role',
            'target_id' => $targetId,
            'request_data' => $requestData,
            'result' => 'success',
            'http_status' => 200,
        ];
    }
}
