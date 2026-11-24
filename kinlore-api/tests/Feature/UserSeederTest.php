<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('SEED_USER_EMAIL=seed@example.com');
        putenv('SEED_USER_PASSWORD=secret-password');
    }

    protected function tearDown(): void
    {
        putenv('SEED_USER_EMAIL');
        putenv('SEED_USER_PASSWORD');
        parent::tearDown();
    }

    public function test_seeds_the_admin_on_an_empty_server_and_not_again_after_it_was_removed(): void
    {
        $this->seed(UserSeeder::class);
        $this->assertSame(Role::Admin, User::where('email', 'seed@example.com')->sole()->role);

        User::factory()->create();
        User::where('email', 'seed@example.com')->delete();
        $this->seed(UserSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'seed@example.com']);
    }
}
