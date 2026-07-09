<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use App\Exception\AuthException;
use App\Service\AuthService;
use App\Service\RedisLock;
use App\Service\RedisLockHandle;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class AuthServiceTest extends TestCase
{
    private const USER_QUERY = 'SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1';

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLoginCachesTokenAndReturnsUserPayload(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);
        $handle = new RedisLockHandle('auth:login-lock:demo', 'lock-value');

        $lock->shouldReceive('acquire')->once()->with('auth:login-lock:demo', 10)->andReturn($handle);
        $db->shouldReceive('select')
            ->once()
            ->with(self::USER_QUERY, ['demo'])
            ->andReturn([(object) [
                'id' => 123456789,
                'username' => 'demo',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            ]]);
        $redis->shouldReceive('setex')
            ->once()
            ->withArgs(static function (string $key, int $ttl, string $payload): bool {
                $session = json_decode($payload, true);

                return str_starts_with($key, 'auth:token:')
                    && $ttl === 7200
                    && $session['user_id'] === 123456789
                    && $session['username'] === 'demo';
            })
            ->andReturn(true);
        $lock->shouldReceive('release')->once()->with($handle)->andReturn(true);

        $result = $this->service($db, $redis, $lock)->login('demo', 'secret');

        self::assertSame('Bearer', $result['token_type']);
        self::assertSame(7200, $result['expires_in']);
        self::assertSame(64, strlen($result['token']));
        self::assertSame(['id' => 123456789, 'username' => 'demo'], $result['user']);
    }

    public function testLoginRejectsInvalidPassword(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);
        $handle = new RedisLockHandle('auth:login-lock:demo', 'lock-value');

        $lock->shouldReceive('acquire')->once()->with('auth:login-lock:demo', 10)->andReturn($handle);
        $db->shouldReceive('select')
            ->once()
            ->with(self::USER_QUERY, ['demo'])
            ->andReturn([(object) [
                'id' => 123456789,
                'username' => 'demo',
                'password_hash' => password_hash('secret', PASSWORD_DEFAULT),
            ]]);
        $redis->shouldReceive('setex')->never();
        $lock->shouldReceive('release')->once()->with($handle)->andReturn(true);

        try {
            $this->service($db, $redis, $lock)->login('demo', 'wrong');
            self::fail('Expected AuthException.');
        } catch (AuthException $exception) {
            self::assertSame(401, $exception->status());
            self::assertSame('Invalid username or password.', $exception->publicMessage());
        }
    }

    public function testLoginReturnsConflictWhenUserLockIsHeld(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);

        $lock->shouldReceive('acquire')->once()->with('auth:login-lock:demo', 10)->andReturn(null);
        $db->shouldReceive('select')->never();
        $redis->shouldReceive('setex')->never();

        try {
            $this->service($db, $redis, $lock)->login('demo', 'secret');
            self::fail('Expected AuthException.');
        } catch (AuthException $exception) {
            self::assertSame(409, $exception->status());
            self::assertSame('Login request already in progress.', $exception->publicMessage());
        }
    }

    public function testLoginRequiresUsernameAndPassword(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);

        $lock->shouldReceive('acquire')->never();

        try {
            $this->service($db, $redis, $lock)->login('', ' ');
            self::fail('Expected AuthException.');
        } catch (AuthException $exception) {
            self::assertSame(400, $exception->status());
            self::assertSame('Username and password are required.', $exception->publicMessage());
        }
    }

    public function testLogoutDeletesTokenAndReturnsSuccess(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);
        $handle = new RedisLockHandle('auth:logout-lock:' . hash('sha256', 'token-123'), 'lock-value');

        $lock->shouldReceive('acquire')->once()->with($handle->key, 10)->andReturn($handle);
        $redis->shouldReceive('del')->once()->with('auth:token:token-123')->andReturn(1);
        $lock->shouldReceive('release')->once()->with($handle)->andReturn(true);

        $result = $this->service($db, $redis, $lock)->logout('token-123');

        self::assertSame(['ok' => true], $result);
    }

    public function testLogoutIsIdempotentWhenTokenIsAlreadyAbsent(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);
        $handle = new RedisLockHandle('auth:logout-lock:' . hash('sha256', 'token-123'), 'lock-value');

        $lock->shouldReceive('acquire')->once()->with($handle->key, 10)->andReturn($handle);
        $redis->shouldReceive('del')->once()->with('auth:token:token-123')->andReturn(0);
        $lock->shouldReceive('release')->once()->with($handle)->andReturn(true);

        $result = $this->service($db, $redis, $lock)->logout('token-123');

        self::assertSame(['ok' => true], $result);
    }

    public function testLogoutReturnsConflictWhenTokenLockIsHeld(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);
        $key = 'auth:logout-lock:' . hash('sha256', 'token-123');

        $lock->shouldReceive('acquire')->once()->with($key, 10)->andReturn(null);
        $redis->shouldReceive('del')->never();

        try {
            $this->service($db, $redis, $lock)->logout('token-123');
            self::fail('Expected AuthException.');
        } catch (AuthException $exception) {
            self::assertSame(409, $exception->status());
            self::assertSame('Logout request already in progress.', $exception->publicMessage());
        }
    }

    public function testLogoutRequiresToken(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);

        $lock->shouldReceive('acquire')->never();

        try {
            $this->service($db, $redis, $lock)->logout(' ');
            self::fail('Expected AuthException.');
        } catch (AuthException $exception) {
            self::assertSame(400, $exception->status());
            self::assertSame('Token is required.', $exception->publicMessage());
        }
    }

    public function testRegisterRandomCreatesOneUserSynchronouslyWithoutLoginToken(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);
        $idGenerator = Mockery::mock(IdGeneratorInterface::class);
        $capturedBindings = [];
        $lockKey = null;

        $idGenerator->shouldReceive('generate')->once()->withNoArgs()->andReturn(100001);
        $lock->shouldReceive('acquire')
            ->once()
            ->withArgs(static function (string $key, int $ttl) use (&$lockKey): bool {
                $lockKey = $key;

                return str_starts_with($key, 'auth:register-lock:test_100001_') && $ttl === 10;
            })
            ->andReturnUsing(static fn (string $key): RedisLockHandle => new RedisLockHandle($key, 'lock-value'));
        $db->shouldReceive('insert')
            ->once()
            ->withArgs(static function (string $sql, array $bindings) use (&$capturedBindings): bool {
                $capturedBindings = $bindings;

                return $sql === 'INSERT INTO users (id, username, password_hash, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
                    && $bindings[0] === 100001
                    && is_string($bindings[1])
                    && preg_match('/^test_100001_[a-f0-9]{8}$/', $bindings[1]) === 1
                    && is_string($bindings[2]);
            })
            ->andReturn(true);
        $redis->shouldReceive('setex')->never();
        $lock->shouldReceive('release')
            ->once()
            ->with(Mockery::on(static function (RedisLockHandle $handle) use (&$lockKey): bool {
                return $handle->key === $lockKey;
            }))
            ->andReturn(true);

        $result = $this->service(
            $db,
            $redis,
            $lock,
            $idGenerator,
            static fn (string $password): string => 'hashed:' . $password
        )->registerRandom();

        self::assertSame('registered', $result['status']);
        self::assertSame(100001, $result['user']['id']);
        self::assertSame($capturedBindings[1], $result['user']['username']);
        self::assertSame('hashed:' . $result['user']['password'], $capturedBindings[2]);
        self::assertArrayNotHasKey('token', $result);
    }

    public function testRegisterRandomReturnsConflictWhenGeneratedUserLockIsHeld(): void
    {
        $db = Mockery::mock(Db::class);
        $redis = Mockery::mock(Redis::class);
        $lock = Mockery::mock(RedisLock::class);
        $idGenerator = Mockery::mock(IdGeneratorInterface::class);

        $idGenerator->shouldReceive('generate')->once()->withNoArgs()->andReturn(100001);
        $lock->shouldReceive('acquire')
            ->once()
            ->withArgs(static fn (string $key, int $ttl): bool => str_starts_with($key, 'auth:register-lock:test_100001_') && $ttl === 10)
            ->andReturn(null);
        $db->shouldReceive('insert')->never();
        $redis->shouldReceive('setex')->never();

        try {
            $this->service($db, $redis, $lock, $idGenerator)->registerRandom();
            self::fail('Expected AuthException.');
        } catch (AuthException $exception) {
            self::assertSame(409, $exception->status());
            self::assertSame('Registration request already in progress.', $exception->publicMessage());
        }
    }

    private function service(
        Db $db,
        Redis $redis,
        RedisLock $lock,
        ?IdGeneratorInterface $idGenerator = null,
        ?callable $passwordHasher = null
    ): AuthService
    {
        return new AuthService(
            $db,
            $redis,
            $lock,
            $idGenerator ?? Mockery::mock(IdGeneratorInterface::class),
            7200,
            10,
            $passwordHasher
        );
    }
}
