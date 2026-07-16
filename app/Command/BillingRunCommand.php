<?php
declare(strict_types=1);namespace App\Command;use App\Service\BillingService;use Hyperf\Command\Command;
/** 按指定账期生成不可变账单，并暂停超过宽限期的订阅。 */
final class BillingRunCommand extends Command{protected ?string$signature='uniapi:billing-run {period : Billing period YYYY-MM}';protected string$description='Generate immutable invoices for one period.';/**
 * 初始化当前组件所需的依赖。
 *
 * @param BillingService $billing 注入的 BillingService 依赖。
 * @return void 无返回值。
 */
public function __construct(private BillingService$billing){parent::__construct();}/**
 * 执行当前控制台命令。
 *
 * @return int 命令执行结果码，成功时返回零。
 */
public function handle():int{$this->billing->generatePeriod((string)$this->input->getArgument('period'));$this->billing->pauseOverdue();$this->info('Billing period processed.');return self::SUCCESS;}}
