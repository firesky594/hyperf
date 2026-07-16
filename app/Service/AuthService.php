<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Throwable;

use function Hyperf\Support\env;

class AuthService
{
    private const USER_QUERY = 'SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1';

    private const TOKEN_PREFIX = 'auth:token:';

    private int $tokenTtl;

    private int $lockTtl;

    private $passwordHasher;

    /**
     * 初始化登录认证服务。
     *
     * @param Db $db MySQL 查询入口，用于读取用户记录。
     * @param Redis $redis Redis 客户端，用于写入和删除登录态缓存。
     * @param RedisLock $locks Redis 锁服务，用于控制登录和退出的并发请求。
     * @param IdGeneratorInterface $idGenerator 雪花 ID 生成器，用于生成用户主键。
     * @param null|int $tokenTtl 登录 token 缓存秒数；为空时读取 AUTH_TOKEN_TTL，默认 7200 秒。
     * @param null|int $lockTtl Redis 锁过期秒数；为空时读取 AUTH_LOCK_TTL，默认 10 秒。
     * @param null|callable $passwordHasher 密码 hash 函数；测试可传入轻量 hash，生产默认使用 password_hash。
     */
    public function __construct(
        private Db $db,
        private Redis $redis,
        private RedisLock $locks,
        private IdGeneratorInterface $idGenerator,
        ?int $tokenTtl = null,
        ?int $lockTtl = null,
        ?callable $passwordHasher = null
    ) {
        $this->tokenTtl = $tokenTtl ?? (int) env('AUTH_TOKEN_TTL', 7200);
        $this->lockTtl = $lockTtl ?? (int) env('AUTH_LOCK_TTL', 10);
        $this->passwordHasher = $passwordHasher ?? static fn (string $password): string => password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * 校验账号密码并创建 Redis 登录缓存。
     *
     * 同一用户名会先获取短期 Redis 锁，避免并发登录同时生成多份登录态。
     * 用户不存在和密码错误统一返回 401，避免泄露用户名是否存在。
     * 登录成功后 token 只写入 Redis，不落 MySQL，TTL 到期后自动失效。
     *
     * @param string $username 登录用户名；方法内部会 trim，不能为空。
     * @param string $password 登录密码；方法内部会 trim 检查是否为空，但校验密码时保留原值。
     * @return array{token:string,token_type:string,expires_in:int,user:array{id:int,username:string}} 登录 token 和用户基础信息。
     * @throws AuthException 参数为空、凭据错误、并发登录冲突或认证基础设施异常时抛出。
     */
    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || trim($password) === '') {
            throw AuthException::badRequest('Username and password are required.');
        }

        $handle = $this->locks->acquire($this->loginLockKey($username), $this->lockTtl);
        if (! $handle instanceof RedisLockHandle) {
            throw AuthException::conflict('Login request already in progress.');
        }

