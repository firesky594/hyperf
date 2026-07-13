<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use JsonException;
use Throwable;

use function Hyperf\Support\env;

class AdminAuthService
{
    private const USER_QUERY = 'SELECT id, username, password_hash, status FROM admin_users WHERE username = ? LIMIT 1';

    private const SESSION_PREFIX = 'admin:session:';

    private const FAILURE_PREFIX = 'admin:login:fail:';

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
        try {
            $failureKey = $this->failureKey($username, $clientIp);
            if ($this->failureCount($failureKey) >= $this->maxAttempts) {
                throw AdminAuthException::rateLimited();
            }

            $user = $this->findUser($username);
            $passwordValid = $user !== null
                && isset($user['password_hash'])
                && is_string($user['password_hash'])
                && (bool) ($this->passwordVerifier)($password, $user['password_hash']);

            if (! $passwordValid || (int) ($user['status'] ?? 0) !== 1) {
                $this->recordFailure($failureKey);
                throw AdminAuthException::invalidCredentials();
            }

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

            $this->db->update(
                'UPDATE admin_users SET last_login_at = NOW() WHERE id = ?',
                [$session['admin_id']]
            );

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
            throw $exception;
        } catch (Throwable $throwable) {
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
     * @return null|array<string,mixed>
     */
    private function findUser(string $username): ?array
    {
        $rows = $this->db->select(self::USER_QUERY, [$username]);
        $user = $rows[0] ?? null;
        if (is_object($user)) {
            return get_object_vars($user);
        }

        return is_array($user) ? $user : null;
    }

    private function failureCount(string $key): int
    {
        $value = $this->redis->get($key);
        if ($value === false || $value === null) {
            return 0;
        }

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw AdminAuthException::unavailable();
    }

    private function recordFailure(string $key): void
    {
        $count = $this->redis->incr($key);
        if (! is_int($count) || $count < 1) {
            throw AdminAuthException::unavailable();
        }

        if ($count === 1 && $this->redis->expire($key, $this->loginWindow) !== true) {
            throw AdminAuthException::unavailable();
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
            && $session['expires_at'] > $now
            && is_string($session['csrf_token'])
            && preg_match('/^[a-f0-9]{64}$/D', $session['csrf_token']) === 1;
    }
}
