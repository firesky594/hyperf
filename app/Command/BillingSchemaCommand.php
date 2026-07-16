<?php
declare(strict_types=1);namespace App\Command;use App\Service\BillingSchemaService;use Hyperf\Command\Command;
/** 创建或升级账单、付款凭证、佣金与结算相关数据表。 */
final class BillingSchemaCommand extends Command{protected ?string$signature='uniapi:billing-schema';protected string$description='Create billing and settlement schema.';/**
 * 初始化当前组件所需的依赖。
 *
 * @param BillingSchemaService $schema 注入的 BillingSchemaService 依赖。
 * @return void 无返回值。
 */
public function __construct(private BillingSchemaService$schema){parent::__construct();}/**
 * 执行当前控制台命令。
 *
 * @return int 命令执行结果码，成功时返回零。
 */
public function handle():int{$this->schema->ensureSchema();$this->info('Billing schema is ready.');return self::SUCCESS;}}
