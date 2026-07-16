<?php
declare(strict_types=1);
namespace App\Service;
use Hyperf\DbConnection\Db;
/** 维护应用、访问凭据、API 订阅及额度审计的数据结构。 */
final class ApplicationSchemaService
{
 /**
  * 初始化当前组件所需的依赖。
  *
  * @param Db $db 数据库访问入口。
  * @return void 无返回值。
  */
 public function __construct(private Db $db){}
 /**
  * 创建或升级当前模块所需的数据表。
  *
  * @return void 无返回值。
  */
 public function ensureSchema():void{foreach([
"CREATE TABLE IF NOT EXISTS `buyer_applications` (`id` BIGINT UNSIGNED NOT NULL,`buyer_profile_id` BIGINT UNSIGNED NOT NULL,`name` VARCHAR(128) NOT NULL,`status` VARCHAR(16) NOT NULL DEFAULT 'active',`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),KEY `idx_buyer_apps`(`buyer_profile_id`,`deleted_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS `application_credentials` (`id` BIGINT UNSIGNED NOT NULL,`application_id` BIGINT UNSIGNED NOT NULL,`key_prefix` VARCHAR(32) NOT NULL,`secret_hash` CHAR(64) NOT NULL,`status` VARCHAR(16) NOT NULL DEFAULT 'active',`revoked_at` TIMESTAMP NULL,`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),UNIQUE KEY `uniq_app_key_prefix`(`key_prefix`),KEY `idx_app_credentials`(`application_id`,`status`,`deleted_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS `api_subscriptions` (`id` BIGINT UNSIGNED NOT NULL,`application_id` BIGINT UNSIGNED NOT NULL,`api_product_id` BIGINT UNSIGNED NOT NULL,`api_version_id` BIGINT UNSIGNED NOT NULL,`supplier_profile_id` BIGINT UNSIGNED NOT NULL,`status` VARCHAR(16) NOT NULL DEFAULT 'active',`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),UNIQUE KEY `uniq_app_api_subscription`(`application_id`,`api_product_id`),KEY `idx_subscription_supplier`(`supplier_profile_id`,`status`,`deleted_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS `subscription_quotas` (`id` BIGINT UNSIGNED NOT NULL,`subscription_id` BIGINT UNSIGNED NOT NULL,`qps_limit` INT UNSIGNED NOT NULL,`period_limit` BIGINT UNSIGNED NOT NULL,`period_seconds` INT UNSIGNED NOT NULL DEFAULT 2592000,`config_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),UNIQUE KEY `uniq_subscription_quota`(`subscription_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS `quota_audit_logs` (`id` BIGINT UNSIGNED NOT NULL,`supplier_profile_id` BIGINT UNSIGNED NOT NULL,`subscription_id` BIGINT UNSIGNED NOT NULL,`old_qps_limit` INT UNSIGNED NOT NULL,`new_qps_limit` INT UNSIGNED NOT NULL,`old_period_limit` BIGINT UNSIGNED NOT NULL,`new_period_limit` BIGINT UNSIGNED NOT NULL,`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),KEY `idx_quota_audit`(`subscription_id`,`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
]as$sql)$this->db->statement($sql);}
}
