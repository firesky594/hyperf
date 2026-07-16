<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\CatalogSchemaService;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class CatalogSchemaServiceTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }
    public function testCreatesCatalogTablesWithLifecycleAndStableVersionConstraints(): void
    {
        $db = Mockery::mock(Db::class); $statements = [];
        $db->shouldReceive('statement')->times(6)->withArgs(function (string $sql) use (&$statements): bool { $statements[] = $sql; return true; })->andReturnTrue();
        (new CatalogSchemaService($db))->ensureSchema(); $all = implode("\n", $statements);
        foreach (['api_products', 'api_versions', 'api_endpoints', 'api_documents', 'api_prices'] as $table) { self::assertStringContainsString("CREATE TABLE IF NOT EXISTS `{$table}`", $all); }
        foreach ($statements as $sql) { self::assertStringContainsString('`created_at`', $sql); self::assertStringContainsString('`updated_at`', $sql); self::assertStringContainsString('`deleted_at`', $sql); }
        self::assertStringContainsString('uniq_api_product_supplier_slug', $all);
        self::assertStringContainsString('uniq_api_version_label', $all);
        self::assertStringContainsString('`unit_price_micros` BIGINT UNSIGNED', $all);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `api_price_audit_logs`', $all);
        self::assertStringContainsString('`name` VARCHAR(128) NOT NULL', $statements[1]);
    }
}
