<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Throwable;

/** 用户侧认证和业务校验异常，携带可安全返回的状态码与公开信息。 */
class AuthException extends RuntimeException
{
    /** 初始化当前组件所需的依赖。 */
    public function __construct(
        private int $status,
        private string $publicMessage,
        ?Throwable $previous = null
    ) {
        parent::__construct($publicMessage, 0, $previous);
    }

    /** 执行 `badRequest` 方法对应的业务处理。 */
    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    /** 执行 `invalidCredentials` 方法对应的业务处理。 */
    public static function invalidCredentials(): self
    {
        return new self(401, 'Invalid username or password.');
    }

    /** 执行 `conflict` 方法对应的业务处理。 */
    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    /** 执行 `tooManyRequests` 方法对应的业务处理。 */
    public static function tooManyRequests(string $message): self
    {
        return new self(429, $message);
    }

    /** 执行 `serviceUnavailable` 方法对应的业务处理。 */
    public static function serviceUnavailable(string $message, ?Throwable $previous = null): self
    {
        return new self(503, $message, $previous);
    }

    /** 执行 `server` 方法对应的业务处理。 */
    public static function server(string $message, ?Throwable $previous = null): self
    {
        return new self(500, $message, $previous);
    }

    /** 更新并返回当前业务状态。 */
    public function status(): int
    {
        return $this->status;
    }

    /** 执行 `publicMessage` 方法对应的业务处理。 */
    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}
