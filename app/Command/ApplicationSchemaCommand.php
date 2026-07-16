<?php
declare(strict_types=1);
namespace App\Command;
use App\Service\ApplicationSchemaService;
use Hyperf\Command\Command;
/** 创建或升级采购方应用、凭据、订阅与额度相关数据表。 */
final class ApplicationSchemaCommand extends Command{protected ?string$signature='uniapi:application-schema';protected string$description='Create application and subscription schema.';/**
 * 初始化当前组件所需的依赖。
 *
 * @param ApplicationSchemaService $schema 注入的 ApplicationSchemaService 依赖。
 * @return void 无返回值。
 */
public function __construct(private ApplicationSchemaService$schema){parent::__construct();}/**
 * 执行当前控制台命令。
 *
 * @return int 命令执行结果码，成功时返回零。
 */
public function handle():int{$this->schema->ensureSchema();$this->info('Application schema is ready.');return self::SUCCESS;}}
