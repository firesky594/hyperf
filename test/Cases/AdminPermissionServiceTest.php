<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Rbac\AdminRouteRegistry;
use App\Service\AdminPermissionService;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class AdminPermissionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testSyncAddsRestoresAndDisablesSystemPermissionsWithoutOverwritingCustomRows(): void
    {
        $registry = new AdminRouteRegistry();
        $permissionCount = count(array_filter(
            $registry->definitions(),
            static fn ($definition): bool => $definition->permissionCode !== null
        ));

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('select')->once()->andReturn([
            (object) ['code' => 'dashboard.view', 'source' => 'system', 'status' => 0, 'deleted_at' => '2026-01-01'],
            (object) ['code' => 'old.view', 'source' => 'system', 'status' => 1, 'deleted_at' => null],
            (object) ['code' => 'administrators.view', 'source' => 'custom', 'status' => 1, 'deleted_at' => null],
        ]);

        $inserts = [];
        $updates = [];
        $connection->shouldReceive('insert')->times($permissionCount - 2)->withArgs(function (string $sql, array $bindings) use (&$inserts): bool {
            $inserts[] = [$sql, $bindings];
            return true;
        })->andReturnTrue();
        $connection->shouldReceive('update')->twice()->withArgs(function (string $sql, array $bindings) use (&$updates): bool {
            $updates[] = [$sql, $bindings];
            return true;
        })->andReturn(1);

        $db = Mockery::mock(Db::class);
        $db->shouldReceive('transaction')->once()->andReturnUsing(
            static fn (callable $callback): array => $callback($connection)
        );
        $ids = Mockery::mock(IdGeneratorInterface::class);
        $ids->shouldReceive('generate')->times($permissionCount - 2)->andReturn(594);

        $result = (new AdminPermissionService($db, $registry, $ids))->syncSystemPermissions();

        self::assertSame(['created' => $permissionCount - 2, 'restored' => 1, 'disabled' => 1, 'skipped_custom' => 1], $result);
        self::assertSame(594, $inserts[0][1][0]);
        self::assertContains('administrators.create', $inserts[0][1]);
        self::assertStringContainsString('`deleted_at` = NULL', $updates[0][0]);
        self::assertContains('dashboard.view', $updates[0][1]);
        self::assertStringContainsString('`status` = 0', $updates[1][0]);
        self::assertContains('old.view', $updates[1][1]);
        self::assertStringNotContainsString('DELETE ', implode("\n", array_column($updates, 0)));
    }
}
