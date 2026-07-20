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

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use JsonException;
use Throwable;

use function Hyperf\Support\env;

/** 完成管理员凭据校验、会话签发、读取和撤销。 */
class AdminAuthService
{
    private const USER_QUERY = 'SELECT id, username, password_hash, status, is_super_admin, must_change_password '
        . 'FROM admin_users WHERE username = ? AND deleted_at IS NULL LIMIT 1 FOR UPDATE';

    private const ENABLED_QUERY = 'SELECT id, status FROM admin_users WHERE id = ? LIMIT 1 FOR UPDATE';

    private const SESSION_PREFIX = 'admin:session:';

    private const SESSION_REGISTRY_PREFIX = 'admin:sessions:';

    private const FAILURE_PREFIX = 'admin:login:fail:';

    private const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$dzRMa1Iua1ovSjRyNTk3NA$fan7A8YiM2t8b2PtiRPArU7SCT4zWAiNkzo4/kj2F8w';

    private const ATTEMPT_RESERVATION_SCRIPT = <<<'LUA'
-- KEYS[1]：已确认的失败记录；KEYS[2]：尚未完成校验的活跃预留记录。
-- ARGV[1]：本次预留标识；ARGV[2]：最大尝试次数；ARGV[3]：限制窗口（秒）。
-- 使用 Redis 服务器时间，避免应用服务器之间存在时钟偏差。
local now_parts = redis.call("TIME")
local now = tonumber(now_parts[1]) + (tonumber(now_parts[2]) / 1000000)
local maximum = tonumber(ARGV[2])
local window = tonumber(ARGV[3])

-- 同一限制窗口内的失败记录必须共享最早确定的截止时间。
-- 如果历史数据的分值不一致，这里统一分值，防止部分记录提前或延后过期。
local failure_entries = redis.call("ZRANGE", KEYS[1], 0, -1, "WITHSCORES")
local failure_deadline = nil
if #failure_entries > 0 then
    failure_deadline = tonumber(failure_entries[2])
    for index = 1, #failure_entries, 2 do
        redis.call("ZADD", KEYS[1], "XX", failure_deadline, failure_entries[index])
    end
end

-- 删除已经过期的失败记录，并让整个有序集合在窗口截止时自动释放。
redis.call("ZREMRANGEBYSCORE", KEYS[1], "-inf", now)
if failure_deadline ~= nil and failure_deadline > now then
    redis.call("PEXPIREAT", KEYS[1], math.ceil(failure_deadline * 1000))
end

-- 清理超时的活跃预留，并根据最后一个活跃预留的截止时间刷新集合过期时间。
redis.call("ZREMRANGEBYSCORE", KEYS[2], "-inf", now)
local latest_active = redis.call("ZRANGE", KEYS[2], -1, -1, "WITHSCORES")
if #latest_active > 0 then
    redis.call("PEXPIREAT", KEYS[2], math.ceil(tonumber(latest_active[2]) * 1000))
end

-- 已失败次数与并发中的预留次数共同占用尝试额度，避免并发请求绕过限制。
local current = redis.call("ZCARD", KEYS[1]) + redis.call("ZCARD", KEYS[2])
if current >= maximum then
    return -1
end

-- 原子写入本次预留；重复的预留标识视为状态异常。
local added = redis.call("ZADD", KEYS[2], "NX", now + window, ARGV[1])
if added ~= 1 then
    return -2
end

redis.call("PEXPIREAT", KEYS[2], math.ceil((now + window) * 1000))
return 1
LUA;

