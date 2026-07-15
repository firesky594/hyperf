<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Command\AdminSetupCommand;
use App\Service\AdminUserProvisioner;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 * @coversNothing
 */
class AdminSetupCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCommandProvisionsWelkinAndPrintsTemporaryPasswordOnce(): void
    {
        $provisioner = Mockery::mock(AdminUserProvisioner::class);
        $provisioner->shouldReceive('provisionSuperAdmin')->once()->with('welkin')->andReturn([
            'id' => 9100,
            'username' => 'welkin',
            'created' => true,
            'temporary_password' => 'Temp-Password-2026!',
        ]);
        $tester = new CommandTester(new AdminSetupCommand($provisioner));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('welkin', $tester->getDisplay());
        self::assertSame(1, substr_count($tester->getDisplay(), 'Temp-Password-2026!'));
        self::assertStringContainsString('change this password', strtolower($tester->getDisplay()));
    }
}
