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
    /**
     * 初始化当前组件所需的依赖。
     *
     * @param int $status 目标业务状态。
     * @param string $publicMessage 可安全返回给调用方的异常信息。
     * @param ?Throwable $previous 需要保留的前置异常。
     * @return void 无返回值。
     */
    public function __construct(
        private int $status,
        private string $publicMessage,
        ?Throwable $previous = null
    ) {
        parent::__construct($publicMessage, 0, $previous);
    }

    /**
     * 创建输入校验异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @return self 返回validation处理结果。
     */
    public static function validation(string $message = 'Invalid administrator input.'): self
    {
        return new self(422, $message);
    }

    /**
     * 创建凭据无效异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @return self 返回invalidCredentials处理结果。
     */
    public static function invalidCredentials(string $message = 'Invalid username or password.'): self
    {
        return new self(401, $message);
    }

    /**
     * 创建表单令牌无效异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @return self 返回invalid表单令牌处理结果。
     */
    public static function invalidFormToken(string $message = 'Invalid form token.'): self
    {
        return new self(419, $message);
    }

    /**
     * 创建访问频率超限异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @return self 返回rateLimited处理结果。
     */
    public static function rateLimited(string $message = 'Too many requests.'): self
    {
        return new self(429, $message);
    }

    /**
     * 创建后台服务不可用异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @param ?Throwable $previous 需要保留的前置异常。
     * @return self 返回unavailable处理结果。
     */
    public static function unavailable(
        string $message = 'Administrator authentication unavailable.',
        ?Throwable $previous = null
    ): self {
        return new self(503, $message, $previous);
    }

    /**
     * 更新并返回当前业务状态。
     *
     * @return int 返回状态整数结果。
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * 获取可安全返回给调用方的异常信息。
     *
     * @return string 返回public提示信息字符串结果。
     */
    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}
