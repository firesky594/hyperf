<?php
declare(strict_types=1);namespace App\Service;use App\Exception\AuthException;use Hyperf\DbConnection\Db;
/** 校验应用访问密钥并解析可调用的有效订阅和额度配置。 */
final class GatewayAccessService{/**
 * 初始化当前组件所需的依赖。
 *
 * @param Db $db 数据库访问入口。
 * @return void 无返回值。
 */
public function __construct(private Db$db){} /**
 * 解析并返回当前请求对应的有效配置。
 *
 * @param string $key 缓存、锁或凭据键。
 * @param int $subscriptionId 对应业务记录的唯一标识。
 * @return array<string,mixed> 返回resolve结构化数据。
 * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
 */
public function resolve(string$key,int$subscriptionId):array{if(!preg_match('/^app_live_(ak_[a-f0-9]{12})\.[a-f0-9]{64}$/D',$key,$m))throw AuthException::invalidCredentials();$row=$this->db->selectOne('SELECT c.`secret_hash`,s.`id` AS `subscription_id`,s.`api_version_id`,q.`qps_limit`,q.`period_limit`,q.`period_seconds` FROM `application_credentials` c INNER JOIN `buyer_applications` a ON a.`id`=c.`application_id` INNER JOIN `api_subscriptions` s ON s.`application_id`=a.`id` INNER JOIN `subscription_quotas` q ON q.`subscription_id`=s.`id` AND q.`deleted_at` IS NULL WHERE c.`key_prefix`=? AND s.`id`=? AND c.`status`=\'active\' AND s.`status`=\'active\' AND a.`status`=\'active\' AND c.`deleted_at` IS NULL AND s.`deleted_at` IS NULL LIMIT 1',[$m[1],$subscriptionId]);if(!is_object($row)||!hash_equals((string)$row->secret_hash,hash('sha256',$key)))throw AuthException::invalidCredentials();return get_object_vars($row);}}
