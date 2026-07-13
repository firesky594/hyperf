<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Throwable;

class AdminAuthException extends RuntimeException
{
    public function __construct(
        private int $status,
        private string $publicMessage,
        ?Throwable $previous = null
    ) {
        parent::__construct($publicMessage, 0, $previous);
    }

    public static function validation(string $message = 'Invalid administrator input.'): self
    {
        return new self(422, $message);
    }

    public static function invalidCredentials(string $message = 'Invalid username or password.'): self
    {
        return new self(401, $message);
    }

    public static function invalidFormToken(string $message = 'Invalid form token.'): self
    {
        return new self(419, $message);
    }

    public static function rateLimited(string $message = 'Too many requests.'): self
    {
        return new self(429, $message);
    }

    public static function unavailable(
        string $message = 'Administrator authentication unavailable.',
        ?Throwable $previous = null
    ): self {
        return new self(503, $message, $previous);
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
