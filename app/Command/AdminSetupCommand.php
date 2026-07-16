<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Command;

use App\Exception\AdminAuthException;
use App\Service\AdminUserProvisioner;
use Hyperf\Command\Command;

/** 初始化后台管理员账号，并按需生成临时密码。 */
class AdminSetupCommand extends Command
{
    protected ?string $signature = 'admin:setup';

    protected string $description = 'Create or reset an administrator account.';

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AdminUserProvisioner $provisioner 注入的 AdminUserProvisioner 依赖。
     * @return void 无返回值。
     */
    public function __construct(private AdminUserProvisioner $provisioner)
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
        try {
            $result = $this->provisioner->provisionSuperAdmin('welkin');
        } catch (AdminAuthException $exception) {
            $this->error($exception->publicMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Administrator %s %s.',
            $result['username'],
            $result['created'] ? 'created' : 'reset'
        ));
        $this->line('Temporary password: ' . $result['temporary_password']);
        $this->warn('Sign in and change this password immediately. It will not be shown again.');

        return self::SUCCESS;
    }
}
