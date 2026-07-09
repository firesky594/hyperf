<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\DbConnection\Db;

class AuthSetupCommand extends Command
{
    protected ?string $signature = 'auth:setup {--username=demo : Demo username} {--password=secret : Demo password}';

    protected string $description = 'Create auth tables and seed a demo user.';

    public function __construct(
        private Db $db,
        private IdGeneratorInterface $idGenerator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->createUsersTable();

        $username = trim((string) $this->option('username'));
        $password = (string) $this->option('password');

        if ($username !== '' && $password !== '') {
            $this->upsertDemoUser($username, $password);
            $this->info(sprintf('Auth demo user [%s] is ready.', $username));
        } else {
            $this->info('Auth users table is ready.');
        }

        return self::SUCCESS;
    }

    private function createUsersTable(): void
    {
        $this->db->statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    private function upsertDemoUser(string $username, string $password): void
    {
        $rows = $this->db->select('SELECT id FROM users WHERE username = ? LIMIT 1', [$username]);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if ($rows !== []) {
            $this->db->update(
                'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE username = ?',
                [$passwordHash, $username]
            );
            return;
        }

        $this->db->insert(
            'INSERT INTO users (id, username, password_hash, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())',
            [$this->idGenerator->generate(), $username, $passwordHash]
        );
    }
}
