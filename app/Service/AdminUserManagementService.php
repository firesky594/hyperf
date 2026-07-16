<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

/** 维护后台管理员资料、状态、角色关联和密码重置。 */
class AdminUserManagementService
{
    private $passwordHasher;

    private $temporaryPasswordGenerator;

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @param IdGeneratorInterface $ids 注入的 IdGeneratorInterface 依赖。
     * @param AdminAuditService $audit 注入的 AdminAuditService 依赖。
     * @param AdminAuthService $auth 注入的 AdminAuthService 依赖。
     * @param ?callable $passwordHasher 用于安全校验的哈希值。
     * @param ?callable $temporaryPasswordGenerator 用于执行指定处理逻辑的回调。
     * @return void 无返回值。
     */
    public function __construct(
        private Db $db,
        private IdGeneratorInterface $ids,
        private AdminAuditService $audit,
        private AdminAuthService $auth,
        ?callable $passwordHasher = null,
        ?callable $temporaryPasswordGenerator = null
    ) {
        $this->passwordHasher = $passwordHasher
            ?? static fn (string $password): string => password_hash($password, PASSWORD_ARGON2ID);
        $this->temporaryPasswordGenerator = $temporaryPasswordGenerator
            ?? static fn (): string => bin2hex(random_bytes(16)) . 'Aa1!';
    }

    /**
     * 创建管理员。
     *
     * @param string $username 登录用户名。
     * @param array<string,mixed> $auditContext 记录操作者和请求信息的审计上下文。
     * @return array{id:int,username:string,temporary_password:string} 返回create管理员结构化数据。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function createAdministrator(string $username, array $auditContext): array
    {
        if (preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username) !== 1 || $username === 'welkin') {
            throw AdminAuthException::validation('Administrator username is invalid or protected.');
        }
        $temporaryPassword = (string) ($this->temporaryPasswordGenerator)();
        if (strlen($temporaryPassword) < 20) {
            throw AdminAuthException::unavailable('Unable to generate a secure temporary password.');
        }

        return $this->db->transaction(function (ConnectionInterface $connection) use (
            $username,
            $temporaryPassword,
            $auditContext
        ): array {
            if ($connection->selectOne(
                'SELECT `id` FROM `admin_users` WHERE `username` = ? LIMIT 1 FOR UPDATE',
                [$username]
            ) !== null) {
                throw AdminAuthException::validation('Administrator username already exists.');
            }

            $id = $this->ids->generate();
            $connection->insert(
                'INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `status`, `is_super_admin`, '
                . '`must_change_password`, `created_at`, `updated_at`, `deleted_at`) '
                . 'VALUES (?, ?, ?, 1, 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)',
                [$id, $username, ($this->passwordHasher)($temporaryPassword)]
            );
            $this->audit->append($connection, $this->event(
                $auditContext,
                'administrator.create',
                $id,
                ['username' => $username]
            ));

            return ['id' => $id, 'username' => $username, 'temporary_password' => $temporaryPassword];
        });
    }

    /**
     * 查询管理员列表。
     *
     * @return list<array<string,mixed>> 返回list管理员列表结构化数据。
     */
    public function listAdministrators(): array
    {
        $rows = $this->db->select(<<<'SQL'
SELECT au.`id`, au.`username`, au.`status`, au.`is_super_admin`, au.`must_change_password`,
       au.`last_login_at`, au.`created_at`, au.`updated_at`,
       GROUP_CONCAT(ar.`id` ORDER BY ar.`id`) AS `role_ids`,
       GROUP_CONCAT(ar.`name` ORDER BY ar.`id`) AS `role_names`
FROM `admin_users` au
LEFT JOIN `admin_user_roles` aur ON aur.`admin_user_id` = au.`id` AND aur.`deleted_at` IS NULL
LEFT JOIN `admin_roles` ar ON ar.`id` = aur.`role_id` AND ar.`deleted_at` IS NULL
WHERE au.`deleted_at` IS NULL
GROUP BY au.`id`, au.`username`, au.`status`, au.`is_super_admin`, au.`must_change_password`,
         au.`last_login_at`, au.`created_at`, au.`updated_at`
ORDER BY au.`is_super_admin` DESC, au.`created_at` ASC
SQL);

        return array_map(static function (object|array $row): array {
            $data = is_object($row) ? get_object_vars($row) : $row;
            $roleIds = (string) ($data['role_ids'] ?? '');
            $roleNames = (string) ($data['role_names'] ?? '');
            $data['role_ids'] = $roleIds === '' ? [] : array_map('intval', explode(',', $roleIds));
            $data['role_names'] = $roleNames === '' ? [] : explode(',', $roleNames);

            return $data;
        }, $rows);
    }

