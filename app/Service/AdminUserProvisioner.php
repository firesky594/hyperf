<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\DbConnection\Db;
use Throwable;

class AdminUserProvisioner
{
    private $passwordHasher;

    public function __construct(
        private Db $db,
        private IdGeneratorInterface $idGenerator,
        ?callable $passwordHasher = null
    ) {
        $this->passwordHasher = $passwordHasher
            ?? static fn (string $password): string => password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * @return array{id:int,username:string,created:bool}
     */
    public function provision(string $username, string $password): array
    {
        try {
            $this->validate($username, $password);
            $this->createTable();

            $rows = $this->db->select(
                'SELECT id FROM admin_users WHERE username = ? LIMIT 1',
                [$username]
            );
            $passwordHash = ($this->passwordHasher)($password);
            $administrator = $rows[0] ?? null;

            if ($administrator !== null) {
                $id = (int) (is_object($administrator) ? $administrator->id : $administrator['id']);
                $this->db->update(
                    'UPDATE admin_users SET password_hash = ?, status = 1, updated_at = NOW() WHERE username = ?',
                    [$passwordHash, $username]
                );

                return ['id' => $id, 'username' => $username, 'created' => false];
            }

            $id = $this->idGenerator->generate();
            $this->db->insert(
                'INSERT INTO admin_users (id, username, password_hash, status, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, 1, NOW(), NOW())',
                [$id, $username, $passwordHash]
            );

            return ['id' => $id, 'username' => $username, 'created' => true];
        } catch (AdminAuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable('Administrator provisioning unavailable.', $throwable);
        }
    }

    private function validate(string $username, string $password): void
    {
        $passwordLength = strlen($password);
        if (
            preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username) !== 1
            || $passwordLength < 12
            || $passwordLength > 4096
        ) {
            throw AdminAuthException::validation(
                'Username must be 3-64 valid characters and password must be 12-4096 bytes.'
            );
        }
    }

    private function createTable(): void
    {
        $this->db->statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` BIGINT UNSIGNED NOT NULL,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_users_username` (`username`),
  KEY `idx_admin_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
