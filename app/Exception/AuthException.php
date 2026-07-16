<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Throwable;

/** 用户侧认证和业务校验异常，携带可安全返回的状态码与公开信息。 */
class AuthException extends RuntimeException
{
    public function __construct(
        private int $status,
        private string $publicMessage,
        ?Throwable $previous = null
    ) {
        parent::__construct($publicMessage, 0, $previous);
    }

    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    public static function invalidCredentials(): self
    {
        return new self(401, 'Invalid username or password.');
    }

    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    public static function tooManyRequests(string $message): self
    {
        return new self(429, $message);
    }

    public static function serviceUnavailable(string $message, ?Throwable $previous = null): self
    {
        return new self(503, $message, $previous);
    }

    public static function server(string $message, ?Throwable $previous = null): self
    {
        return new self(500, $message, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}
