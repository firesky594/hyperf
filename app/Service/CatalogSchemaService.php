<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\DbConnection\Db;

class CatalogSchemaService
{
    public function __construct(private Db $db) {}
    public function ensureSchema(): void
    {
        foreach ([
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_products` (
 `id` BIGINT UNSIGNED NOT NULL, `supplier_profile_id` BIGINT UNSIGNED NOT NULL, `name` VARCHAR(128) NOT NULL,
 `slug` VARCHAR(96) NOT NULL, `summary` VARCHAR(500) NOT NULL DEFAULT '', `status` VARCHAR(16) NOT NULL DEFAULT 'draft',
 `current_published_version_id` BIGINT UNSIGNED DEFAULT NULL, `created_at` TIMESTAMP NULL DEFAULT NULL,
 `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`),
 UNIQUE KEY `uniq_api_product_supplier_slug` (`supplier_profile_id`,`slug`), KEY `idx_api_products_market` (`status`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_versions` (
 `id` BIGINT UNSIGNED NOT NULL, `api_product_id` BIGINT UNSIGNED NOT NULL, `version` VARCHAR(32) NOT NULL,
 `name` VARCHAR(128) NOT NULL, `summary` VARCHAR(500) NOT NULL DEFAULT '',
 `status` VARCHAR(16) NOT NULL DEFAULT 'draft', `published_at` TIMESTAMP NULL DEFAULT NULL,
 `created_at` TIMESTAMP NULL DEFAULT NULL, `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uniq_api_version_label` (`api_product_id`,`version`), KEY `idx_api_versions_status` (`api_product_id`,`status`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_endpoints` (
 `id` BIGINT UNSIGNED NOT NULL, `api_version_id` BIGINT UNSIGNED NOT NULL, `method` VARCHAR(12) NOT NULL,
 `path` VARCHAR(255) NOT NULL, `name` VARCHAR(128) NOT NULL, `description` VARCHAR(500) NOT NULL DEFAULT '',
 `created_at` TIMESTAMP NULL DEFAULT NULL, `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uniq_api_endpoint` (`api_version_id`,`method`,`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_documents` (
 `id` BIGINT UNSIGNED NOT NULL, `api_version_id` BIGINT UNSIGNED NOT NULL, `content_md` MEDIUMTEXT NOT NULL,
 `created_at` TIMESTAMP NULL DEFAULT NULL, `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uniq_api_document_version` (`api_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_prices` (
 `id` BIGINT UNSIGNED NOT NULL, `api_version_id` BIGINT UNSIGNED NOT NULL, `unit_price_micros` BIGINT UNSIGNED NOT NULL,
 `currency` CHAR(3) NOT NULL DEFAULT 'CNY', `billing_unit` INT UNSIGNED NOT NULL DEFAULT 1,
 `created_at` TIMESTAMP NULL DEFAULT NULL, `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uniq_api_price_version` (`api_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS `api_price_audit_logs` (
 `id` BIGINT UNSIGNED NOT NULL, `supplier_profile_id` BIGINT UNSIGNED NOT NULL, `api_product_id` BIGINT UNSIGNED NOT NULL,
 `api_version_id` BIGINT UNSIGNED NOT NULL, `old_unit_price_micros` BIGINT UNSIGNED DEFAULT NULL,
 `new_unit_price_micros` BIGINT UNSIGNED NOT NULL, `currency` CHAR(3) NOT NULL, `action` VARCHAR(32) NOT NULL,
 `created_at` TIMESTAMP NULL DEFAULT NULL, `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL,
 PRIMARY KEY (`id`), KEY `idx_api_price_audit_version` (`api_version_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
        ] as $sql) { $this->db->statement($sql); }
    }
}
