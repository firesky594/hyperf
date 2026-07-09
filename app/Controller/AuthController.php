<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface;

class AuthController extends AbstractController
{
    public function __construct(private AuthService $auth)
    {
    }

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

    public function logout(): ResponseInterface
    {
        try {
            return $this->response->json($this->auth->logout($this->tokenFromRequest()));
        } catch (AuthException $exception) {
            return $this->authError($exception);
        }
    }

    private function tokenFromRequest(): string
    {
        $authorization = (string) $this->request->header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        return (string) $this->request->input('token', '');
    }

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
