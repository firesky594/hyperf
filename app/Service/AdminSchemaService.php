<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\DbConnection\Db;

class AdminSchemaService
{
    public function __construct(private Db $db)
    {
    }

    public function ensureSchema(): void
    {
        foreach ($this->statements() as $statement) {
            $this->db->statement($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function statements(): array
    {
        return [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` BIGINT UNSIGNED NOT NULL,
  `username` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_super_admin` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `must_change_password` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_users_username` (`username`),
  KEY `idx_admin_users_status` (`status`),
  KEY `idx_admin_users_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
ALTER TABLE `admin_users`
  ADD COLUMN IF NOT EXISTS `is_super_admin` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `must_change_password` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `is_super_admin`,
  ADD COLUMN IF NOT EXISTS `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_roles_code` (`code`),
  KEY `idx_admin_roles_status_deleted` (`status`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_permissions` (
  `id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(96) NOT NULL,
  `code` VARCHAR(128) NOT NULL,
  `source` VARCHAR(16) NOT NULL,
  `route_method` VARCHAR(16) DEFAULT NULL,
  `route_path` VARCHAR(255) DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_permissions_code` (`code`),
  KEY `idx_admin_permissions_source_status` (`source`, `status`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_user_roles` (
  `id` BIGINT UNSIGNED NOT NULL,
  `admin_user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_user_role` (`admin_user_id`, `role_id`),
  KEY `idx_admin_user_roles_role` (`role_id`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_role_permissions` (
  `id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_role_permission` (`role_id`, `permission_id`),
  KEY `idx_admin_role_permissions_permission` (`permission_id`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_menus` (
  `id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(64) NOT NULL,
  `icon` VARCHAR(64) NOT NULL DEFAULT '',
  `sort_order` INT NOT NULL DEFAULT 0,
  `route_path` VARCHAR(255) NOT NULL DEFAULT '',
  `permission_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_menus_parent_sort` (`parent_id`, `sort_order`, `deleted_at`),
  KEY `idx_admin_menus_permission` (`permission_id`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL,
  `request_id` VARCHAR(64) NOT NULL,
  `actor_admin_id` BIGINT UNSIGNED DEFAULT NULL,
  `actor_username` VARCHAR(64) NOT NULL DEFAULT '',
  `action` VARCHAR(128) NOT NULL,
  `target_type` VARCHAR(64) NOT NULL DEFAULT '',
  `target_id` BIGINT UNSIGNED DEFAULT NULL,
  `request_method` VARCHAR(16) NOT NULL,
  `request_path` VARCHAR(255) NOT NULL,
  `request_summary` JSON DEFAULT NULL,
  `result` VARCHAR(16) NOT NULL,
  `http_status` SMALLINT UNSIGNED NOT NULL,
  `error_code` VARCHAR(64) NOT NULL DEFAULT '',
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(512) NOT NULL DEFAULT '',
  `started_at` TIMESTAMP(6) NULL DEFAULT NULL,
  `finished_at` TIMESTAMP(6) NULL DEFAULT NULL,
  `duration_ms` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_audit_actor_created` (`actor_admin_id`, `created_at`),
  KEY `idx_admin_audit_action_created` (`action`, `created_at`),
  KEY `idx_admin_audit_request_id` (`request_id`),
  KEY `idx_admin_audit_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ];
    }
}
