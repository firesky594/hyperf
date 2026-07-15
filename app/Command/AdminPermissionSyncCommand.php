<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AdminPermissionService;
use Hyperf\Command\Command;

final class AdminPermissionSyncCommand extends Command
{
    protected ?string $signature = 'admin:permissions:sync';

    protected string $description = 'Synchronize administrator route permissions.';

    public function __construct(private AdminPermissionService $permissions)
    {
        parent::__construct();
    }

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
