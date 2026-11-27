<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Invite and setup links use APP_URL, or the request address when APP_URL is a placeholder.
 */
class PublicUrlTest extends TestCase
{
    use RefreshDatabase;

    private function createInvitation(string $base): array
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        return $this->postJson($base.'/api/v1/invitations')->assertStatus(201)->json('data');
    }

    public function test_placeholder_app_url_falls_back_to_the_request_address(): void
    {
        config(['app.url' => 'https://photos.example.com']);

        $data = $this->createInvitation('https://family.lan:8443');

        $this->assertFalse($data['public_url_configured']);
        $this->assertSame("https://family.lan:8443/invite/{$data['token']}", $data['invite_url']);
        $this->assertSame("kinlore://invite/{$data['token']}?server=https%3A%2F%2Ffamily.lan%3A8443", $data['invite_link']);

        $this->get("https://family.lan:8443/invite/{$data['token']}")->assertOk()
            ->assertSee('<code aria-labelledby="server-label">https://family.lan:8443</code>', false)
            ->assertSee("kinlore://invite/{$data['token']}?server=https%3A%2F%2Ffamily.lan%3A8443", false)
            ->assertDontSee('photos.example.com');
        $this->getJson('https://family.lan:8443/api/auth/setup-status')->assertJsonPath('data.setup_url', 'https://family.lan:8443/setup');
    }

    public function test_real_app_url_wins_over_the_host_header(): void
    {
        config(['app.url' => 'https://kin.family.test/']);

        $data = $this->createInvitation('http://other.host');

        $this->assertTrue($data['public_url_configured']);
        $this->assertSame("https://kin.family.test/invite/{$data['token']}", $data['invite_url']);
        $this->assertSame("kinlore://invite/{$data['token']}?server=https%3A%2F%2Fkin.family.test", $data['invite_link']);
        $this->get("http://other.host/invite/{$data['token']}")->assertOk()
            ->assertSee('<code aria-labelledby="server-label">https://kin.family.test</code>', false)
            ->assertDontSee('other.host');
    }

    public function test_placeholder_detection(): void
    {
        $placeholders = ['', 'http://localhost', 'http://localhost:8000', 'http://127.0.0.1', 'http://[::1]:8000', 'http://0.0.0.0',
            'https://example.com', 'https://photos.example.com', 'https://EXAMPLE.org', 'https://a.b.example.net', 'photos.kin.app'];
        foreach ($placeholders as $url) {
            config(['app.url' => $url]);
            $this->assertFalse(PublicUrl::isConfigured(), $url);
        }

        foreach (['https://kin.app', 'https://myexample.com', 'https://example.com.kin.app', 'http://192.168.1.20:8000'] as $url) {
            config(['app.url' => $url]);
            $this->assertTrue(PublicUrl::isConfigured(), $url);
        }
    }

    public function test_detailed_health_warns_about_a_placeholder_app_url(): void
    {
        Storage::fake(config('filesystems.default'));
        Sanctum::actingAs(User::factory()->admin()->create());

        config(['app.url' => 'https://photos.example.com']);
        $this->getJson('/api/health/detailed')->assertJsonPath('checks.public_url.status', 'warning');

        config(['app.url' => 'https://kin.app']);
        $this->getJson('/api/health/detailed')->assertJsonPath('checks.public_url.status', 'ok')
            ->assertJsonPath('checks.public_url.url', 'https://kin.app');
    }
}