    private const ATTEMPT_FAILURE_SCRIPT = <<<'LUA'
-- KEYS[1]：已确认的失败记录；KEYS[2]：尚未完成校验的活跃预留记录。
-- ARGV[1]：活跃预留标识；ARGV[2]：对应的失败标识；ARGV[3]：限制窗口（秒）。
-- 使用 Redis 服务器时间，保证窗口计算与预留脚本使用同一时间源。
local now_parts = redis.call("TIME")
local now = tonumber(now_parts[1]) + (tonumber(now_parts[2]) / 1000000)
local window = tonumber(ARGV[3])

-- 首次失败开启固定窗口；后续失败沿用首条失败记录的截止时间。
local first_failure = redis.call("ZRANGE", KEYS[1], 0, -1, "WITHSCORES")
local deadline
if #first_failure == 0 then
    deadline = now + window
else
    deadline = tonumber(first_failure[2])
end

-- 修正同一窗口内可能不一致的历史分值，使所有失败记录同时失效。
if #first_failure > 0 then
    for index = 1, #first_failure, 2 do
        redis.call("ZADD", KEYS[1], "XX", deadline, first_failure[index])
    end
end

-- 移除两个集合中的过期成员；若原窗口已经结束，则从当前时间开启新窗口。
redis.call("ZREMRANGEBYSCORE", KEYS[1], "-inf", now)
redis.call("ZREMRANGEBYSCORE", KEYS[2], "-inf", now)
if deadline <= now then
    deadline = now + window
end

-- 已经转换为失败记录时按幂等成功处理，同时清除可能残留的活跃预留。
local existing = redis.call("ZSCORE", KEYS[1], ARGV[2])
if existing then
    deadline = tonumber(existing)
    redis.call("ZREM", KEYS[2], ARGV[1])
    redis.call("PEXPIREAT", KEYS[1], math.ceil(deadline * 1000))
    return 1
end

-- 只有仍持有本次活跃预留，才能将它转换成失败记录。
local active = redis.call("ZSCORE", KEYS[2], ARGV[1])
if not active then
    return 0
end

-- 原子完成“释放预留并登记失败”；重复失败标识视为状态异常。
redis.call("ZREM", KEYS[2], ARGV[1])
local added = redis.call("ZADD", KEYS[1], "NX", deadline, ARGV[2])
if added ~= 1 then
    return -2
end

redis.call("PEXPIREAT", KEYS[1], math.ceil(deadline * 1000))
return 1
LUA;

    private const ATTEMPT_RELEASE_SCRIPT = <<<'LUA'
return redis.call("ZREM", KEYS[1], ARGV[1])
LUA;

    private const ATTEMPT_SUCCESS_SCRIPT = <<<'LUA'
local active = redis.call("ZSCORE", KEYS[2], ARGV[1])
if not active then
    return 0
end

redis.call("ZREM", KEYS[2], ARGV[1])
redis.call("DEL", KEYS[1])
return 1
LUA;

    private int $tokenTtl;

    private int $maxAttempts;

    private int $loginWindow;

    private $passwordVerifier;

