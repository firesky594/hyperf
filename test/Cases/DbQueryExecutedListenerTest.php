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

use App\Listener\DbQueryExecutedListener;
use Hyperf\Database\ConnectionInterface;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Logger\LoggerFactory;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * @internal
 * @coversNothing
 */
class DbQueryExecutedListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testProcessLogsSqlTemplateAndTimeWithoutAnyBindingValues(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };
        $factory = Mockery::mock(LoggerFactory::class);
        $factory->shouldReceive('get')->once()->with('sql')->andReturn($logger);
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->once()->with(LoggerFactory::class)->andReturn($factory);
        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->shouldReceive('getName')->twice()->andReturn('default');
        $listener = new DbQueryExecutedListener($container);

        $positionalSql = 'INSERT INTO admin_users (username, note, password_hash) VALUES (?, ?, ?)';
        $positionalBindings = [
            'ordinary-binding-secret',
            "O'Reilly binding secret",
            '$argon2id$v=19$m=65536,t=4,p=1$positionalSalt$positionalHash',
        ];
        $associativeSql = 'UPDATE admin_users SET username = ?, password_hash = ? WHERE external_id = ?';
        $associativeBindings = [
            'username' => 'associative-binding-secret',
            'password_hash' => '$argon2id$v=19$m=65536,t=4,p=1$associativeSalt$associativeHash',
            'external_id' => 'associative-id-secret',
        ];

        $listener->process(new QueryExecuted($positionalSql, $positionalBindings, 12.34, $connection));
        $listener->process(new QueryExecuted($associativeSql, $associativeBindings, 8.5, $connection));

        self::assertSame([
            [
                'level' => 'info',
                'message' => '[12.34] ' . $positionalSql,
                'context' => [],
            ],
            [
                'level' => 'info',
                'message' => '[8.5] ' . $associativeSql,
                'context' => [],
            ],
        ], $logger->records);

        $loggedMessages = implode("\n", array_column($logger->records, 'message'));
        foreach (array_merge($positionalBindings, array_values($associativeBindings)) as $binding) {
            self::assertStringNotContainsString($binding, $loggedMessages);
        }
    }
}
