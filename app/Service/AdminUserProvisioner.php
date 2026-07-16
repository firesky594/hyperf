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

namespace App\Service;

use App\Exception\AdminAuthException;
use Hyperf\Contract\IdGeneratorInterface;
use Hyperf\DbConnection\Db;
use Throwable;

/** 创建或更新管理员账号，并安全生成和散列初始密码。 */
class AdminUserProvisioner
{
    private $passwordHasher;

    private $temporaryPasswordGenerator;

    /**
     * 初始化当前组件所需的依赖。
     *
     * @param Db $db 数据库访问入口。
     * @param IdGeneratorInterface $idGenerator 注入的 IdGeneratorInterface 依赖。
     * @param AdminSchemaService $schema 注入的 AdminSchemaService 依赖。
     * @param ?callable $passwordHasher 用于安全校验的哈希值。
     * @param ?callable $temporaryPasswordGenerator 用于执行指定处理逻辑的回调。
     * @return void 无返回值。
     */
    public function __construct(
        private Db $db,
        private IdGeneratorInterface $idGenerator,
        private AdminSchemaService $schema,
        ?callable $passwordHasher = null,
        ?callable $temporaryPasswordGenerator = null
    ) {
        $this->passwordHasher = $passwordHasher
            ?? static fn (string $password): string => password_hash($password, PASSWORD_ARGON2ID);
        $this->temporaryPasswordGenerator = $temporaryPasswordGenerator
            ?? static fn (): string => bin2hex(random_bytes(16)) . 'Aa1!';
    }

    /**
     * 处理provisionSuper管理员。
     *
     * @param string $username 登录用户名。
     * @return array{id:int,username:string,created:bool,temporary_password:string} 返回provisionSuper管理员结构化数据。
     * @throws Throwable 底层处理失败并重新抛出原异常。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function provisionSuperAdmin(string $username = 'welkin'): array
    {
        $temporaryPassword = (string) ($this->temporaryPasswordGenerator)();
        $this->validate($username, $temporaryPassword);

        try {
            $this->schema->ensureSchema();
            $rows = $this->db->select(
                'SELECT id FROM admin_users WHERE username = ? LIMIT 1',
                [$username]
            );
            $passwordHash = ($this->passwordHasher)($temporaryPassword);
            $administrator = $rows[0] ?? null;

            if ($administrator !== null) {
                $id = (int) (is_object($administrator) ? $administrator->id : $administrator['id']);
                $this->db->update(
                    'UPDATE admin_users SET password_hash = ?, status = 1, is_super_admin = 1, '
                    . 'must_change_password = 1, deleted_at = NULL, updated_at = NOW() WHERE username = ?',
                    [$passwordHash, $username]
                );

                return [
                    'id' => $id,
                    'username' => $username,
                    'created' => false,
                    'temporary_password' => $temporaryPassword,
                ];
            }

            $id = $this->idGenerator->generate();
            $this->db->insert(
                'INSERT INTO admin_users '
                . '(id, username, password_hash, status, is_super_admin, must_change_password, created_at, updated_at) '
                . 'VALUES (?, ?, ?, 1, 1, 1, NOW(), NOW())',
                [$id, $username, $passwordHash]
            );

            return [
                'id' => $id,
                'username' => $username,
                'created' => true,
                'temporary_password' => $temporaryPassword,
            ];
        } catch (AdminAuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable('Administrator provisioning unavailable.', $throwable);
        }
    }

    /**
     * 处理provision。
     *
     * @param string $username 登录用户名。
     * @param string $password 登录密码明文。
     * @return array{id:int,username:string,created:bool} 返回provision结构化数据。
     * @throws Throwable 底层处理失败并重新抛出原异常。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    public function provision(string $username, string $password): array
    {
        try {
            $this->validate($username, $password);
            $this->schema->ensureSchema();

            $rows = $this->db->select(
                'SELECT id FROM admin_users WHERE username = ? LIMIT 1',
                [$username]
            );
            $passwordHash = ($this->passwordHasher)($password);
            $administrator = $rows[0] ?? null;

            if ($administrator !== null) {
                $id = (int) (is_object($administrator) ? $administrator->id : $administrator['id']);
                $this->db->update(
                    'UPDATE admin_users SET password_hash = ?, status = 1, updated_at = NOW() WHERE username = ?',
                    [$passwordHash, $username]
                );

                return ['id' => $id, 'username' => $username, 'created' => false];
            }

            $id = $this->idGenerator->generate();
            $this->db->insert(
                'INSERT INTO admin_users (id, username, password_hash, status, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, 1, NOW(), NOW())',
                [$id, $username, $passwordHash]
            );

            return ['id' => $id, 'username' => $username, 'created' => true];
        } catch (AdminAuthException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw AdminAuthException::unavailable('Administrator provisioning unavailable.', $throwable);
        }
    }

    /**
     * 校验ate。
     *
     * @param string $username 登录用户名。
     * @param string $password 登录密码明文。
     * @return void 无返回值。
     * @throws \App\Exception\AdminAuthException 认证、授权或业务校验失败时抛出。
     */
    private function validate(string $username, string $password): void
    {
        $passwordLength = strlen($password);
        if (
            preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username) !== 1
            || $passwordLength < 12
            || $passwordLength > 4096
        ) {
            throw AdminAuthException::validation(
                'Username must be 3-64 valid characters and password must be 12-4096 bytes.'
            );
        }
    }

}
