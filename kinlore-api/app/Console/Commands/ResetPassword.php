<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * For the server owner when someone forgot a password (docs/SELF_HOSTING.md).
 */
class ResetPassword extends Command
{
    protected $signature = 'kinlore:reset-password {email} {--password= : New password; asked for when left out}';

    protected $description = 'Set a new password for a user and sign them out on all devices';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('No user has this email address.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('New password (at least 8 characters)');
        if (strlen((string) $password) < 8) {
            $this->error('The password needs at least 8 characters.');

            return self::FAILURE;
        }

        $user->password = $password;
        $user->save();
        $user->tokens()->delete();

        $this->info("Password changed for {$user->email}. They are signed out on all devices.");

        return self::SUCCESS;
    }
}
