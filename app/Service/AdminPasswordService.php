<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Throwable;

/** 负责管理员密码强度校验、哈希生成和安全比对。 */
class AdminPasswordService
{
    private $passwordVerifier;

    private $passwordHasher;

    public function __construct(
        private Db $db,
        private AdminAuthService $auth,
        ?callable $passwordVerifier = null,
        ?callable $passwordHasher = null
    ) {
        $this->passwordVerifier = $passwordVerifier
            ?? static fn (string $password, string $hash): bool => password_verify($password, $hash);
        $this->passwordHasher = $passwordHasher
            ?? static fn (string $password): string => password_hash($password, PASSWORD_ARGON2ID);
    }

    public function changePassword(int $adminId, string $currentPassword, string $newPassword): void
    {
        if (
            $adminId <= 0
            || $currentPassword === ''
            || strlen($newPassword) < 12
            || strlen($newPassword) > 4096
            || hash_equals($currentPassword, $newPassword)
        ) {
            throw AdminAuthException::validation('Password change input is invalid.');
        }

        try {
            $this->db->transaction(function (ConnectionInterface $connection) use (
                $adminId,
                $currentPassword,
                $newPassword
            ): void {
                $rows = $connection->select(
                    'SELECT id, password_hash, status FROM admin_users '
                    . 'WHERE id = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                    [$adminId]
                );
                $administrator = $rows[0] ?? null;
                $administrator = is_object($administrator) ? get_object_vars($administrator) : $administrator;
                $storedHash = is_array($administrator) ? ($administrator['password_hash'] ?? null) : null;

                if (
                    ! is_string($storedHash)
                    || (int) ($administrator['status'] ?? 0) !== 1
                    || ! ($this->passwordVerifier)($currentPassword, $storedHash)
                ) {
                    throw AdminAuthException::invalidCredentials('Current password is incorrect.');
                }

                $updated = $connection->update(
                    'UPDATE admin_users SET password_hash = ?, must_change_password = 0, updated_at = NOW() '
                    . 'WHERE id = ? AND status = 1 AND deleted_at IS NULL',
                    [($this->passwordHasher)($newPassword), $adminId]
                );
                if ($updated !== 1) {
                    throw AdminAuthException::unavailable('Unable to change administrator password.');
                }
            });

            $this->auth->revokeAdminSessions($adminId);
        } catch (AdminAuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable('Unable to change administrator password.', $throwable);
        }
    }
}
