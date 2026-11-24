<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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

    public function test_detailed_health_is_for_the_admin_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/health/detailed')->assertForbidden();
    }

    public function test_admin_can_access_detailed_health(): void
    {
        Storage::fake(config('filesystems.default'));
        $user = User::factory()->admin()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => ['database', 'storage'],
        ]);
    }

    public function test_detailed_health_reports_storage_error_when_the_disk_write_fails(): void
    {
        $disk = Storage::fake(config('filesystems.default'));
        Storage::shouldReceive('disk')->andReturn(\Mockery::mock($disk)->makePartial()->shouldReceive('put')->andReturn(false)->getMock());
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/health/detailed')->assertStatus(503)->assertJsonPath('checks.storage.status', 'error');
    }
}
