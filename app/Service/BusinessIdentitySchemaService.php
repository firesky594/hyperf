<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\DbConnection\Db;

/** 维护采购方与供应商业务身份的数据结构。 */
class BusinessIdentitySchemaService
{
    /** 初始化当前组件所需的依赖。 */
    public function __construct(private Db $db) {}

    /** 创建或升级当前模块所需的数据表。 */
    public function ensureSchema(): void
    {
        foreach ([
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `buyer_profiles` (
  `id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL, `display_name` VARCHAR(96) NOT NULL DEFAULT '',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1, `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uniq_buyer_profiles_user` (`user_id`), KEY `idx_buyer_profiles_status` (`status`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `supplier_profiles` (
  `id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL, `company_name` VARCHAR(128) NOT NULL,
  `contact_name` VARCHAR(96) NOT NULL, `contact_email` VARCHAR(190) NOT NULL, `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NULL DEFAULT NULL, `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uniq_supplier_profiles_user` (`user_id`), KEY `idx_supplier_profiles_status` (`status`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ] as $statement) { $this->db->statement($statement); }
    }
}
