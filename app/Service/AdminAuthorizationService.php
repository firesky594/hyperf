<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\DbConnection\Db;

/** 根据管理员角色与权限状态执行后台服务端授权。 */
class AdminAuthorizationService
{
    public function __construct(private Db $db)
    {
    }

    /**
     * @param array<string,mixed> $session
     */
    public function allows(array $session, string $permissionCode): bool
    {
        if (($session['is_super_admin'] ?? false) === true) {
            return true;
        }

        $adminId = $session['admin_id'] ?? null;
        if (! is_int($adminId) && ! ctype_digit((string) $adminId)) {
            return false;
        }

        $row = $this->db->selectOne(<<<'SQL'
SELECT 1 AS `allowed`
FROM `admin_users` au
INNER JOIN `admin_user_roles` aur ON aur.`admin_user_id` = au.`id` AND aur.`deleted_at` IS NULL
INNER JOIN `admin_roles` ar ON ar.`id` = aur.`role_id` AND ar.`status` = 1 AND ar.`deleted_at` IS NULL
INNER JOIN `admin_role_permissions` arp ON arp.`role_id` = ar.`id` AND arp.`deleted_at` IS NULL
INNER JOIN `admin_permissions` ap ON ap.`id` = arp.`permission_id` AND ap.`status` = 1 AND ap.`deleted_at` IS NULL
WHERE au.`id` = ? AND au.`status` = 1 AND au.`deleted_at` IS NULL AND ap.`code` = ?
LIMIT 1
SQL, [(int) $adminId, $permissionCode]);

        return $row !== null;
    }
}
