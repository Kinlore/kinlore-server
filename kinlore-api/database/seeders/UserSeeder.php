<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the initial user from environment variables.
     */
    public function run(): void
    {
        $email = env('SEED_USER_EMAIL');
        $password = env('SEED_USER_PASSWORD');

        if (empty($email) || empty($password)) {
            $this->command->warn('SEED_USER_EMAIL or SEED_USER_PASSWORD not set. Skipping user seeding.');
            return;
        }

        // Only create if user doesn't already exist
        if (User::where('email', $email)->exists()) {
            $this->command->info("User {$email} already exists. Skipping.");
            return;
        }

        $user = new User([
            'name' => env('SEED_USER_NAME', 'Admin User'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->email_verified_at = now();
        if (!User::exists()) {
            $user->role = 'admin'; // the instance's first user is its admin
        }
        $user->save();

        $this->command->info("User {$email} created successfully.");
    }
}