        try {
            $user = $this->findUser($username);
            if ($user === null || ! password_verify($password, (string) $user['password_hash'])) {
                throw AuthException::invalidCredentials();
            }

            $token = bin2hex(random_bytes(32));
            $payload = json_encode([
                'user_id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'created_at' => time(),
                'csrf_token' => bin2hex(random_bytes(32)),
            ], JSON_THROW_ON_ERROR);

            $cached = $this->redis->setex(self::TOKEN_PREFIX . $token, $this->tokenTtl, $payload);
            if ($cached !== true && $cached !== 'OK') {
                throw AuthException::server('Unable to cache login token.');
            }

            return [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->tokenTtl,
                'user' => [
                    'id' => (int) $user['id'],
                    'username' => (string) $user['username'],
                ],
            ];
        } catch (AuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AuthException::server('Authentication infrastructure unavailable.', $throwable);
        } finally {
            $this->locks->release($handle);
        }
    }

    /**
     * 注册单个随机测试用户。
     *
     * 该方法每次只生成 1 个测试用户，同步写入 MySQL，并通过 Redis 短锁覆盖真实并发注册路径。
     * 注册成功后不创建登录 token，接口只返回压测所需的明文测试凭据。
     *
     * @return array{status:string,user:array{id:int,username:string,password:string}} 注册状态和随机用户明文测试凭据。
     * @throws AuthException Redis 锁冲突或注册基础设施不可用时抛出。
     */
    public function registerRandom(): array
    {
        try {
            $id = (int) $this->idGenerator->generate();
            $username = $this->randomUsername($id);
            $password = $this->randomPassword();

            $handle = $this->locks->acquire($this->registerLockKey($username), $this->lockTtl);
            if (! $handle instanceof RedisLockHandle) {
                throw AuthException::conflict('Registration request already in progress.');
            }

            try {
                $this->db->transaction(function (ConnectionInterface $connection) use ($id, $username, $password): void {
                    if ($connection->insert('INSERT INTO users (id, username, password_hash, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', [$id, $username, ($this->passwordHasher)($password)]) !== true) { throw AuthException::serviceUnavailable('Registration infrastructure unavailable.'); }
                    if ($connection->insert('INSERT INTO `buyer_profiles` (`id`, `user_id`, `display_name`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, NULL)', [$id, $id, $username]) !== true) { throw AuthException::serviceUnavailable('Registration infrastructure unavailable.'); }
                });

                return [
                    'status' => 'registered',
                    'user' => [
                        'id' => $id,
                        'username' => $username,
                        'password' => $password,
                    ],
                ];
            } finally {
                $this->locks->release($handle);
            }
        } catch (AuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AuthException::serviceUnavailable('Registration infrastructure unavailable.', $throwable);
        }
    }

    /**
     * 删除 Redis 登录缓存并完成退出登录。
     *
     * 退出操作保持幂等：Redis key 不存在时也返回成功。
     * token 可能较长，退出锁 key 使用 sha256(token) 作为后缀，保持 Redis key 稳定。
     * 锁释放由 RedisLock 校验锁 value 后删除，避免误删其他请求刚拿到的锁。
     *
     * @param string $token 登录 token；方法内部会 trim，不能为空。
     * @return array{ok:bool} 退出成功结果。
     * @throws AuthException token 为空、并发退出冲突或认证基础设施异常时抛出。
     */
    public function logout(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw AuthException::badRequest('Token is required.');
        }

        $handle = $this->locks->acquire($this->logoutLockKey($token), $this->lockTtl);
        if (! $handle instanceof RedisLockHandle) {
            throw AuthException::conflict('Logout request already in progress.');
        }

        try {
            $this->redis->del(self::TOKEN_PREFIX . $token);

            return ['ok' => true];
        } catch (Throwable $throwable) {
            throw AuthException::server('Authentication infrastructure unavailable.', $throwable);
        } finally {
            $this->locks->release($handle);
        }
    }

    /** @return null|array{user_id:int,username:string,csrf_token:string} */
    public function resolveToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') { return null; }
        try {
            $payload = $this->redis->get(self::TOKEN_PREFIX . $token);
            if (! is_string($payload) || $payload === '') { return null; }
            $session = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($session) || (int) ($session['user_id'] ?? 0) <= 0 || ! is_string($session['username'] ?? null)) { return null; }
            return ['user_id' => (int) $session['user_id'], 'username' => $session['username'], 'csrf_token' => is_string($session['csrf_token'] ?? null) ? $session['csrf_token'] : ''];
        } catch (Throwable $throwable) { throw AuthException::serviceUnavailable('Authentication infrastructure unavailable.', $throwable); }
    }

    /**
     * 根据用户名查询用户记录。
     *
     * Db::select 在不同驱动配置下可能返回对象或数组，这里统一转换成数组给上层使用。
     *
     * @param string $username 已清洗后的用户名。
     * @return null|array{id:mixed,username:mixed,password_hash:mixed} 查到用户时返回用户数组，否则返回 null。
     */
    private function findUser(string $username): ?array
    {
        $rows = $this->db->select(self::USER_QUERY, [$username]);
        $user = $rows[0] ?? null;
        if ($user === null) {
            return null;
        }

        if (is_object($user)) {
            return get_object_vars($user);
        }

        return is_array($user) ? $user : null;
    }

    /**
     * 生成登录并发锁 key。
     *
     * @param string $username 已清洗后的用户名。
     * @return string Redis 登录锁 key。
     */
    private function loginLockKey(string $username): string
    {
        return 'auth:login-lock:' . $username;
    }

    /**
     * 生成注册并发锁 key。
     *
     * @param string $username 已生成的随机用户名。
     * @return string Redis 注册锁 key。
     */
    private function registerLockKey(string $username): string
    {
        return 'auth:register-lock:' . $username;
    }

    /**
     * 生成退出并发锁 key。
     *
     * @param string $token 已清洗后的登录 token。
     * @return string Redis 退出锁 key，后缀为 token 的 sha256 值。
     */
    private function logoutLockKey(string $token): string
    {
        return 'auth:logout-lock:' . hash('sha256', $token);
    }

    /**
     * 生成随机测试用户名。
     *
     * @param int $id 本次注册用户的雪花 ID。
     * @return string 随机测试用户名。
     */
    private function randomUsername(int $id): string
    {
        return sprintf('test_%d_%s', $id, bin2hex(random_bytes(4)));
    }

    /**
     * 生成随机测试用户密码。
     *
     * @return string 明文随机密码，仅用于测试接口响应。
     */
    private function randomPassword(): string
    {
        return bin2hex(random_bytes(8));
    }
}
