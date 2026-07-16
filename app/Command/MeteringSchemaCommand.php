<?php
declare(strict_types=1);namespace App\Command;use App\Service\MeteringSchemaService;use Hyperf\Command\Command;
final class MeteringSchemaCommand extends Command{protected ?string$signature='uniapi:metering-schema';protected string$description='Create gateway metering schema.';public function __construct(private MeteringSchemaService$schema){parent::__construct();}public function handle():int{$this->schema->ensureSchema();$this->info('Metering schema is ready.');return self::SUCCESS;}}
