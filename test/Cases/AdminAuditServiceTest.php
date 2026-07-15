<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\AdminAuditService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

/** @internal @coversNothing */
final class AdminAuditServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAppendWritesPermanentDatabaseAuditWithoutSensitiveValues(): void
    {
        $connection = Mockery::mock(ConnectionInterface::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $ids->shouldReceive('generate')->once()->andReturn(7001);
        $connection->shouldReceive('insert')->once()->withArgs(function (string $sql, array $bindings): bool {
            self::assertStringContainsString('admin_audit_logs', $sql);
            self::assertStringContainsString('created_at', $sql);
            self::assertStringContainsString('updated_at', $sql);
            self::assertStringContainsString('deleted_at', $sql);
            self::assertSame(7001, $bindings[0]);
            self::assertContains('administrator.status', $bindings);
            $encoded = implode('|', array_map('strval', $bindings));
            self::assertStringNotContainsString('secret-password', $encoded);
            self::assertStringNotContainsString('Bearer token', $encoded);
            return true;
        })->andReturnTrue();
        $connection->shouldNotReceive('update');
        $connection->shouldNotReceive('delete');

        (new AdminAuditService($ids))->append($connection, [
            'request_id' => 'req-594',
            'actor_admin_id' => 41,
            'actor_username' => 'operator',
            'action' => 'administrator.status',
            'target_type' => 'admin_user',
            'target_id' => 52,
            'request_method' => 'POST',
            'request_path' => '/agent_admin/administrators/status',
            'request_data' => ['status' => 0, 'password' => 'secret-password', 'authorization' => 'Bearer token'],
            'result' => 'success',
            'http_status' => 200,
        ]);
    }
}
