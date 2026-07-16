<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AdminPermissionService;
use Hyperf\Command\Command;

/** 将代码中的系统权限定义同步到数据库，并保留后台自定义权限。 */
final class AdminPermissionSyncCommand extends Command
{
    protected ?string $signature = 'admin:permissions:sync';

    protected string $description = 'Synchronize administrator route permissions.';

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AdminPermissionService $permissions 注入的 AdminPermissionService 依赖。
     * @return void 无返回值。
     */
    public function __construct(private AdminPermissionService $permissions)
    {
        parent::__construct();
    }

    /**
     * 执行当前控制台命令。
     *
     * @return int 命令执行结果码，成功时返回零。
     */
    public function handle(): int
    {
        $result = $this->permissions->syncSystemPermissions();
        $this->info(sprintf(
            'Permission sync completed: created=%d restored=%d disabled=%d skipped_custom=%d',
            $result['created'],
            $result['restored'],
            $result['disabled'],
            $result['skipped_custom']
        ));

        return self::SUCCESS;
    }
}
