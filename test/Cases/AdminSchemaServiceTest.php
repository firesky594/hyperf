<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\AdminSchemaService;
use Hyperf\DbConnection\Db;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AdminSchemaServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testEnsureSchemaCreatesAllAdministratorTablesWithLifecycleColumns(): void
    {
        $db = Mockery::mock(Db::class);
        $statements = [];
        $db->shouldReceive('select')->once()->with('SHOW COLUMNS FROM `admin_users`')->andReturn([
            (object) ['Field' => 'is_super_admin'], (object) ['Field' => 'must_change_password'], (object) ['Field' => 'deleted_at'],
        ]);
        $db->shouldReceive('statement')->atLeast()->times(7)->withArgs(
            static function (string $sql) use (&$statements): bool {
                $statements[] = $sql;

                return true;
            }
        )->andReturn(true);

        (new AdminSchemaService($db))->ensureSchema();

        $sql = implode("\n", $statements);
        foreach ([
            'admin_users',
            'admin_roles',
            'admin_permissions',
            'admin_user_roles',
            'admin_role_permissions',
            'admin_menus',
            'admin_audit_logs',
        ] as $table) {
            self::assertStringContainsString("CREATE TABLE IF NOT EXISTS `{$table}`", $sql);
        }

        foreach ($statements as $statement) {
            if (! str_contains($statement, 'CREATE TABLE IF NOT EXISTS')) {
                continue;
            }

            self::assertStringContainsString('`created_at`', $statement);
            self::assertStringContainsString('`updated_at`', $statement);
            self::assertStringContainsString('`deleted_at`', $statement);
        }

        self::assertStringContainsString('`is_super_admin`', $sql);
        self::assertStringContainsString('`must_change_password`', $sql);
        self::assertStringContainsString('uniq_admin_user_role', $sql);
        self::assertStringContainsString('uniq_admin_role_permission', $sql);
        self::assertStringContainsString('idx_admin_audit_actor_created', $sql);
        self::assertStringContainsString('idx_admin_audit_action_created', $sql);
        self::assertStringContainsString('idx_admin_audit_request_id', $sql);
        self::assertStringNotContainsString('ADD COLUMN IF NOT EXISTS', $sql);
    }

    public function testAddsMissingLegacyColumnsWithoutUnsupportedIfNotExistsSyntax(): void
    {
        $db = Mockery::mock(Db::class); $statements = [];
        $db->shouldReceive('select')->once()->andReturn([(object) ['Field' => 'deleted_at']]);
        $db->shouldReceive('statement')->withArgs(function (string $sql) use (&$statements): bool { $statements[] = $sql; return true; })->andReturnTrue();
        (new AdminSchemaService($db))->ensureSchema();
        $sql = implode("\n", $statements);
        self::assertStringContainsString('ADD COLUMN `is_super_admin`', $sql);
        self::assertStringContainsString('ADD COLUMN `must_change_password`', $sql);
        self::assertStringNotContainsString('ADD COLUMN IF NOT EXISTS', $sql);
    }
}
