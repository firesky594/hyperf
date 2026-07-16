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
    public function __construct(private BusinessIdentitySchemaService $schema) { parent::__construct(); }
    public function handle(): int { $this->schema->ensureSchema(); $this->info('Business identity schema is ready.'); return self::SUCCESS; }
}
