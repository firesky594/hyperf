<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Service\AdminSchemaService;
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
        $schema = Mockery::mock(AdminSchemaService::class);
        $schema->shouldReceive('ensureSchema')->once();
        $db->shouldReceive('select')->once()
            ->with('SELECT id FROM admin_users WHERE username = ? LIMIT 1', ['root_admin'])
            ->andReturn([]);
        $ids->shouldReceive('generate')->once()->andReturn(9001);
        $db->shouldReceive('insert')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'INSERT INTO admin_users')
                && $bindings === [9001, 'root_admin', 'hashed:strong-password']
        )->andReturn(true);
        $service = new AdminUserProvisioner($db, $ids, $schema, static fn (string $value): string => 'hashed:' . $value);
        self::assertSame(
            ['id' => 9001, 'username' => 'root_admin', 'created' => true],
            $service->provision('root_admin', 'strong-password')
        );
    }

    public function testProvisionSuperAdminCreatesWelkinWithOneTimeTemporaryPassword(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $schema = Mockery::mock(AdminSchemaService::class);
        $schema->shouldReceive('ensureSchema')->once();
        $db->shouldReceive('select')->once()
            ->with('SELECT id FROM admin_users WHERE username = ? LIMIT 1', ['welkin'])
            ->andReturn([]);
        $ids->shouldReceive('generate')->once()->andReturn(9100);
        $db->shouldReceive('insert')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'INSERT INTO admin_users')
                && str_contains($sql, 'is_super_admin')
                && str_contains($sql, 'must_change_password')
                && $bindings === [9100, 'welkin', 'hashed:Temp-Password-2026!']
        )->andReturn(true);

        $service = new AdminUserProvisioner(
            $db,
            $ids,
            $schema,
            static fn (string $value): string => 'hashed:' . $value,
            static fn (): string => 'Temp-Password-2026!'
        );

        self::assertSame([
            'id' => 9100,
            'username' => 'welkin',
            'created' => true,
            'temporary_password' => 'Temp-Password-2026!',
        ], $service->provisionSuperAdmin());
    }

    public function testProvisionSuperAdminRepairsExistingWelkinAndRotatesTemporaryPassword(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $schema = Mockery::mock(AdminSchemaService::class);
        $schema->shouldReceive('ensureSchema')->once();
        $db->shouldReceive('select')->once()->andReturn([(object) ['id' => 77]]);
        $ids->shouldReceive('generate')->never();
        $db->shouldReceive('update')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'status = 1')
                && str_contains($sql, 'is_super_admin = 1')
                && str_contains($sql, 'must_change_password = 1')
                && str_contains($sql, 'deleted_at = NULL')
                && $bindings === ['hashed:Rotated-Password-2026!', 'welkin']
        )->andReturn(1);

        $service = new AdminUserProvisioner(
            $db,
            $ids,
            $schema,
            static fn (string $value): string => 'hashed:' . $value,
            static fn (): string => 'Rotated-Password-2026!'
        );

        self::assertSame([
            'id' => 77,
            'username' => 'welkin',
            'created' => false,
            'temporary_password' => 'Rotated-Password-2026!',
        ], $service->provisionSuperAdmin());
    }

    public function testDefaultHasherUsesPasswordBytesAfterBcryptLimit(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $schema = Mockery::mock(AdminSchemaService::class);
        $schema->shouldReceive('ensureSchema')->once();
        $passwordHash = null;
        $password = str_repeat('a', 72) . '-original-suffix';
        $differentPassword = str_repeat('a', 72) . '-different-suffix';

        $db->shouldReceive('select')->once()->andReturn([]);
        $ids->shouldReceive('generate')->once()->andReturn(9002);
        $db->shouldReceive('insert')->once()->withArgs(
            static function (string $sql, array $bindings) use (&$passwordHash): bool {
                $passwordHash = $bindings[2];

                return str_contains($sql, 'INSERT INTO admin_users');
            }
        )->andReturn(true);

        (new AdminUserProvisioner($db, $ids, $schema))->provision('root_admin', $password);

        self::assertIsString($passwordHash);
        self::assertTrue(password_verify($password, $passwordHash));
        self::assertFalse(password_verify($differentPassword, $passwordHash));
    }

    public function testProvisionResetsExistingAdministrator(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $schema = Mockery::mock(AdminSchemaService::class);
        $schema->shouldReceive('ensureSchema')->once();
        $db->shouldReceive('select')->once()->andReturn([(object) ['id' => 41]]);
        $ids->shouldReceive('generate')->never();
        $db->shouldReceive('update')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'status = 1')
                && $bindings === ['hashed:new-password-123', 'root_admin']
        )->andReturn(1);
        $service = new AdminUserProvisioner($db, $ids, $schema, static fn (string $value): string => 'hashed:' . $value);
        self::assertSame(
            ['id' => 41, 'username' => 'root_admin', 'created' => false],
            $service->provision('root_admin', 'new-password-123')
        );
    }

    public function testProvisionRejectsInvalidInputBeforeDatabaseAccess(): void
    {
        $db = Mockery::mock(Db::class);
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $schema = Mockery::mock(AdminSchemaService::class);
        $schema->shouldReceive('ensureSchema')->never();
        $this->expectException(AdminAuthException::class);
        (new AdminUserProvisioner($db, $ids, $schema))->provision('bad name', 'short');
    }
}
