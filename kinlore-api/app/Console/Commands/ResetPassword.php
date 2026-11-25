<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * For the server owner when someone forgot a password (docs/self-hosting/maintenance.md).
 */
#[Signature('kinlore:reset-password {email} {--password= : New password; asked for when left out}')]
#[Description('Set a new password for a user and sign them out on all devices')]
class ResetPassword extends Command
{
    public function handle(): int
    {
        $user = User::where('email', strtolower(trim((string) $this->argument('email'))))->first();
        if (! $user) {
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
