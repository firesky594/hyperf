<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Command\AdminPermissionSyncCommand;
use App\Service\AdminPermissionService;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** @internal @coversNothing */
final class AdminPermissionSyncCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCommandReportsPermissionSynchronizationCounts(): void
    {
        $service = Mockery::mock(AdminPermissionService::class);
        $service->shouldReceive('syncSystemPermissions')->once()->andReturn([
            'created' => 12,
            'restored' => 2,
            'disabled' => 3,
            'skipped_custom' => 1,
        ]);

        $tester = new CommandTester(new AdminPermissionSyncCommand($service));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('created=12', $tester->getDisplay());
        self::assertStringContainsString('restored=2', $tester->getDisplay());
        self::assertStringContainsString('disabled=3', $tester->getDisplay());
        self::assertStringContainsString('skipped_custom=1', $tester->getDisplay());
    }
}
