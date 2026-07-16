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

namespace App\Exception;

use RuntimeException;
use Throwable;

/** 后台认证与授权异常，携带可公开返回的状态码和提示。 */
class AdminAuthException extends RuntimeException
{
    /** 初始化当前组件所需的依赖。 */
    public function __construct(
        private int $status,
        private string $publicMessage,
        ?Throwable $previous = null
    ) {
        parent::__construct($publicMessage, 0, $previous);
    }

    /** 执行 `validation` 方法对应的业务处理。 */
    public static function validation(string $message = 'Invalid administrator input.'): self
    {
        return new self(422, $message);
    }

    /** 执行 `invalidCredentials` 方法对应的业务处理。 */
    public static function invalidCredentials(string $message = 'Invalid username or password.'): self
    {
        return new self(401, $message);
    }

    /** 执行 `invalidFormToken` 方法对应的业务处理。 */
    public static function invalidFormToken(string $message = 'Invalid form token.'): self
    {
        return new self(419, $message);
    }

    /** 执行 `rateLimited` 方法对应的业务处理。 */
    public static function rateLimited(string $message = 'Too many requests.'): self
    {
        return new self(429, $message);
    }

    /** 执行 `unavailable` 方法对应的业务处理。 */
    public static function unavailable(
        string $message = 'Administrator authentication unavailable.',
        ?Throwable $previous = null
    ): self {
        return new self(503, $message, $previous);
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