    private $clock;

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @param Redis $redis Redis 客户端实例。
     * @param ?int $tokenTtl 传入的 ?int 实例，用于初始化当前组件所需的依赖。
     * @param ?int $maxAttempts 传入的 ?int 实例，用于初始化当前组件所需的依赖。
     * @param ?int $loginWindow 传入的 ?int 实例，用于初始化当前组件所需的依赖。
     * @param ?callable $passwordVerifier 用于执行指定处理逻辑的回调。
     * @param ?callable $clock 用于执行指定处理逻辑的回调。
     * @return void 无返回值。
     */
    public function __construct(
        private Db $db,
        private Redis $redis,
        ?int $tokenTtl = null,
        ?int $maxAttempts = null,
        ?int $loginWindow = null,
        ?callable $passwordVerifier = null,
        ?callable $clock = null
    ) {
        $this->tokenTtl = max(1, $tokenTtl ?? (int) env('ADMIN_AUTH_TOKEN_TTL', 7200));
        $this->maxAttempts = max(1, $maxAttempts ?? (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', 5));
        $this->loginWindow = max(1, $loginWindow ?? (int) env('ADMIN_LOGIN_WINDOW', 300));
        $this->passwordVerifier = $passwordVerifier
            ?? static fn (string $password, string $hash): bool => password_verify($password, $hash);
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * 校验凭据并建立登录会话。
     *
     * @param string $username 登录用户名。
     * @param string $password 登录密码明文。
     * @param string $clientIp clientIp字符串。
     * @return array{ 返回登录结构化数据。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     * @throws Throwable 底层处理失败并重新抛出原异常。
     */
    public function login(string $username, string $password, string $clientIp): array
    {
        $this->validateLoginInput($username, $password);

        $failureKey = $this->failureKey($username, $clientIp);
        $activeReservationKey = $this->activeReservationKey($failureKey);
        $reservationId = null;
        $sessionKey = null;
        $registryKey = null;

        try {
            $reservationId = bin2hex(random_bytes(32));
            $this->reserveAttempt($failureKey, $activeReservationKey, $reservationId);

            $user = $this->authenticateInTransaction($username, $password);

            $now = (int) ($this->clock)();
            $token = bin2hex(random_bytes(32));
            $session = [
                'admin_id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'issued_at' => $now,
                'expires_at' => $now + $this->tokenTtl,
                'csrf_token' => bin2hex(random_bytes(32)),
                'is_super_admin' => (int) ($user['is_super_admin'] ?? 0) === 1,
                'must_change_password' => (int) ($user['must_change_password'] ?? 0) === 1,
            ];
            $payload = json_encode($session, JSON_THROW_ON_ERROR);

            $sessionKey = $this->sessionKey($token);
            $cached = $this->redis->setex($sessionKey, $this->tokenTtl, $payload);
            if ($cached !== true && $cached !== 'OK') {
                throw AdminAuthException::unavailable();
            }
            $registryKey = self::SESSION_REGISTRY_PREFIX . (int) $user['id'];
            if ($this->redis->sAdd($registryKey, $sessionKey) !== 1) {
                throw AdminAuthException::unavailable();
            }
            if ($this->redis->expire($registryKey, $this->tokenTtl) !== true) {
                throw AdminAuthException::unavailable();
            }

            $this->clearAttemptsAfterSuccess($failureKey, $activeReservationKey, $reservationId);
            $reservationId = null;

            return [
                'token' => $token,
                'expires_in' => $this->tokenTtl,
                'session' => $session,
            ];
        } catch (AdminAuthException $exception) {
            if ($sessionKey !== null) {
                $this->bestEffortDeleteSession($sessionKey);
            }
            if ($registryKey !== null && $sessionKey !== null) {
                $this->bestEffortRemoveSessionFromRegistry($registryKey, $sessionKey);
            }

            if ($reservationId !== null && $exception->status() === 401) {
                try {
                    $this->finalizeFailure($failureKey, $activeReservationKey, $reservationId);
                    $reservationId = null;
                } catch (Throwable $throwable) {
                    $this->bestEffortFinalizeFailure($failureKey, $activeReservationKey, $reservationId);
                    if ($throwable instanceof AdminAuthException) {
                        throw $throwable;
                    }

                    throw AdminAuthException::unavailable(previous: $throwable);
                }
            } elseif ($reservationId !== null && $exception->status() !== 429) {
                $this->bestEffortRelease($activeReservationKey, $reservationId);
            }

            throw $exception;
        } catch (Throwable $throwable) {
            if ($sessionKey !== null) {
                $this->bestEffortDeleteSession($sessionKey);
            }
            if ($registryKey !== null && $sessionKey !== null) {
                $this->bestEffortRemoveSessionFromRegistry($registryKey, $sessionKey);
            }

            if ($reservationId !== null) {
                $this->bestEffortRelease($activeReservationKey, $reservationId);
            }

            throw AdminAuthException::unavailable(previous: $throwable);
        }
    }

    /**
     * 解析会话。
     *
     * @param string $token 待校验或撤销的访问令牌。
     * @return null|array{admin_id:int,username:string,issued_at:int,expires_at:int,csrf_token:string} 查询成功时返回对应数据，不存在时返回 null。
     * @throws Throwable 底层处理失败并重新抛出原异常。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function resolveSession(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            return null;
        }

        try {
            $key = $this->sessionKey($token);
            $payload = $this->redis->get($key);
            if ($payload === false || $payload === null) {
                return null;
            }

            if (! is_string($payload)) {
                $this->deleteSession($key);

                return null;
            }

            try {
                $session = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $this->deleteSession($key);

                return null;
            }

            $now = (int) ($this->clock)();
            if (! $this->isValidSession($session, $now)) {
                $this->deleteSession($key);

                return null;
            }

            return $session;
        } catch (AdminAuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable(previous: $throwable);
        }
    }

    /**
     * 注销当前登录会话。
     *
     * @param string $token 待校验或撤销的访问令牌。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     * @throws Throwable 底层处理失败并重新抛出原异常。
     */
    public function logout(string $token): void
    {
        try {
            $deleted = $this->redis->del($this->sessionKey($token));
            if ($deleted === false) {
                throw AdminAuthException::unavailable();
            }
        } catch (AdminAuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable(previous: $throwable);
        }
    }

    /**
     * 撤销管理员Sessions。
     *
     * @param int $adminId 对应业务记录的唯一标识。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     * @throws Throwable 底层处理失败并重新抛出原异常。
     */
    public function revokeAdminSessions(int $adminId): void
    {
        if ($adminId <= 0) {
            throw AdminAuthException::validation();
        }

        try {
            $registryKey = self::SESSION_REGISTRY_PREFIX . $adminId;
            $sessionKeys = $this->redis->sMembers($registryKey);
            if (! is_array($sessionKeys)) {
                throw AdminAuthException::unavailable();
            }

            foreach ($sessionKeys as $sessionKey) {
                if (
                    ! is_string($sessionKey)
                    || preg_match('/^admin:session:[a-f0-9]{64}$/D', $sessionKey) !== 1
                ) {
                    continue;
                }

                if ($this->redis->del($sessionKey) === false) {
                    throw AdminAuthException::unavailable();
                }
            }

            if ($this->redis->del($registryKey) === false) {
                throw AdminAuthException::unavailable();
            }
        } catch (AdminAuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable(previous: $throwable);
        }
    }

    /**
     * 处理authenticateInTransaction。
     *
     * @param string $username 登录用户名。
     * @param string $password 登录密码明文。
     * @return array<string,mixed> 返回authenticateInTransaction结构化数据。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function authenticateInTransaction(string $username, string $password): array
    {
        return $this->db->transaction(function (ConnectionInterface $connection) use ($username, $password): array {
            $user = $this->findUser($connection, $username);
            $storedHash = $user['password_hash'] ?? null;
            $hashValid = is_string($storedHash)
                && password_get_info($storedHash)['algoName'] !== 'unknown';
            $verificationHash = $hashValid ? $storedHash : self::DUMMY_PASSWORD_HASH;
            $passwordValid = (bool) ($this->passwordVerifier)($password, $verificationHash);

            if ($user === null || ! $hashValid || ! $passwordValid || (int) ($user['status'] ?? 0) !== 1) {
                throw AdminAuthException::invalidCredentials();
            }

            $adminId = (int) $user['id'];
            $updated = $connection->update(
                'UPDATE admin_users SET last_login_at = NOW() WHERE id = ? AND status = 1',
                [$adminId]
            );
            if ($updated === 1) {
                return $user;
            }

            if ($updated !== 0) {
                throw AdminAuthException::unavailable();
            }

            $rows = $connection->select(self::ENABLED_QUERY, [$adminId]);
            $administrator = $rows[0] ?? null;
            $administrator = is_object($administrator) ? get_object_vars($administrator) : $administrator;
            if (
                ! is_array($administrator)
                || (int) ($administrator['id'] ?? 0) !== $adminId
                || (int) ($administrator['status'] ?? 0) !== 1
            ) {
                throw AdminAuthException::invalidCredentials();
            }

            return $user;
        });
    }

    /**
     * 查询用户。
     *
     * @param ConnectionInterface $connection 传入的 ConnectionInterface 实例，用于查询用户。
     * @param string $username 登录用户名。
     * @return null|array<string,mixed> 查询成功时返回对应数据，不存在时返回 null。
     */
    private function findUser(ConnectionInterface $connection, string $username): ?array
    {
        $rows = $connection->select(self::USER_QUERY, [$username]);
        $user = $rows[0] ?? null;
        if (is_object($user)) {
            return get_object_vars($user);
        }

        return is_array($user) ? $user : null;
    }

    /**
     * 处理reserveAttempt。
     *
     * @param string $failureKey failure键名字符串。
     * @param string $activeReservationKey activeReservation键名字符串。
     * @param string $reservationId 对应业务记录的唯一标识。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function reserveAttempt(string $failureKey, string $activeReservationKey, string $reservationId): void
    {
        $result = $this->redis->eval(
            self::ATTEMPT_RESERVATION_SCRIPT,
            [
                $failureKey,
                $activeReservationKey,
                'a:' . $reservationId,
                $this->maxAttempts,
                $this->loginWindow,
            ],
            2
        );
        if ($result === -1) {
            throw AdminAuthException::rateLimited();
        }

        if ($result !== 1) {
            throw AdminAuthException::unavailable();
        }
    }

    /**
     * 处理finalizeFailure。
     *
     * @param string $failureKey failure键名字符串。
     * @param string $activeReservationKey activeReservation键名字符串。
     * @param string $reservationId 对应业务记录的唯一标识。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function finalizeFailure(
        string $failureKey,
        string $activeReservationKey,
        string $reservationId
    ): void {
        $result = $this->redis->eval(
            self::ATTEMPT_FAILURE_SCRIPT,
            [
                $failureKey,
                $activeReservationKey,
                'a:' . $reservationId,
                'f:' . $reservationId,
                $this->loginWindow,
            ],
            2
        );
        if ($result !== 1) {
            throw AdminAuthException::unavailable();
        }
    }

    /**
     * 处理releaseAttempt。
     *
     * @param string $activeReservationKey activeReservation键名字符串。
     * @param string $reservationId 对应业务记录的唯一标识。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function releaseAttempt(string $activeReservationKey, string $reservationId): void
    {
        $result = $this->redis->eval(
            self::ATTEMPT_RELEASE_SCRIPT,
            [$activeReservationKey, 'a:' . $reservationId],
            1
        );
        if ($result !== 0 && $result !== 1) {
            throw AdminAuthException::unavailable();
        }
    }

    /**
     * 处理clearAttemptsAfterSuccess。
     *
     * @param string $failureKey failure键名字符串。
     * @param string $activeReservationKey activeReservation键名字符串。
     * @param string $reservationId 对应业务记录的唯一标识。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function clearAttemptsAfterSuccess(
        string $failureKey,
        string $activeReservationKey,
        string $reservationId
    ): void {
        $result = $this->redis->eval(
            self::ATTEMPT_SUCCESS_SCRIPT,
            [$failureKey, $activeReservationKey, 'a:' . $reservationId],
            2
        );
        if ($result !== 0 && $result !== 1) {
            throw AdminAuthException::unavailable();
        }
    }

    /**
     * 处理bestEffortFinalizeFailure。
     *
     * @param string $failureKey failure键名字符串。
     * @param string $activeReservationKey activeReservation键名字符串。
     * @param string $reservationId 对应业务记录的唯一标识。
     * @return void 无返回值。
     */
    private function bestEffortFinalizeFailure(
        string $failureKey,
        string $activeReservationKey,
        string $reservationId
    ): void {
        try {
            $this->finalizeFailure($failureKey, $activeReservationKey, $reservationId);
        } catch (Throwable) {
        }
    }

    /**
     * 处理bestEffortRelease。
     *
     * @param string $activeReservationKey activeReservation键名字符串。
     * @param string $reservationId 对应业务记录的唯一标识。
     * @return void 无返回值。
     */
    private function bestEffortRelease(string $activeReservationKey, string $reservationId): void
    {
        try {
            $this->releaseAttempt($activeReservationKey, $reservationId);
        } catch (Throwable) {
        }
    }

    /**
     * 处理bestEffortDelete会话。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return void 无返回值。
     */
    private function bestEffortDeleteSession(string $key): void
    {
        try {
            $this->deleteSession($key);
        } catch (Throwable) {
        }
    }

    /**
     * 处理bestEffortRemove会话FromRegistry。
     *
     * @param string $registryKey registry键名字符串。
     * @param string $sessionKey 会话键名字符串。
     * @return void 无返回值。
     */
    private function bestEffortRemoveSessionFromRegistry(string $registryKey, string $sessionKey): void
    {
        try {
            $this->redis->sRem($registryKey, $sessionKey);
        } catch (Throwable) {
        }
    }

    /**
     * 处理会话键名。
     *
     * @param string $token 待校验或撤销的访问令牌。
     * @return string 返回会话键名字符串结果。
     */
    private function sessionKey(string $token): string
    {
        return self::SESSION_PREFIX . hash('sha256', $token);
    }

    /**
     * 处理failure键名。
     *
     * @param string $username 登录用户名。
     * @param string $clientIp clientIp字符串。
     * @return string 返回failure键名字符串结果。
     */
    private function failureKey(string $username, string $clientIp): string
    {
        return self::FAILURE_PREFIX . hash('sha256', strtolower($username) . chr(0) . $clientIp);
    }

    /**
     * 处理activeReservation键名。
     *
     * @param string $failureKey failure键名字符串。
     * @return string 返回activeReservation键名字符串结果。
     */
    private function activeReservationKey(string $failureKey): string
    {
        return $failureKey . ':active';
    }

    /**
     * 校验登录输入参数。
     *
     * @param string $username 登录用户名。
     * @param string $password 登录密码明文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function validateLoginInput(string $username, string $password): void
    {
        if (
            preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username) !== 1
            || $password === ''
            || strlen($password) > 4096
        ) {
            throw AdminAuthException::validation();
        }
    }

    /**
     * 删除会话。
     *
     * @param string $key 缓存、锁或凭据键。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function deleteSession(string $key): void
    {
        if ($this->redis->del($key) === false) {
            throw AdminAuthException::unavailable();
        }
    }

    /**
     * 判断valid会话。
     *
     * @param mixed $session 当前登录会话数据。
     * @param int $now now数值。
     * @return bool 条件满足时返回 true，否则返回 false。
     */
    private function isValidSession(mixed $session, int $now): bool
    {
        if (! is_array($session) || count($session) !== 7) {
            return false;
        }

        foreach ([
            'admin_id',
            'username',
            'issued_at',
            'expires_at',
            'csrf_token',
            'is_super_admin',
            'must_change_password',
        ] as $field) {
            if (! array_key_exists($field, $session)) {
                return false;
            }
        }

        return is_int($session['admin_id'])
            && $session['admin_id'] > 0
            && is_string($session['username'])
            && preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $session['username']) === 1
            && is_int($session['issued_at'])
            && $session['issued_at'] >= 0
            && $session['issued_at'] <= $now
            && is_int($session['expires_at'])
            && $session['expires_at'] > $session['issued_at']
            && $this->tokenTtl === $session['expires_at'] - $session['issued_at']
            && $session['expires_at'] > $now
            && is_string($session['csrf_token'])
            && preg_match('/^[a-f0-9]{64}$/D', $session['csrf_token']) === 1
            && is_bool($session['is_super_admin'])
            && is_bool($session['must_change_password']);
    }
}
