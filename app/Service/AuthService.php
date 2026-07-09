<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AuthException;
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

    public function __construct(
        private Db $db,
        private Redis $redis,
        private RedisLock $locks,
        ?int $tokenTtl = null,
        ?int $lockTtl = null
    ) {
        $this->tokenTtl = $tokenTtl ?? (int) env('AUTH_TOKEN_TTL', 7200);
        $this->lockTtl = $lockTtl ?? (int) env('AUTH_LOCK_TTL', 10);
    }

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

    private function loginLockKey(string $username): string
    {
        return 'auth:login-lock:' . $username;
    }

    private function logoutLockKey(string $token): string
    {
        return 'auth:logout-lock:' . hash('sha256', $token);
    }
}
