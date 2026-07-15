<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\AdminAuthService;
use App\Service\AdminPasswordService;
use Closure;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AdminPasswordServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testChangePasswordVerifiesCurrentPasswordAndRevokesAllSessions(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $auth = Mockery::mock(AdminAuthService::class);
        $db->shouldReceive('transaction')->once()->withArgs(
            static fn (Closure $callback): bool => true
        )->andReturnUsing(static fn (Closure $callback): mixed => $callback($connection));
        $connection->shouldReceive('select')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'FOR UPDATE')
                && str_contains($sql, 'deleted_at IS NULL')
                && $bindings === [41]
        )->andReturn([[
            'id' => 41,
            'password_hash' => 'stored-hash',
            'status' => 1,
        ]]);
        $connection->shouldReceive('update')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'must_change_password = 0')
                && $bindings === ['hashed:new-strong-password', 41]
        )->andReturn(1);
        $auth->shouldReceive('revokeAdminSessions')->once()->with(41);

        $service = new AdminPasswordService(
            $db,
            $auth,
            static fn (string $plain, string $hash): bool => $plain === 'current-password' && $hash === 'stored-hash',
            static fn (string $plain): string => 'hashed:' . $plain
        );

        $service->changePassword(41, 'current-password', 'new-strong-password');
        self::addToAssertionCount(1);
    }
}
