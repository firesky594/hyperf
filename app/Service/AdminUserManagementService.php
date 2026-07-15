<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

class AdminUserManagementService
{
    private $passwordHasher;

    private $temporaryPasswordGenerator;

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
     * @param array<string,mixed> $auditContext
     * @return array{id:int,username:string,temporary_password:string}
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

    /** @return list<array<string,mixed>> */
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

    /** @param array<string,mixed> $auditContext */
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

    /** @param list<int> $roleIds @param array<string,mixed> $auditContext */
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

    /** @param array<string,mixed> $auditContext */
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
     * @param array<string,mixed> $context
     * @param array<string,mixed> $requestData
     * @return array<string,mixed>
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
