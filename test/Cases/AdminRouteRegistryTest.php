<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Rbac\AdminRouteDefinition;
use App\Rbac\AdminRouteRegistry;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AdminRouteRegistryTest extends TestCase
{
    public function testDefinitionsUseUniquePermissionCodesAndAdminPaths(): void
    {
        $definitions = (new AdminRouteRegistry())->definitions();
        self::assertNotEmpty($definitions);
        $permissions = [];

        foreach ($definitions as $definition) {
            self::assertInstanceOf(AdminRouteDefinition::class, $definition);
            self::assertContains($definition->method, ['GET', 'POST']);
            self::assertStringStartsWith('/agent_admin', $definition->path);
            self::assertNotSame('', $definition->handler);
            if ($definition->permissionCode === null) {
                continue;
            }

            self::assertMatchesRegularExpression('/^[a-z][a-z0-9_.-]{2,127}$/D', $definition->permissionCode);
            self::assertNotContains($definition->permissionCode, $permissions);
            $permissions[] = $definition->permissionCode;
        }

        self::assertContains('dashboard.view', $permissions);
        self::assertContains('administrators.view', $permissions);
        self::assertContains('roles.view', $permissions);
        self::assertContains('permissions.view', $permissions);
        self::assertContains('menus.view', $permissions);
        self::assertContains('audit.view', $permissions);
    }
}
