<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_server_members()
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();
        ServerMember::factory()->create(['user_id' => $user->id, 'server_id' => $server->id]);
        ServerMember::factory()->count(3)->create(['server_id' => $server->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/servers/{$server->id}/members");

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_owner_can_remove_member()
    {
        $owner = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $owner->id]);
        ServerMember::factory()->create(['user_id' => $owner->id, 'server_id' => $server->id, 'role' => 'owner']);

        $member = User::factory()->create();
        $membership = ServerMember::factory()->create(['user_id' => $member->id, 'server_id' => $server->id, 'role' => 'viewer']);

        $response = $this->actingAs($owner)->deleteJson("/api/v1/servers/{$server->id}/members/{$membership->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('server_members', ['id' => $membership->id]);
    }

    public function test_viewer_cannot_remove_member()
    {
        $viewer = User::factory()->create();
        $server = Server::factory()->create();
        ServerMember::factory()->create(['user_id' => $viewer->id, 'server_id' => $server->id, 'role' => 'viewer']);

        $otherMember = ServerMember::factory()->create(['server_id' => $server->id]);

        $response = $this->actingAs($viewer)->deleteJson("/api/v1/servers/{$server->id}/members/{$otherMember->id}");

        $response->assertStatus(403);
    }
}
