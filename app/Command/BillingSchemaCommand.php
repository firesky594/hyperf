<?php
declare(strict_types=1);namespace App\Command;use App\Service\BillingSchemaService;use Hyperf\Command\Command;
/** 创建或升级账单、付款凭证、佣金与结算相关数据表。 */
final class BillingSchemaCommand extends Command{protected ?string$signature='uniapi:billing-schema';protected string$description='Create billing and settlement schema.';public function __construct(private BillingSchemaService$schema){parent::__construct();}public function handle():int{$this->schema->ensureSchema();$this->info('Billing schema is ready.');return self::SUCCESS;}}
