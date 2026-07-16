<?php
declare(strict_types=1);namespace App\Command;use App\Service\BillingService;use Hyperf\Command\Command;
final class BillingRunCommand extends Command{protected ?string$signature='uniapi:billing-run {period : Billing period YYYY-MM}';protected string$description='Generate immutable invoices for one period.';public function __construct(private BillingService$billing){parent::__construct();}public function handle():int{$this->billing->generatePeriod((string)$this->input->getArgument('period'));$this->billing->pauseOverdue();$this->info('Billing period processed.');return self::SUCCESS;}}
