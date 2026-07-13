<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Database\ConnectionInterface;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use JsonException;
use Throwable;

use function Hyperf\Support\env;

class AdminAuthService
{
    private const USER_QUERY = 'SELECT id, username, password_hash, status FROM admin_users WHERE username = ? LIMIT 1 FOR UPDATE';

    private const ENABLED_QUERY = 'SELECT id, status FROM admin_users WHERE id = ? LIMIT 1 FOR UPDATE';

    private const SESSION_PREFIX = 'admin:session:';

    private const FAILURE_PREFIX = 'admin:login:fail:';

    private const DUMMY_PASSWORD_HASH = '$argon2id$v=19$m=65536,t=4,p=1$dzRMa1Iua1ovSjRyNTk3NA$fan7A8YiM2t8b2PtiRPArU7SCT4zWAiNkzo4/kj2F8w';

    private const ATTEMPT_RESERVATION_SCRIPT = <<<'LUA'
local now_parts = redis.call("TIME")
local now = tonumber(now_parts[1]) + (tonumber(now_parts[2]) / 1000000)
local maximum = tonumber(ARGV[2])
local window = tonumber(ARGV[3])

redis.call("ZREMRANGEBYSCORE", KEYS[1], "-inf", now)
if redis.call("ZCARD", KEYS[1]) >= maximum then
    redis.call("EXPIRE", KEYS[1], window)
    return -1
end

local added = redis.call("ZADD", KEYS[1], "NX", now + window, ARGV[1])
if added ~= 1 then
    return -2
end

redis.call("EXPIRE", KEYS[1], window)
return 1
LUA;

    private const ATTEMPT_RELEASE_SCRIPT = <<<'LUA'
return redis.call("ZREM", KEYS[1], ARGV[1])
LUA;

    private int $tokenTtl;

    private int $maxAttempts;

    private int $loginWindow;

    private $passwordVerifier;

    private $clock;

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
     * @return array{
     *     token:string,
     *     expires_in:int,
     *     session:array{admin_id:int,username:string,issued_at:int,expires_at:int,csrf_token:string}
     * }
     */
    public function login(string $username, string $password, string $clientIp): array
    {
        $failureKey = $this->failureKey($username, $clientIp);
        $reservationId = null;

        try {
            $reservationId = bin2hex(random_bytes(32));
            $this->reserveAttempt($failureKey, $reservationId);

            $user = $this->authenticateInTransaction($username, $password);
            $this->releaseAttempt($failureKey, $reservationId);
            $reservationId = null;

            $now = (int) ($this->clock)();
            $token = bin2hex(random_bytes(32));
            $session = [
                'admin_id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'issued_at' => $now,
                'expires_at' => $now + $this->tokenTtl,
                'csrf_token' => bin2hex(random_bytes(32)),
            ];
            $payload = json_encode($session, JSON_THROW_ON_ERROR);

            $cached = $this->redis->setex($this->sessionKey($token), $this->tokenTtl, $payload);
            if ($cached !== true && $cached !== 'OK') {
                throw AdminAuthException::unavailable();
            }

            return [
                'token' => $token,
                'expires_in' => $this->tokenTtl,
                'session' => $session,
            ];
        } catch (AdminAuthException $exception) {
            if ($reservationId !== null && ! in_array($exception->status(), [401, 429], true)) {
                $this->bestEffortRelease($failureKey, $reservationId);
            }

            throw $exception;
        } catch (Throwable $throwable) {
            if ($reservationId !== null) {
                $this->bestEffortRelease($failureKey, $reservationId);
            }

            throw AdminAuthException::unavailable(previous: $throwable);
        }
    }

    /**
     * @return null|array{admin_id:int,username:string,issued_at:int,expires_at:int,csrf_token:string}
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
     * @return array<string,mixed>
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
     * @return null|array<string,mixed>
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

    private function reserveAttempt(string $key, string $reservationId): void
    {
        $result = $this->redis->eval(
            self::ATTEMPT_RESERVATION_SCRIPT,
            [$key, $reservationId, $this->maxAttempts, $this->loginWindow],
            1
        );
        if ($result === -1) {
            throw AdminAuthException::rateLimited();
        }

        if ($result !== 1) {
            throw AdminAuthException::unavailable();
        }
    }

    private function releaseAttempt(string $key, string $reservationId): void
    {
        $result = $this->redis->eval(self::ATTEMPT_RELEASE_SCRIPT, [$key, $reservationId], 1);
        if ($result !== 0 && $result !== 1) {
            throw AdminAuthException::unavailable();
        }
    }

    private function bestEffortRelease(string $key, string $reservationId): void
    {
        try {
            $this->releaseAttempt($key, $reservationId);
        } catch (Throwable) {
        }
    }

    private function sessionKey(string $token): string
    {
        return self::SESSION_PREFIX . hash('sha256', $token);
    }

    private function failureKey(string $username, string $clientIp): string
    {
        return self::FAILURE_PREFIX . hash('sha256', strtolower($username) . chr(0) . $clientIp);
    }

    private function deleteSession(string $key): void
    {
        if ($this->redis->del($key) === false) {
            throw AdminAuthException::unavailable();
        }
    }

    private function isValidSession(mixed $session, int $now): bool
    {
        if (! is_array($session) || count($session) !== 5) {
            return false;
        }

        foreach (['admin_id', 'username', 'issued_at', 'expires_at', 'csrf_token'] as $field) {
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
            && $session['expires_at'] - $session['issued_at'] === $this->tokenTtl
            && $session['expires_at'] > $now
            && is_string($session['csrf_token'])
            && preg_match('/^[a-f0-9]{64}$/D', $session['csrf_token']) === 1;
    }
}
