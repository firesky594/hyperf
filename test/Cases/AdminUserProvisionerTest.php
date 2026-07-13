<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Service\AdminUserProvisioner;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AdminUserProvisionerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testProvisionCreatesTableAndAdministrator(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $db->shouldReceive('statement')->once()->with(Mockery::on(
            static fn (string $sql): bool => str_contains($sql, 'CREATE TABLE IF NOT EXISTS `admin_users`')
                && str_contains($sql, 'uniq_admin_users_username')
        ))->andReturn(true);
        $db->shouldReceive('select')->once()
            ->with('SELECT id FROM admin_users WHERE username = ? LIMIT 1', ['root_admin'])
            ->andReturn([]);
        $ids->shouldReceive('generate')->once()->andReturn(9001);
        $db->shouldReceive('insert')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'INSERT INTO admin_users')
                && $bindings === [9001, 'root_admin', 'hashed:strong-password']
        )->andReturn(true);
        $service = new AdminUserProvisioner($db, $ids, static fn (string $value): string => 'hashed:' . $value);
        self::assertSame(
            ['id' => 9001, 'username' => 'root_admin', 'created' => true],
            $service->provision('root_admin', 'strong-password')
        );
    }

    public function testProvisionResetsExistingAdministrator(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $db->shouldReceive('statement')->once()->andReturn(true);
        $db->shouldReceive('select')->once()->andReturn([(object) ['id' => 41]]);
        $ids->shouldReceive('generate')->never();
        $db->shouldReceive('update')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'status = 1')
                && $bindings === ['hashed:new-password-123', 'root_admin']
        )->andReturn(1);
        $service = new AdminUserProvisioner($db, $ids, static fn (string $value): string => 'hashed:' . $value);
        self::assertSame(
            ['id' => 41, 'username' => 'root_admin', 'created' => false],
            $service->provision('root_admin', 'new-password-123')
        );
    }

    public function testProvisionRejectsInvalidInputBeforeDatabaseAccess(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $db->shouldReceive('statement')->never();
        $this->expectException(AdminAuthException::class);
        (new AdminUserProvisioner($db, $ids))->provision('bad name', 'short');
    }
}
