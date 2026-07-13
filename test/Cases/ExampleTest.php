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

use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class ExampleTest extends TestCase
{
    public function testRootRedirectsToAgentAdminLogin(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        self::assertSame('/agent_admin/login', $response->getHeaderLine('Location'));
    }
}
