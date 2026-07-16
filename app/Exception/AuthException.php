<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Throwable;

/** 用户侧认证和业务校验异常，携带可安全返回的状态码与公开信息。 */
class AuthException extends RuntimeException
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
     * 创建请求参数错误异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @return self 返回badRequest处理结果。
     */
    public static function badRequest(string $message): self
    {
        return new self(400, $message);
    }

    /**
     * 创建凭据无效异常。
     *
     * @return self 返回invalidCredentials处理结果。
     */
    public static function invalidCredentials(): self
    {
        return new self(401, 'Invalid username or password.');
    }

    /**
     * 创建资源冲突异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @return self 返回conflict处理结果。
     */
    public static function conflict(string $message): self
    {
        return new self(409, $message);
    }

    /**
     * 创建请求频率超限异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @return self 返回tooManyRequests处理结果。
     */
    public static function tooManyRequests(string $message): self
    {
        return new self(429, $message);
    }

    /**
     * 创建服务暂不可用异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @param ?Throwable $previous 需要保留的前置异常。
     * @return self 返回serviceUnavailable处理结果。
     */
    public static function serviceUnavailable(string $message, ?Throwable $previous = null): self
    {
        return new self(503, $message, $previous);
    }

    /**
     * 创建服务器内部错误异常。
     *
     * @param string $message 可安全返回给调用方的提示信息。
     * @param ?Throwable $previous 需要保留的前置异常。
     * @return self 返回server处理结果。
     */
    public static function server(string $message, ?Throwable $previous = null): self
    {
        return new self(500, $message, $previous);
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
