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

    /** 初始化当前组件所需的依赖。 */
    public function __construct(private AdminPermissionService $permissions)
    {
        parent::__construct();
    }

    /** 执行当前控制台命令。 */
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
