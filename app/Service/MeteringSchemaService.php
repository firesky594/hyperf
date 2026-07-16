<?php
declare(strict_types=1);namespace App\Service;use Hyperf\DbConnection\Db;
/** 维护网关路由、原始调用事件和周期用量汇总的数据结构。 */
final class MeteringSchemaService{/**
 * 初始化当前组件所需的依赖。
 *
 * @param Db $db 数据库访问入口。
 * @return void 无返回值。
 */
public function __construct(private Db$db){}/**
 * 创建或升级当前模块所需的数据表。
 *
 * @return void 无返回值。
 */
public function ensureSchema():void{foreach([
"CREATE TABLE IF NOT EXISTS `gateway_routes` (`id` BIGINT UNSIGNED NOT NULL,`api_endpoint_id` BIGINT UNSIGNED NOT NULL,`supplier_profile_id` BIGINT UNSIGNED NOT NULL,`upstream_url` VARCHAR(2048) NOT NULL,`status` VARCHAR(16) NOT NULL DEFAULT 'active',`config_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),UNIQUE KEY `uniq_gateway_endpoint`(`api_endpoint_id`),KEY `idx_gateway_supplier`(`supplier_profile_id`,`deleted_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS `gateway_call_events` (`id` BIGINT UNSIGNED NOT NULL,`event_id` VARCHAR(64) NOT NULL,`subscription_id` BIGINT UNSIGNED NOT NULL,`status_code` SMALLINT UNSIGNED NOT NULL,`duration_ms` INT UNSIGNED NOT NULL,`occurred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),UNIQUE KEY `uniq_event_id`(`event_id`),KEY `idx_call_subscription_time`(`subscription_id`,`occurred_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS `usage_aggregates` (`id` BIGINT UNSIGNED NOT NULL,`subscription_id` BIGINT UNSIGNED NOT NULL,`period_id` VARCHAR(32) NOT NULL,`call_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,`created_at` TIMESTAMP NULL,`updated_at` TIMESTAMP NULL,`deleted_at` TIMESTAMP NULL,PRIMARY KEY(`id`),UNIQUE KEY `uniq_usage_period`(`subscription_id`,`period_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"]as$sql)$this->db->statement($sql);}}
