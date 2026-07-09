<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface;

class AuthController extends AbstractController
{
    /**
     * 初始化认证控制器。
     *
     * @param AuthService $auth 认证服务，负责登录、退出和注册业务。
     */
    public function __construct(private AuthService $auth)
    {
    }

    /**
     * 用户登录接口。
     *
     * 从请求中读取 username 和 password，委托 AuthService 校验并创建 Redis 登录缓存。
     *
     * @return ResponseInterface 登录成功或认证错误的 JSON 响应。
     */
    public function login(): ResponseInterface
    {
        try {
            return $this->response->json($this->auth->login(
                (string) $this->request->input('username', ''),
                (string) $this->request->input('password', '')
            ));
        } catch (AuthException $exception) {
            return $this->authError($exception);
        }
    }

    /**
     * 用户退出接口。
     *
     * 优先从 Authorization Bearer 头读取 token，缺失时再从请求参数 token 读取。
     *
     * @return ResponseInterface 退出结果或认证错误的 JSON 响应。
     */
    public function logout(): ResponseInterface
    {
        try {
            return $this->response->json($this->auth->logout($this->tokenFromRequest()));
        } catch (AuthException $exception) {
            return $this->authError($exception);
        }
    }

    /**
     * 注册单个随机测试用户。
     *
     * 每次访问只生成并同步写入 1 个用户，不再走 Redis Stream 批量队列。
     *
     * @return ResponseInterface 注册结果 JSON 响应。
     */
    public function registerRandom(): ResponseInterface
    {
        try {
            return $this->response->json($this->auth->registerRandom())->withStatus(201);
        } catch (AuthException $exception) {
            return $this->authError($exception);
        }
    }

    /**
     * 从当前请求提取登录 token。
     *
     * @return string 解析到的 token；未提供时返回空字符串。
     */
    private function tokenFromRequest(): string
    {
        $authorization = (string) $this->request->header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        return (string) $this->request->input('token', '');
    }

    /**
     * 将认证异常转换为统一 JSON 错误响应。
     *
     * @param AuthException $exception 认证服务抛出的业务异常。
     * @return ResponseInterface 携带 HTTP 状态码和错误信息的 JSON 响应。
     */
    private function authError(AuthException $exception): ResponseInterface
    {
        return $this->response->json([
            'error' => [
                'message' => $exception->publicMessage(),
                'status' => $exception->status(),
            ],
        ])->withStatus($exception->status());
    }
}
