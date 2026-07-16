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
use App\Command\BusinessIdentitySchemaCommand;
use App\Command\CatalogSchemaCommand;
use App\Command\ApplicationSchemaCommand;
use App\Command\MeteringSchemaCommand;
use App\Command\BillingSchemaCommand;
use App\Command\BillingRunCommand;

// 注册 UniAPI 数据结构初始化、管理员初始化、权限同步和账单处理命令。
return [
    AuthSetupCommand::class,
    AdminSchemaCommand::class,
    AdminSetupCommand::class,
    AdminPermissionSyncCommand::class,
    BusinessIdentitySchemaCommand::class,
    CatalogSchemaCommand::class,
    ApplicationSchemaCommand::class,
    MeteringSchemaCommand::class,
    BillingSchemaCommand::class,
    BillingRunCommand::class,
];
