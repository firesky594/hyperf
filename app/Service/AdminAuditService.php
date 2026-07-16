<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use JsonException;

/** 写入后台关键操作的结构化永久审计记录。 */
class AdminAuditService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        '_csrf', 'cookie', 'authorization', 'token', 'secret', 'api_secret', 'signature',
    ];

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param IdGeneratorInterface $ids 注入的 IdGeneratorInterface 依赖。
     * @return void 无返回值。
     */
    public function __construct(private IdGeneratorInterface $ids)
    {
    }

    /**
     * 处理append。
     *
     * @param ConnectionInterface $connection 传入的 ConnectionInterface 实例，用于处理append。
     * @param array<string,mixed> $event 当前监听到的事件对象。
     * @return void 无返回值。
     * @throws JsonException 处理失败时抛出。
     */
    public function append(ConnectionInterface $connection, array $event): void
    {
        $summary = $this->sanitize(is_array($event['request_data'] ?? null) ? $event['request_data'] : []);
        $connection->insert(
            'INSERT INTO `admin_audit_logs` (`id`, `request_id`, `actor_admin_id`, `actor_username`, `action`, '
            . '`target_type`, `target_id`, `request_method`, `request_path`, `request_summary`, `result`, '
            . '`http_status`, `error_code`, `ip_address`, `user_agent`, `duration_ms`, '
            . '`created_at`, `updated_at`, `deleted_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '
            . 'CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)',
            [
                $this->ids->generate(),
                (string) ($event['request_id'] ?? ''),
                isset($event['actor_admin_id']) ? (int) $event['actor_admin_id'] : null,
                (string) ($event['actor_username'] ?? ''),
                (string) ($event['action'] ?? ''),
                (string) ($event['target_type'] ?? ''),
                isset($event['target_id']) ? (int) $event['target_id'] : null,
                strtoupper((string) ($event['request_method'] ?? 'GET')),
                (string) ($event['request_path'] ?? ''),
                json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                (string) ($event['result'] ?? 'success'),
                (int) ($event['http_status'] ?? 200),
                (string) ($event['error_code'] ?? ''),
                (string) ($event['ip_address'] ?? ''),
                (string) ($event['user_agent'] ?? ''),
                (float) ($event['duration_ms'] ?? 0),
            ]
        );
    }

    /**
     * 处理sanitize。
     *
     * @param array<string,mixed> $data 待处理的业务数据。
     * @return array<string,mixed> 返回sanitize结构化数据。
     */
    private function sanitize(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
                continue;
            }
            $safe[(string) $key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $safe;
    }
}
