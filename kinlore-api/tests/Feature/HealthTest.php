<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_success(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
        ]);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'service',
        ]);
    }

    public function test_detailed_health_check_requires_authentication(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_detailed_health(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => ['database', 'storage'],
        ]);
    }
}
