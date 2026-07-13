<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Service\AdminAuthService;
use Closure;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 * @coversNothing
 */
class AdminAuthServiceTest extends TestCase
{
    private const USER_QUERY = 'SELECT id, username, password_hash, status FROM admin_users WHERE username = ? LIMIT 1 FOR UPDATE';

    private const ENABLED_QUERY = 'SELECT id, status FROM admin_users WHERE id = ? LIMIT 1 FOR UPDATE';

    private const VALID_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$TTl2OW9KVzJwOVEwenlSZQ$sPhQPy7f9m2UVWryZJPP3sm2aAcfMcGxuXQX3GFGn4w';

    private const NOW = 1_700_000_000;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLoginCreatesHashedRedisSessionForEnabledAdministrator(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');
        $storedKey = null;
        $storedPayload = null;
        $committed = false;

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectAttemptRelease($redis, $failureKey);
        $this->expectTransaction($db, $connection, static function () use (&$committed): void {
            $committed = true;
        });
        $connection->shouldReceive('select')->once()
            ->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([(object) [
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => self::VALID_PASSWORD_HASH,
                'status' => 1,
            ]]);
        $connection->shouldReceive('update')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'UPDATE admin_users SET last_login_at = NOW()')
                && str_contains($sql, 'status = 1')
                && $bindings === [41]
        )->andReturn(1);
        $redis->shouldReceive('setex')->once()->withArgs(
            static function (string $key, int $ttl, string $payload) use (&$storedKey, &$storedPayload, &$committed): bool {
                $storedKey = $key;
                $storedPayload = $payload;

                return $committed && str_starts_with($key, 'admin:session:') && $ttl === 7200;
            }
        )->andReturn(true);

        $result = $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
        $session = json_decode((string) $storedPayload, true, 512, JSON_THROW_ON_ERROR);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $result['token']);
        self::assertSame(7200, $result['expires_in']);
        self::assertSame('admin:session:' . hash('sha256', $result['token']), $storedKey);
        self::assertStringNotContainsString($result['token'], (string) $storedKey);
        self::assertSame(41, $session['admin_id']);
        self::assertSame('root_admin', $session['username']);
        self::assertSame(self::NOW, $session['issued_at']);
        self::assertSame(self::NOW + 7200, $session['expires_at']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $session['csrf_token']);
        self::assertSame($session, $result['session']);
    }

    public function testLoginRejectsWrongPasswordAndRecordsFailure(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()
            ->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => self::VALID_PASSWORD_HASH,
                'status' => 1,
            ]]);
        $redis->shouldReceive('setex')->never();
        $connection->shouldReceive('update')->never();

        try {
            $this->service(
                $db,
                $redis,
                static fn (string $password, string $hash): bool => false
            )->login('root_admin', 'wrong-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
            self::assertStringNotContainsString('root_admin', $failureKey);
            self::assertStringNotContainsString('203.0.113.10', $failureKey);
        }
    }

    public function testLoginRejectsDisabledAdministratorWithSamePublicMessage(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()
            ->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => self::VALID_PASSWORD_HASH,
                'status' => 0,
            ]]);
        $redis->shouldReceive('setex')->never();
        $connection->shouldReceive('update')->never();

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
        }
    }

    public function testLoginRejectsAdministratorDeletedBeforeAuthoritativeLockedRead(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()
            ->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([]);
        $connection->shouldReceive('update')->never();
        $redis->shouldReceive('setex')->never();

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
        }
    }

    public function testLoginVerifiesDummyHashForMissingAdministratorAndReturnsUniformCredentialsError(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('missing_admin', '203.0.113.10');
        $verificationCalls = 0;
        $verifiedHash = null;

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()->with(self::USER_QUERY, ['missing_admin'])->andReturn([]);
        $connection->shouldReceive('update')->never();
        $redis->shouldReceive('setex')->never();

        try {
            $this->service(
                $db,
                $redis,
                static function (string $password, string $hash) use (&$verificationCalls, &$verifiedHash): bool {
                    ++$verificationCalls;
                    $verifiedHash = $hash;

                    return true;
                }
            )->login('missing_admin', 'any-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
        }

        self::assertSame(1, $verificationCalls);
        self::assertIsString($verifiedHash);
        self::assertSame('argon2id', password_get_info($verifiedHash)['algoName']);
        self::assertNotSame(self::VALID_PASSWORD_HASH, $verifiedHash);
    }

    public function testLoginVerifiesDummyHashForMalformedStoredHashAndReturnsUniformCredentialsError(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');
        $verificationCalls = 0;
        $verifiedHash = null;

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => 'not-a-password-hash',
                'status' => 1,
            ]]);
        $connection->shouldReceive('update')->never();
        $redis->shouldReceive('setex')->never();

        try {
            $this->service(
                $db,
                $redis,
                static function (string $password, string $hash) use (&$verificationCalls, &$verifiedHash): bool {
                    ++$verificationCalls;
                    $verifiedHash = $hash;

                    return true;
                }
            )->login('root_admin', 'any-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
        }

        self::assertSame(1, $verificationCalls);
        self::assertIsString($verifiedHash);
        self::assertSame('argon2id', password_get_info($verifiedHash)['algoName']);
        self::assertNotSame(self::VALID_PASSWORD_HASH, $verifiedHash);
    }

    public function testLoginRejectsWhenZeroRowUpdateCannotConfirmEnabledAdministrator(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => self::VALID_PASSWORD_HASH,
                'status' => 1,
            ]]);
        $connection->shouldReceive('update')->once()->andReturn(0);
        $connection->shouldReceive('select')->once()->with(self::ENABLED_QUERY, [41])->andReturn([]);
        $redis->shouldReceive('setex')->never();

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
        }
    }

    public function testLoginAcceptsZeroRowUpdateWhenLockedAdministratorRemainsEnabled(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');
        $committed = false;

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectAttemptRelease($redis, $failureKey);
        $this->expectTransaction($db, $connection, static function () use (&$committed): void {
            $committed = true;
        });
        $connection->shouldReceive('select')->once()->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => self::VALID_PASSWORD_HASH,
                'status' => 1,
            ]]);
        $connection->shouldReceive('update')->once()->andReturn(0);
        $connection->shouldReceive('select')->once()->with(self::ENABLED_QUERY, [41])
            ->andReturn([['id' => 41, 'status' => 1]]);
        $redis->shouldReceive('setex')->once()->withArgs(
            static function (string $key, int $ttl, string $payload) use (&$committed): bool {
                return $committed
                    && str_starts_with($key, 'admin:session:')
                    && $ttl === 7200;
            }
        )->andReturn(true);

        $result = $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');

        self::assertSame(64, strlen($result['token']));
    }

    public function testLoginRejectsUnexpectedAuthoritativeUpdateResultAsUnavailable(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectAttemptRelease($redis, $failureKey);
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => self::VALID_PASSWORD_HASH,
                'status' => 1,
            ]]);
        $connection->shouldReceive('update')->once()->andReturn(2);
        $connection->shouldReceive('select')->never()->with(self::ENABLED_QUERY, [41]);
        $redis->shouldReceive('setex')->never();

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(503, $exception->status());
            self::assertSame('Administrator authentication unavailable.', $exception->publicMessage());
        }
    }

    public function testSixthAttemptIsRateLimited(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $this->expectAttemptReservation($redis, $failureKey, -1);
        $db->shouldReceive('transaction')->never();
        $redis->shouldReceive('get')->never();
        $redis->shouldReceive('incr')->never();
        $redis->shouldReceive('expire')->never();
        $redis->shouldReceive('setex')->never();

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(429, $exception->status());
            self::assertSame('Too many requests.', $exception->publicMessage());
        }
    }

    public function testOnlyOneAttemptCanReserveTheFifthSlotAtomically(): void
    {
        $db = Mockery::mock(Db::class);
        $connection = Mockery::mock(ConnectionInterface::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $redis->shouldReceive('eval')->twice()
            ->withArgs($this->attemptReservationMatcher($failureKey))
            ->andReturn(5, -1);
        $redis->shouldReceive('get')->never();
        $redis->shouldReceive('incr')->never();
        $redis->shouldReceive('expire')->never();
        $redis->shouldReceive('setex')->never();
        $this->expectTransaction($db, $connection);
        $connection->shouldReceive('select')->once()->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => self::VALID_PASSWORD_HASH,
                'status' => 1,
            ]]);
        $connection->shouldReceive('update')->never();

        try {
            $this->service(
                $db,
                $redis,
                static fn (string $password, string $hash): bool => false
            )->login('root_admin', 'wrong-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
        }

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(429, $exception->status());
        }
    }

    public function testLoginReleasesReservedAttemptWhenDatabaseInfrastructureFails(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $this->expectAttemptReservation($redis, $failureKey, 1);
        $this->expectAttemptRelease($redis, $failureKey);
        $db->shouldReceive('transaction')->once()->andThrow(new RuntimeException('database unavailable'));
        $redis->shouldReceive('setex')->never();

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(503, $exception->status());
            self::assertSame('Administrator authentication unavailable.', $exception->publicMessage());
        }
    }

    public function testResolveSessionReturnsValidPayload(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $token = str_repeat('a', 64);
        $session = [
            'admin_id' => 41,
            'username' => 'root_admin',
            'issued_at' => self::NOW - 60,
            'expires_at' => self::NOW - 60 + 7200,
            'csrf_token' => str_repeat('b', 64),
        ];

        $redis->shouldReceive('get')->once()
            ->with($this->sessionKey($token))
            ->andReturn(json_encode($session, JSON_THROW_ON_ERROR));
        $redis->shouldReceive('del')->never();

        self::assertSame($session, $this->service($db, $redis)->resolveSession($token));
    }

    public function testResolveSessionDeletesExpiredOrMalformedPayload(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $expiredToken = str_repeat('c', 64);
        $malformedToken = str_repeat('d', 64);
        $expiredKey = $this->sessionKey($expiredToken);
        $malformedKey = $this->sessionKey($malformedToken);
        $expiredSession = [
            'admin_id' => 41,
            'username' => 'root_admin',
            'issued_at' => self::NOW - 7200,
            'expires_at' => self::NOW,
            'csrf_token' => str_repeat('e', 64),
        ];
        $malformedSession = [
            'admin_id' => 41,
            'username' => 'root_admin',
            'issued_at' => self::NOW,
            'expires_at' => self::NOW + 7200,
            'csrf_token' => str_repeat('g', 64),
        ];

        $redis->shouldReceive('get')->once()->with($expiredKey)
            ->andReturn(json_encode($expiredSession, JSON_THROW_ON_ERROR));
        $redis->shouldReceive('del')->once()->with($expiredKey)->andReturn(1);
        $redis->shouldReceive('get')->once()->with($malformedKey)
            ->andReturn(json_encode($malformedSession, JSON_THROW_ON_ERROR));
        $redis->shouldReceive('del')->once()->with($malformedKey)->andReturn(1);

        $service = $this->service($db, $redis);
        self::assertNull($service->resolveSession($expiredToken));
        self::assertNull($service->resolveSession($malformedToken));
    }

    public function testResolveSessionDeletesPayloadWithLongerThanConfiguredLifetime(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $token = str_repeat('1', 64);
        $key = $this->sessionKey($token);
        $issuedAt = self::NOW - 60;
        $session = [
            'admin_id' => 41,
            'username' => 'root_admin',
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt + 7201,
            'csrf_token' => str_repeat('a', 64),
        ];

        $redis->shouldReceive('get')->once()->with($key)
            ->andReturn(json_encode($session, JSON_THROW_ON_ERROR));
        $redis->shouldReceive('del')->once()->with($key)->andReturn(1);

        self::assertNull($this->service($db, $redis)->resolveSession($token));
    }

    public function testLogoutDeletesHashedSessionKey(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $token = str_repeat('f', 64);
        $deletedKey = null;

        $redis->shouldReceive('del')->once()->withArgs(
            static function (string $key) use (&$deletedKey): bool {
                $deletedKey = $key;

                return true;
            }
        )->andReturn(0);

        $this->service($db, $redis)->logout($token);

        self::assertSame($this->sessionKey($token), $deletedKey);
        self::assertStringNotContainsString($token, (string) $deletedKey);
    }

    private function service(
        Db $db,
        Redis $redis,
        ?callable $passwordVerifier = null,
        ?callable $clock = null
    ): AdminAuthService {
        return new AdminAuthService(
            $db,
            $redis,
            7200,
            5,
            300,
            $passwordVerifier ?? static fn (string $password, string $hash): bool => $password === 'correct-password'
                && $hash === self::VALID_PASSWORD_HASH,
            $clock ?? static fn (): int => self::NOW
        );
    }

    private function sessionKey(string $token): string
    {
        return 'admin:session:' . hash('sha256', $token);
    }

    private function failureKey(string $username, string $clientIp): string
    {
        return 'admin:login:fail:' . hash('sha256', strtolower($username) . chr(0) . $clientIp);
    }

    private function expectAttemptReservation(Redis $redis, string $key, int $result): void
    {
        $redis->shouldReceive('eval')->once()
            ->withArgs($this->attemptReservationMatcher($key))
            ->andReturn($result);
    }

    private function expectAttemptRelease(Redis $redis, string $key): void
    {
        $redis->shouldReceive('eval')->once()->withArgs(
            static fn (string $script, array $arguments, int $numberOfKeys): bool => $arguments === [$key]
                && $numberOfKeys === 1
                && str_contains($script, 'DECR')
                && str_contains($script, 'DEL')
        )->andReturn(1);
    }

    private function attemptReservationMatcher(string $key): Closure
    {
        return static fn (string $script, array $arguments, int $numberOfKeys): bool => $arguments === [$key, 5, 300]
            && $numberOfKeys === 1
            && str_contains($script, 'INCR')
            && str_contains($script, 'TTL')
            && str_contains($script, 'EXPIRE')
            && str_contains($script, 'return -1');
    }

    private function expectTransaction(
        Db $db,
        ConnectionInterface $connection,
        ?callable $afterCommit = null
    ): void {
        $db->shouldReceive('transaction')->once()->withArgs(
            static fn (Closure $callback): bool => true
        )->andReturnUsing(static function (Closure $callback) use ($connection, $afterCommit): mixed {
            $result = $callback($connection);
            if ($afterCommit !== null) {
                $afterCommit();
            }

            return $result;
        });
    }
}
