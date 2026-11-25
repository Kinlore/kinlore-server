<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * For the server owner before the first admin exists (docs/self-hosting/first-admin.md).
 */
#[Signature('kinlore:setup-token')]
#[Description('Show the token that the setup page asks for, while the server has no user')]
class SetupToken extends Command
{
    public function handle(AuthService $auth): int
    {
        if (User::exists()) {
            $auth->forgetSetupToken();
            $this->info('This server is already set up, so it has no setup token.');

            return self::SUCCESS;
        }
        if (filled(config('app.setup_token'))) {
            $this->info('The setup token is the value of SETUP_TOKEN in the server settings.');

            return self::SUCCESS;
        }

        $this->line('Setup token: '.$auth->setupToken());
        $this->line('Open '.$auth->setupUrl().' and enter this token to create the admin account.');

        return self::SUCCESS;
    }
}
