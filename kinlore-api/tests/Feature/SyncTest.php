<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Kid;
use App\Models\Moment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_pull_initial_sync(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->count(3)->create(['kid_id' => $kid->id]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => null,
            'schemaVersion' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'changes' => [
                'kids' => ['created', 'updated', 'deleted'],
                'moments' => ['created', 'updated', 'deleted'],
                'milestones' => ['created', 'updated', 'deleted'],
            ],
            'timestamp',
        ]);

        $this->assertCount(1, $response->json('changes.kids.created'));
        $this->assertCount(3, $response->json('changes.moments.created'));
    }

    public function test_user_can_pull_incremental_sync(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $lastPulledAt = now()->subHours(1)->timestamp * 1000;
        
        // Create moment after lastPulledAt
        Moment::factory()->create(['kid_id' => $kid->id]);

        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => $lastPulledAt,
            'schemaVersion' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('changes.moments.created')));
    }

    public function test_user_can_push_changes(): void
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/push', [
            'changes' => [
                'kids' => [
                    'created' => [
                        [
                            'id' => '123e4567-e89b-12d3-a456-426614174000',
                            'name' => 'Emma',
                            'date_of_birth' => now()->subYears(5)->timestamp * 1000,
                            'avatar_color' => '#FF6B6B',
                        ],
                    ],
                    'updated' => [],
                    'deleted' => [],
                ],
                'moments' => [
                    'created' => [],
                    'updated' => [],
                    'deleted' => [],
                ],
            ],
            'lastPulledAt' => now()->timestamp * 1000,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('kids', [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Emma',
            'user_id' => $user->id,
        ]);
    }

    public function test_sync_pull_requires_schema_version(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => null,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['schemaVersion']);
    }

    public function test_unauthenticated_user_cannot_sync(): void
    {
        $response = $this->postJson('/api/v1/sync/pull', [
            'lastPulledAt' => null,
            'schemaVersion' => 1,
        ]);

        $response->assertStatus(401);
    }
}
