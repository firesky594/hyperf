<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Service\AdminAuditQueryService;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal @coversNothing */
final class AdminAuditQueryServiceTest extends TestCase
{
    protected function tearDown(): void { Mockery::close(); parent::tearDown(); }

    public function testReturnsBoundedReadOnlyAuditRows(): void
    {
        $db = Mockery::mock(Db::class);
        $db->shouldReceive('select')->once()->withArgs(fn (string $sql, array $bindings): bool => str_contains($sql, 'LIMIT 100') && $bindings === ['role.%'])
            ->andReturn([(object) ['id' => 1, 'action' => 'role.create']]);
        self::assertSame('role.create', (new AdminAuditQueryService($db))->search('role.%')[0]['action']);
    }

    public function testFailsClosedWhenAuditQueryFails(): void
    {
        $db = Mockery::mock(Db::class); $db->shouldReceive('select')->andThrow(new RuntimeException('db down'));
        $this->expectException(AdminAuthException::class);
        (new AdminAuditQueryService($db))->search('');
    }
}
