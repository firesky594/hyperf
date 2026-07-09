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

    /**
     * 初始化认证数据准备命令。
     *
     * @param Db $db MySQL 访问入口，用于创建用户表和写入测试用户。
     * @param IdGeneratorInterface $idGenerator 雪花 ID 生成器，用于生成用户主键。
     */
    public function __construct(
        private Db $db,
        private IdGeneratorInterface $idGenerator
    ) {
        parent::__construct();
    }

    /**
     * 执行认证数据初始化。
     *
     * 该命令会先确保 users 表存在，再根据命令参数创建或更新一个测试用户。
     * 当 username 或 password 为空时，只创建表，不写入测试用户。
     *
     * @return int Symfony Console 命令退出码；成功时返回 Command::SUCCESS。
     */
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

    /**
     * 创建用户表。
     *
     * users.id 使用 BIGINT UNSIGNED 存储雪花 ID，不使用 MySQL 自增主键。
     *
     * @return void
     */
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

    /**
     * 创建或更新测试用户。
     *
     * 用户存在时只刷新密码哈希和更新时间；用户不存在时使用雪花 ID 创建新记录。
     *
     * @param string $username 测试用户名；由命令参数传入，调用前已去除首尾空白。
     * @param string $password 测试用户明文密码；写入数据库前会使用 password_hash 生成哈希。
     * @return void
     */
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
            'INSERT INTO users (id, username, password_hash, created_at, updated_at) '
                . 'VALUES (?, ?, ?, NOW(), NOW())',
            [$this->idGenerator->generate(), $username, $passwordHash]
        );
    }
}
