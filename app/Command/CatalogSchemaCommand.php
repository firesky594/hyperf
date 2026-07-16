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
    /** 初始化当前组件所需的依赖。 */
    public function __construct(private CatalogSchemaService $schema) { parent::__construct(); }
    /** 执行当前控制台命令。 */
    public function handle(): int { $this->schema->ensureSchema(); $this->info('API catalog schema is ready.'); return self::SUCCESS; }
}
