<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_server()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/servers', [
            'name' => 'My New Family',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'My New Family')
            ->assertJsonPath('data.owner_id', $user->id);

        $this->assertDatabaseHas('servers', [
            'name' => 'My New Family',
            'owner_id' => $user->id,
        ]);
        
        $this->assertDatabaseHas('server_members', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_can_list_their_servers()
    {
        $user = User::factory()->create();
        
        // Create server 1 (owned)
        $server1 = Server::factory()->create(['owner_id' => $user->id]);
        ServerMember::factory()->create(['user_id' => $user->id, 'server_id' => $server1->id, 'role' => 'owner']);
        
        // Create server 2 (member)
        $server2 = Server::factory()->create();
        ServerMember::factory()->create(['user_id' => $user->id, 'server_id' => $server2->id, 'role' => 'viewer']);
        
        // Create server 3 (not member)
        Server::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/user/servers');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_only_owner_can_update_server()
    {
        $owner = User::factory()->create();
        $server = Server::factory()->create(['owner_id' => $owner->id]);
        ServerMember::factory()->create(['user_id' => $owner->id, 'server_id' => $server->id, 'role' => 'owner']);

        $otherUser = User::factory()->create();
        ServerMember::factory()->create(['user_id' => $otherUser->id, 'server_id' => $server->id, 'role' => 'viewer']);

        // Owner can update
        $response = $this->actingAs($owner)->putJson("/api/v1/servers/{$server->id}", [
            'name' => 'Updated Name',
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('servers', ['id' => $server->id, 'name' => 'Updated Name']);

        // Other member cannot update
        $response = $this->actingAs($otherUser)->putJson("/api/v1/servers/{$server->id}", [
            'name' => 'Hacked Name',
        ]);
        $response->assertStatus(403);
    }
}
