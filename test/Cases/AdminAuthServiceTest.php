<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AdminAuthException;
use App\Service\AdminAuthService;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AdminAuthServiceTest extends TestCase
{
    private const USER_QUERY = 'SELECT id, username, password_hash, status FROM admin_users WHERE username = ? LIMIT 1';

    private const NOW = 1_700_000_000;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLoginCreatesHashedRedisSessionForEnabledAdministrator(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');
        $storedKey = null;
        $storedPayload = null;

        $redis->shouldReceive('get')->once()->with($failureKey)->andReturn(false);
        $db->shouldReceive('select')->once()
            ->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([(object) [
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => 'stored-hash',
                'status' => 1,
            ]]);
        $db->shouldReceive('update')->once()->withArgs(
            static fn (string $sql, array $bindings): bool => str_contains($sql, 'UPDATE admin_users SET last_login_at = NOW()')
                && $bindings === [41]
        )->andReturn(1);
        $redis->shouldReceive('setex')->once()->withArgs(
            static function (string $key, int $ttl, string $payload) use (&$storedKey, &$storedPayload): bool {
                $storedKey = $key;
                $storedPayload = $payload;

                return str_starts_with($key, 'admin:session:') && $ttl === 7200;
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
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $redis->shouldReceive('get')->once()->with($failureKey)->andReturn(false);
        $db->shouldReceive('select')->once()
            ->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => 'stored-hash',
                'status' => 1,
            ]]);
        $redis->shouldReceive('incr')->once()->with($failureKey)->andReturn(1);
        $redis->shouldReceive('expire')->once()->with($failureKey, 300)->andReturn(true);
        $redis->shouldReceive('setex')->never();
        $db->shouldReceive('update')->never();

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
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $redis->shouldReceive('get')->once()->with($failureKey)->andReturn('0');
        $db->shouldReceive('select')->once()
            ->with(self::USER_QUERY, ['root_admin'])
            ->andReturn([[
                'id' => 41,
                'username' => 'root_admin',
                'password_hash' => 'stored-hash',
                'status' => 0,
            ]]);
        $redis->shouldReceive('incr')->once()->with($failureKey)->andReturn(1);
        $redis->shouldReceive('expire')->once()->with($failureKey, 300)->andReturn(true);
        $redis->shouldReceive('setex')->never();
        $db->shouldReceive('update')->never();

        try {
            $this->service($db, $redis)->login('root_admin', 'correct-password', '203.0.113.10');
            self::fail('Expected AdminAuthException.');
        } catch (AdminAuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
        }
    }

    public function testSixthAttemptIsRateLimited(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $failureKey = $this->failureKey('root_admin', '203.0.113.10');

        $redis->shouldReceive('get')->once()->with($failureKey)->andReturn('5');
        $db->shouldReceive('select')->never();
        $db->shouldReceive('update')->never();
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

    public function testResolveSessionReturnsValidPayload(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $token = str_repeat('a', 64);
        $session = [
            'admin_id' => 41,
            'username' => 'root_admin',
            'issued_at' => self::NOW - 60,
            'expires_at' => self::NOW + 60,
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
            'issued_at' => self::NOW - 120,
            'expires_at' => self::NOW,
            'csrf_token' => str_repeat('e', 64),
        ];
        $malformedSession = [
            'admin_id' => 41,
            'username' => 'root_admin',
            'issued_at' => self::NOW,
            'expires_at' => self::NOW + 120,
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
                && $hash === 'stored-hash',
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
}
