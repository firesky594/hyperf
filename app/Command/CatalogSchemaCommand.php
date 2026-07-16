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
    public function __construct(private CatalogSchemaService $schema) { parent::__construct(); }
    public function handle(): int { $this->schema->ensureSchema(); $this->info('API catalog schema is ready.'); return self::SUCCESS; }
}
