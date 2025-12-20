<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function test_user_can_logout_from_all_devices(): void
    {
        $user = User::factory()->create();
        
        // Create multiple tokens
        $user->createToken('device-1');
        $user->createToken('device-2');
        Sanctum::actingAs($user);

        $this->assertEquals(2, $user->tokens()->count());

        $response = $this->postJson('/api/auth/logout-all');

        $response->assertStatus(200);
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function test_user_can_refresh_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $oldTokenCount = $user->tokens()->count();

        $response = $this->postJson('/api/auth/refresh');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['token', 'token_type'],
        ]);
        
        // Old token should be deleted, new one created
        $this->assertEquals($oldTokenCount, $user->tokens()->count());
    }

    public function test_user_can_get_active_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('device-1');
        $user->createToken('device-2');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/sessions');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_user_can_revoke_specific_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device-1');
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/auth/sessions/{$token->accessToken->id}");

        $response->assertStatus(200);
        $this->assertEquals(0, $user->tokens()->count());
    }
}
