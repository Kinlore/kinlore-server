<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Kid;
use App\Models\Moment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_kids(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        $this->assertCount(1, $user->kids);
        $this->assertEquals($kid->id, $user->kids->first()->id);
    }

    public function test_user_can_calculate_total_moments_count(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->count(5)->create(['kid_id' => $kid->id]);

        $this->assertEquals(5, $user->getTotalMomentsCount());
    }

    public function test_user_can_calculate_storage_used(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Moment::factory()->create([
            'kid_id' => $kid->id,
            'file_size' => 1024 * 1024, // 1MB
        ]);

        $this->assertEquals(1024 * 1024, $user->getStorageUsed());
        $this->assertStringContainsString('MB', $user->getStorageUsedFormatted());
    }

    public function test_user_has_hashed_password(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(\Hash::check('password123', $user->password));
    }
}

