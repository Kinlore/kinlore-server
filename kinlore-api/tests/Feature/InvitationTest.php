<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Server;
use App\Models\ServerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_invitation()
    {
        $owner = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $owner->id]);
        ServerMember::factory()->create(['user_id' => $owner->id, 'server_id' => $server->id, 'role' => 'owner']);

        $response = $this->actingAs($owner)->postJson("/api/v1/servers/{$server->id}/invitations", [
            'role' => 'contributor',
            'email' => 'friend@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'friend@example.com')
            ->assertJsonPath('data.role', 'contributor');

        $this->assertDatabaseHas('invitations', [
            'server_id' => $server->id,
            'email' => 'friend@example.com',
            'role' => 'contributor',
            'invited_by' => $owner->id,
        ]);
    }

    public function test_viewer_cannot_create_invitation()
    {
        $viewer = User::factory()->create();
        $server = Server::factory()->create();
        ServerMember::factory()->create(['user_id' => $viewer->id, 'server_id' => $server->id, 'role' => 'viewer']);

        $response = $this->actingAs($viewer)->postJson("/api/v1/servers/{$server->id}/invitations", [
            'role' => 'contributor',
        ]);

        $response->assertStatus(403);
    }

    public function test_can_validate_public_invitation_token()
    {
        $invitation = Invitation::factory()->create();

        $response = $this->getJson("/api/v1/invitations/validate/{$invitation->token}");

        $response->assertStatus(200)
            ->assertJsonPath('valid', true)
            ->assertJsonPath('data.token', null); // Token is hidden in serialization but we check object exists
    }

    public function test_user_can_accept_invitation()
    {
        $user = User::factory()->create();
        $invitation = Invitation::factory()->create(['role' => 'contributor']);

        $response = $this->actingAs($user)->postJson('/api/v1/invitations/accept', [
            'token' => $invitation->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('server_members', [
            'user_id' => $user->id,
            'server_id' => $invitation->server_id,
            'role' => 'contributor',
        ]);

        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_cannot_accept_expired_invitation()
    {
        $user = User::factory()->create();
        $invitation = Invitation::factory()->expired()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/invitations/accept', [
            'token' => $invitation->token,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Invalid invitation: expired');
    }
}
