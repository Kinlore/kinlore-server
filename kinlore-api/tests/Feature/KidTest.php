<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Kid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class KidTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_their_kids(): void
    {
        $user = User::factory()->create();
        Kid::factory()->count(3)->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/kids');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name', 'date_of_birth', 'avatar_color', 'age', 'moments_count'],
            ],
        ]);
    }

    public function test_unauthenticated_user_cannot_list_kids(): void
    {
        $response = $this->getJson('/api/kids');

        $response->assertStatus(401);
    }

    public function test_user_can_create_kid(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/kids', [
            'name' => 'Emma',
            'date_of_birth' => '2019-03-15',
            'avatar_color' => '#FF6B6B',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'Emma');
        $response->assertJsonPath('data.avatar_color', '#FF6B6B');

        $this->assertDatabaseHas('kids', [
            'name' => 'Emma',
            'user_id' => $user->id,
        ]);
    }

    public function test_kid_creation_requires_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/kids', [
            'date_of_birth' => '2019-03-15',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_kid_creation_requires_date_of_birth(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/kids', [
            'name' => 'Emma',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_kid_date_of_birth_must_be_in_past(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/kids', [
            'name' => 'Emma',
            'date_of_birth' => now()->addDays(1)->format('Y-m-d'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_kid_generates_random_color_if_not_provided(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/kids', [
            'name' => 'Emma',
            'date_of_birth' => '2019-03-15',
        ]);

        $response->assertStatus(201);
        $this->assertMatchesRegularExpression(
            '/^#[0-9A-F]{6}$/i',
            $response->json('data.avatar_color')
        );
    }

    public function test_user_can_view_their_own_kid(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/kids/{$kid->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $kid->id);
        $response->assertJsonPath('data.name', $kid->name);
    }

    public function test_user_cannot_view_another_users_kid(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/kids/{$kid->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_their_own_kid(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id, 'name' => 'Emma']);
        
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/kids/{$kid->id}", [
            'name' => 'Emma Rose',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Emma Rose');

        $this->assertDatabaseHas('kids', [
            'id' => $kid->id,
            'name' => 'Emma Rose',
        ]);
    }

    public function test_user_cannot_update_another_users_kid(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/kids/{$kid->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_their_own_kid(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/kids/{$kid->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('kids', ['id' => $kid->id]);
    }

    public function test_user_cannot_delete_another_users_kid(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/kids/{$kid->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('kids', ['id' => $kid->id]);
    }
}
