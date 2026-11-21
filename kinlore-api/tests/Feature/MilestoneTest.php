<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Milestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_predefined_milestones(): void
    {
        Milestone::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/milestones?flat=1');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_list_milestones_grouped_by_stage(): void
    {
        Milestone::factory()->count(3)->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/milestones')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_user_can_get_milestone_by_id(): void
    {
        $milestone = Milestone::factory()->create([
            'id' => 'first-steps',
            'title' => 'First Steps',
        ]);
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson("/api/v1/milestones/{$milestone->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', 'first-steps');
        $response->assertJsonPath('data.title', 'First Steps');
    }

    public function test_unknown_milestone_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/milestones/does-not-exist')->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_list_milestones(): void
    {
        $this->getJson('/api/v1/milestones')->assertStatus(401);
    }
}