    /**
     * 设置状态。
     *
     * @param int $adminId 对应业务记录的唯一标识。
     * @param bool $enabled 控制enabled行为的布尔标记。
     * @param array<string,mixed> $auditContext 记录操作者和请求信息的审计上下文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function setStatus(int $adminId, bool $enabled, array $auditContext): void
    {
        if ($adminId <= 0) {
            throw AdminAuthException::validation();
        }
        $status = $enabled ? 1 : 0;
        $this->db->transaction(function (ConnectionInterface $connection) use (
            $adminId,
            $status,
            $auditContext
        ): void {
            $administrator = $connection->selectOne(
                'SELECT `id`, `username`, `is_super_admin` FROM `admin_users` '
                . 'WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE',
                [$adminId]
            );
            if ($administrator === null) {
                throw AdminAuthException::validation('Administrator does not exist.');
            }
            if ($status === 0 && (
                (string) $administrator->username === 'welkin'
                || (int) $administrator->is_super_admin === 1
            )) {
                throw AdminAuthException::validation('The permanent super administrator cannot be disabled.');
            }
            if ($connection->update(
                'UPDATE `admin_users` SET `status` = ?, `updated_at` = CURRENT_TIMESTAMP '
                . 'WHERE `id` = ? AND `deleted_at` IS NULL',
                [$status, $adminId]
            ) !== 1) {
                throw AdminAuthException::unavailable('Unable to update administrator status.');
            }
            $this->audit->append($connection, $this->event(
                $auditContext,
                'administrator.status',
                $adminId,
                ['status' => $status]
            ));
        });

        if (! $enabled) {
            $this->auth->revokeAdminSessions($adminId);
        }
    }

    /**
     * 更新管理员。
     *
     * @param int $adminId 对应业务记录的唯一标识。
     * @param string $username 登录用户名。
     * @param array<string,mixed> $auditContext 记录操作者和请求信息的审计上下文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function updateAdministrator(int $adminId, string $username, array $auditContext): void
    {
        $username = trim($username);
        if ($adminId <= 0 || preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username) !== 1 || $username === 'welkin') { throw AdminAuthException::validation('Administrator username is invalid or protected.'); }
        $this->db->transaction(function (ConnectionInterface $connection) use ($adminId, $username, $auditContext): void {
            $administrator = $this->lockedAdministrator($connection, $adminId);
            if ((int) $administrator->is_super_admin === 1 || (string) $administrator->username === 'welkin') { throw AdminAuthException::validation('The permanent super administrator cannot be renamed.'); }
            if ($connection->selectOne('SELECT `id` FROM `admin_users` WHERE `username` = ? AND `id` <> ? LIMIT 1 FOR UPDATE', [$username, $adminId]) !== null) { throw AdminAuthException::validation('Administrator username already exists.'); }
            if ($connection->update('UPDATE `admin_users` SET `username` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `deleted_at` IS NULL', [$username, $adminId]) !== 1) { throw AdminAuthException::unavailable('Unable to update administrator.'); }
            $this->audit->append($connection, $this->event($auditContext, 'administrator.update', $adminId, ['username' => $username]));
        });
    }

    /**
     * 分配角色列表。
     *
     * @param int $adminId 对应业务记录的唯一标识。
     * @param list<int> $roleIds 待关联业务记录的唯一标识列表。
     * @param array<string,mixed> $auditContext 记录操作者和请求信息的审计上下文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function assignRoles(int $adminId, array $roleIds, array $auditContext): void
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        sort($roleIds);
        if ($adminId <= 0 || $roleIds === [] || in_array(0, $roleIds, true)) {
            throw AdminAuthException::validation('Administrator roles are invalid.');
        }

        $this->db->transaction(function (ConnectionInterface $connection) use ($adminId, $roleIds, $auditContext): void {
            $administrator = $this->lockedAdministrator($connection, $adminId);
            if ((int) $administrator->is_super_admin === 1 || (string) $administrator->username === 'welkin') {
                throw AdminAuthException::validation('The permanent super administrator does not use role assignments.');
            }
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $roles = $connection->select(
                'SELECT `id` FROM `admin_roles` WHERE `id` IN (' . $placeholders . ') '
                . 'AND `status` = 1 AND `deleted_at` IS NULL FOR UPDATE',
                $roleIds
            );
            if (count($roles) !== count($roleIds)) {
                throw AdminAuthException::validation('One or more roles are unavailable.');
            }
            $connection->update(
                'UPDATE `admin_user_roles` SET `deleted_at` = CURRENT_TIMESTAMP, `updated_at` = CURRENT_TIMESTAMP '
                . 'WHERE `admin_user_id` = ? AND `deleted_at` IS NULL',
                [$adminId]
            );
            foreach ($roleIds as $roleId) {
                $connection->insert(
                    'INSERT INTO `admin_user_roles` (`id`, `admin_user_id`, `role_id`, `created_at`, `updated_at`, `deleted_at`) '
                    . 'VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL) '
                    . 'ON DUPLICATE KEY UPDATE `deleted_at` = NULL, `updated_at` = CURRENT_TIMESTAMP',
                    [$this->ids->generate(), $adminId, $roleId]
                );
            }
            $this->audit->append($connection, $this->event(
                $auditContext,
                'administrator.roles',
                $adminId,
                ['role_ids' => $roleIds]
            ));
        });
    }

    /**
     * 重置密码。
     *
     * @param int $adminId 对应业务记录的唯一标识。
     * @param array<string,mixed> $auditContext 记录操作者和请求信息的审计上下文。
     * @return string 返回reset密码字符串结果。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function resetPassword(int $adminId, array $auditContext): string
    {
        if ($adminId <= 0) {
            throw AdminAuthException::validation();
        }
        $temporaryPassword = (string) ($this->temporaryPasswordGenerator)();
        if (strlen($temporaryPassword) < 20) {
            throw AdminAuthException::unavailable('Unable to generate a secure temporary password.');
        }
        $this->db->transaction(function (ConnectionInterface $connection) use (
            $adminId,
            $temporaryPassword,
            $auditContext
        ): void {
            $administrator = $this->lockedAdministrator($connection, $adminId);
            if ((int) $administrator->is_super_admin === 1 || (string) $administrator->username === 'welkin') {
                throw AdminAuthException::validation('Use the protected setup flow for the permanent super administrator.');
            }
            if ($connection->update(
                'UPDATE `admin_users` SET `password_hash` = ?, `must_change_password` = 1, '
                . '`updated_at` = CURRENT_TIMESTAMP WHERE `id` = ? AND `deleted_at` IS NULL',
                [($this->passwordHasher)($temporaryPassword), $adminId]
            ) !== 1) {
                throw AdminAuthException::unavailable('Unable to reset administrator password.');
            }
            $this->audit->append($connection, $this->event(
                $auditContext,
                'administrator.password-reset',
                $adminId,
                []
            ));
        });
        $this->auth->revokeAdminSessions($adminId);

        return $temporaryPassword;
    }

    /**
     * 处理locked管理员。
     *
     * @param ConnectionInterface $connection 传入的 ConnectionInterface 实例，用于处理locked管理员。
     * @param int $adminId 对应业务记录的唯一标识。
     * @return object 返回locked管理员处理结果。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function lockedAdministrator(ConnectionInterface $connection, int $adminId): object
    {
        $administrator = $connection->selectOne(
            'SELECT `id`, `username`, `is_super_admin` FROM `admin_users` '
            . 'WHERE `id` = ? AND `deleted_at` IS NULL LIMIT 1 FOR UPDATE',
            [$adminId]
        );
        if (! is_object($administrator)) {
            throw AdminAuthException::validation('Administrator does not exist.');
        }

        return $administrator;
    }

    /**
     * 处理事件。
     *
     * @param array<string,mixed> $context 当前操作的审计上下文。
     * @param string $action 待执行的操作标识。
     * @param int $targetId 对应业务记录的唯一标识。
     * @param array<string,mixed> $requestData request业务数据数据集合。
     * @return array<string,mixed> 返回事件结构化数据。
     */
    private function event(array $context, string $action, int $targetId, array $requestData): array
    {
        return $context + [
            'action' => $action,
            'target_type' => 'admin_user',
            'target_id' => $targetId,
            'request_data' => $requestData,
            'result' => 'success',
            'http_status' => 200,
        ];
    }
}
