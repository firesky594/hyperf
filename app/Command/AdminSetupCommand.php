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

namespace App\Command;

use App\Exception\AdminAuthException;
use App\Service\AdminUserProvisioner;
use Hyperf\Command\Command;

class AdminSetupCommand extends Command
{
    protected ?string $signature = 'admin:setup {username : Administrator username}';

    protected string $description = 'Create or reset an administrator account.';

    public function __construct(private AdminUserProvisioner $provisioner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = (string) $this->secret('Administrator password');
        $confirmation = (string) $this->secret('Confirm administrator password');

        if (! hash_equals($password, $confirmation)) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        try {
            $result = $this->provisioner->provision($username, $password);
        } catch (AdminAuthException $exception) {
            $this->error($exception->publicMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            '%s %s',
            $result['username'],
            $result['created'] ? 'created' : 'reset'
        ));

        return self::SUCCESS;
    }
}
