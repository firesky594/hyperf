<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\DbConnection\Db;
use Throwable;

/** 查询只追加且永久保存的后台操作审计记录。 */
class AdminAuditQueryService
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @return void 无返回值。
     */
    public function __construct(private Db $db) {}

    /**
     * 处理search。
     *
     * @param string $action 待执行的操作标识。
     * @return list<array<string,mixed>> 返回search结构化数据。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function search(string $action = ''): array
    {
        try {
            $action = trim($action);
            $where = $action === '' ? '' : ' WHERE `action` LIKE ?';
            $bindings = $action === '' ? [] : [$action];
            $rows = $this->db->select('SELECT `id`, `request_id`, `actor_admin_id`, `actor_username`, `action`, `target_type`, `target_id`, `request_method`, `request_path`, `request_summary`, `result`, `http_status`, `error_code`, `ip_address`, `duration_ms`, `created_at` FROM `admin_audit_logs`' . $where . ' ORDER BY `created_at` DESC, `id` DESC LIMIT 100', $bindings);
            return array_map(static fn (object|array $row): array => is_object($row) ? get_object_vars($row) : $row, $rows);
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable('Audit records are unavailable.', $throwable);
        }
    }
}
