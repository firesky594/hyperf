<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\BusinessIdentitySchemaService;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class BusinessIdentitySchemaServiceTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }

    public function testCreatesBuyerAndSupplierProfilesWithLifecycleColumns(): void
    {
        $db = Mockery::mock(Db::class); $sql = [];
        $db->shouldReceive('statement')->twice()->withArgs(function (string $statement) use (&$sql): bool { $sql[] = $statement; return true; })->andReturnTrue();
        (new BusinessIdentitySchemaService($db))->ensureSchema();
        $all = implode("\n", $sql);
        foreach (['buyer_profiles', 'supplier_profiles'] as $table) { self::assertStringContainsString("CREATE TABLE IF NOT EXISTS `{$table}`", $all); }
        foreach ($sql as $statement) { self::assertStringContainsString('`created_at`', $statement); self::assertStringContainsString('`updated_at`', $statement); self::assertStringContainsString('`deleted_at`', $statement); }
        self::assertStringContainsString('UNIQUE KEY `uniq_buyer_profiles_user`', $all);
        self::assertStringContainsString('UNIQUE KEY `uniq_supplier_profiles_user`', $all);
    }
}
