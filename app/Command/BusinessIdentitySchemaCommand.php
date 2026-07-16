<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BusinessIdentitySchemaService;
use Hyperf\Command\Command;

/** 创建采购方和供应商双侧业务身份数据表。 */
final class BusinessIdentitySchemaCommand extends Command
{
    protected ?string $signature = 'uniapi:identity-schema';
    protected string $description = 'Create or upgrade buyer and supplier identity schema.';
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param BusinessIdentitySchemaService $schema 注入的 BusinessIdentitySchemaService 依赖。
     * @return void 无返回值。
     */
    public function __construct(private BusinessIdentitySchemaService $schema) { parent::__construct(); }
    /**
     * 执行当前控制台命令。
     *
     * @return int 命令执行结果码，成功时返回零。
     */
    public function handle(): int { $this->schema->ensureSchema(); $this->info('Business identity schema is ready.'); return self::SUCCESS; }
}
