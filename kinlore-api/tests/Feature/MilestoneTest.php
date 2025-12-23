<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Milestone;
use App\Models\CustomMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_predefined_milestones(): void
    {
        $user = User::factory()->create();
        
        // Create some milestones
        Milestone::factory()->count(3)->create();
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/milestones');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_get_milestone_by_slug(): void
    {
        $user = User::factory()->create();
        
        $milestone = Milestone::factory()->create([
            'name' => 'First Steps',
            'slug' => 'first-steps',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/milestones/{$milestone->slug}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'First Steps');
        $response->assertJsonPath('data.slug', 'first-steps');
    }

    public function test_user_can_list_their_custom_milestones(): void
    {
        $user = User::factory()->create();
        CustomMilestone::factory()->count(2)->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/milestones/custom');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_see_other_users_custom_milestones(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        CustomMilestone::factory()->count(2)->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/milestones/custom');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_user_can_create_custom_milestone(): void
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/milestones/custom', [
            'name' => 'First Bike Ride',
            'description' => 'Learning to ride a bike without training wheels',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'First Bike Ride');

        $this->assertDatabaseHas('custom_milestones', [
            'user_id' => $user->id,
            'name' => 'First Bike Ride',
        ]);
    }

    public function test_custom_milestone_requires_name(): void
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/milestones/custom', [
            'description' => 'Missing name',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_their_custom_milestone(): void
    {
        $user = User::factory()->create();
        $milestone = CustomMilestone::factory()->create([
            'user_id' => $user->id,
            'name' => 'Old Name',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/milestones/custom/{$milestone->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('custom_milestones', [
            'id' => $milestone->id,
            'name' => 'New Name',
        ]);
    }

    public function test_user_cannot_update_other_users_custom_milestone(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $milestone = CustomMilestone::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->putJson("/api/milestones/custom/{$milestone->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_their_custom_milestone(): void
    {
        $user = User::factory()->create();
        $milestone = CustomMilestone::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/milestones/custom/{$milestone->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('custom_milestones', ['id' => $milestone->id]);
    }

    public function test_user_cannot_delete_other_users_custom_milestone(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $milestone = CustomMilestone::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/milestones/custom/{$milestone->id}");

        $response->assertStatus(403);
    }
}
