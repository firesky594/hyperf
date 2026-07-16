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

    public function __construct(private AdminSchemaService $schema)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->schema->ensureSchema();
        $this->info('Administrator schema is ready.');

        return self::SUCCESS;
    }
}
