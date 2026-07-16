<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CatalogSchemaService;
use Hyperf\Command\Command;

/** 创建 API 商品、版本、端点、文档、价格和价格审计数据表。 */
final class CatalogSchemaCommand extends Command
{
    protected ?string $signature = 'uniapi:catalog-schema';
    protected string $description = 'Create or upgrade the API catalog schema.';
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param CatalogSchemaService $schema 注入的 CatalogSchemaService 依赖。
     * @return void 无返回值。
     */
    public function __construct(private CatalogSchemaService $schema) { parent::__construct(); }
    /**
     * 执行当前控制台命令。
     *
     * @return int 命令执行结果码，成功时返回零。
     */
    public function handle(): int { $this->schema->ensureSchema(); $this->info('API catalog schema is ready.'); return self::SUCCESS; }
}
