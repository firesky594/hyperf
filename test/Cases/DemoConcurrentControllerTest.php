<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Service\DemoConcurrentService;
use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class DemoConcurrentControllerTest extends TestCase
{
    public function testConcurrentDemoEndpointReturnsServicePayload(): void
    {
        $payload = [
            'ok' => true,
            'elapsed_ms' => 3.21,
            'tasks' => [
                'mysql' => [
                    'ok' => true,
                    'name' => 'mysql',
                    'elapsed_ms' => 1.1,
                    'data' => ['value' => 1],
                ],
                'redis' => [
                    'ok' => true,
                    'name' => 'redis',
                    'elapsed_ms' => 1.2,
                    'data' => [
                        'key' => 'demo:concurrent:counter',
                        'value' => 9,
                    ],
                ],
            ],
        ];

        $service = $this->mock(DemoConcurrentService::class);
        $service->shouldReceive('run')->once()->andReturn($payload);

        $this->get('/demo/concurrent')->assertOk()->assertJson($payload);
    }
}
