<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AdminSchemaService;
use Hyperf\Command\Command;

/** 创建或升级管理员、RBAC 与永久审计所需的数据表。 */
final class AdminSchemaCommand extends Command
{
    protected ?string $signature = 'admin:schema';

    protected string $description = 'Create or upgrade the administrator database schema.';

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param AdminSchemaService $schema 注入的 AdminSchemaService 依赖。
     * @return void 无返回值。
     */
    public function __construct(private AdminSchemaService $schema)
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
        $this->schema->ensureSchema();
        $this->info('Administrator schema is ready.');

        return self::SUCCESS;
    }
}
