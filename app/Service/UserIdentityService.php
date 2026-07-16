<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;

/** 查询用户工作台身份并处理供应商身份申请和资料维护。 */
class UserIdentityService
{
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @param IdGeneratorInterface $ids 注入的 IdGeneratorInterface 依赖。
     * @return void 无返回值。
     */
    public function __construct(private Db $db, private IdGeneratorInterface $ids) {}

    /**
     * 处理workspace。
     *
     * @param int $userId 对应业务记录的唯一标识。
     * @return array{buyer:?array<string,mixed>,supplier:?array<string,mixed>} 返回workspace结构化数据。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    public function workspace(int $userId): array
    {
        if ($userId <= 0) { throw AuthException::badRequest('User identity is invalid.'); }
        $buyer = $this->db->selectOne('SELECT `id`, `user_id`, `display_name`, `status`, `created_at`, `updated_at` FROM `buyer_profiles` WHERE `user_id` = ? AND `deleted_at` IS NULL LIMIT 1', [$userId]);
        $supplier = $this->db->selectOne('SELECT `id`, `user_id`, `company_name`, `contact_name`, `contact_email`, `status`, `created_at`, `updated_at` FROM `supplier_profiles` WHERE `user_id` = ? AND `deleted_at` IS NULL LIMIT 1', [$userId]);
        return ['buyer' => $this->row($buyer), 'supplier' => $this->row($supplier)];
    }

    /**
     * 申请供应商。
     *
     * @param int $userId 对应业务记录的唯一标识。
     * @param string $company company字符串。
     * @param string $contact contact字符串。
     * @param string $email 联系邮箱地址。
     * @return int 返回apply供应商整数结果。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    public function applySupplier(int $userId, string $company, string $contact, string $email): int
    {
        [$company, $contact, $email] = $this->supplierFields($company, $contact, $email);
        return $this->db->transaction(function (ConnectionInterface $connection) use ($userId, $company, $contact, $email): int {
            if ($connection->selectOne('SELECT `id` FROM `supplier_profiles` WHERE `user_id` = ? LIMIT 1 FOR UPDATE', [$userId]) !== null) { throw AuthException::conflict('Supplier profile already exists.'); }
            $id = $this->ids->generate();
            $connection->insert('INSERT INTO `supplier_profiles` (`id`, `user_id`, `company_name`, `contact_name`, `contact_email`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (?, ?, ?, ?, ?, \'pending\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)', [$id, $userId, $company, $contact, $email]);
            return $id;
        });
    }

    /**
     * 更新供应商。
     *
     * @param int $userId 对应业务记录的唯一标识。
     * @param string $company company字符串。
     * @param string $contact contact字符串。
     * @param string $email 联系邮箱地址。
     * @return void 无返回值。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    public function updateSupplier(int $userId, string $company, string $contact, string $email): void
    {
        [$company, $contact, $email] = $this->supplierFields($company, $contact, $email);
        if ($this->db->update('UPDATE `supplier_profiles` SET `company_name` = ?, `contact_name` = ?, `contact_email` = ?, `updated_at` = CURRENT_TIMESTAMP WHERE `user_id` = ? AND `deleted_at` IS NULL', [$company, $contact, $email, $userId]) !== 1) { throw AuthException::badRequest('Supplier profile does not exist.'); }
    }

    /**
     * 处理供应商字段。
     *
     * @param string $company company字符串。
     * @param string $contact contact字符串。
     * @param string $email 联系邮箱地址。
     * @return array{string,string,string} 返回供应商字段结构化数据。
     * @throws \App\Exception\AuthException 认证、授权或业务校验失败时抛出。
     */
    private function supplierFields(string $company, string $contact, string $email): array
    {
        $company = trim($company); $contact = trim($contact); $email = trim($email);
        if ($company === '' || mb_strlen($company) > 128 || $contact === '' || mb_strlen($contact) > 96 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) { throw AuthException::badRequest('Supplier profile fields are invalid.'); }
        return [$company, $contact, $email];
    }

    /**
     * 处理单条结果。
     *
     * @param object|array|null $row 单条数据库查询结果。
     * @return ?array<string,mixed> 查询成功时返回对应数据，不存在时返回 null。
     */
    private function row(object|array|null $row): ?array { return $row === null ? null : (is_object($row) ? get_object_vars($row) : $row); }
}
