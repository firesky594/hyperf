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
use App\Command\AdminSetupCommand;
use App\Command\AdminPermissionSyncCommand;
use App\Command\AdminSchemaCommand;
use App\Command\AuthSetupCommand;

return [
    AuthSetupCommand::class,
    AdminSchemaCommand::class,
    AdminSetupCommand::class,
    AdminPermissionSyncCommand::class,
];
