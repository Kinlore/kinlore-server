<?php

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fixes from the 2026-11-22 audit: sync paging, password reset, large files as streams.
 */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_pull_pages_moments_and_sends_kids_once(): void
    {
        $user = User::factory()->admin()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        $ids = Moment::factory()->count(5)->create(['kid_id' => $kid->id])->pluck('id')->sort()->values()->all();
        Sanctum::actingAs($user);

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $page = $this->postJson('/api/v1/sync/pull', ['lastPulledAt' => null, 'schemaVersion' => 2, 'cursor' => $cursor, 'limit' => 2])
                ->assertOk()->json();
            $pages++;
            $this->assertCount($cursor === null ? 1 : 0, $page['changes']['kids']['created'], 'kids come with the first page only');
            $this->assertLessThanOrEqual(2, count($page['changes']['moments']['created']));
            $seen = array_merge($seen, array_column($page['changes']['moments']['created'], 'id'));
            $cursor = $page['next_cursor'];
            $this->assertSame($cursor !== null, $page['has_more']);
        } while ($cursor !== null);

        $this->assertSame(3, $pages);
        $this->assertSame($ids, $seen, 'every moment exactly once, in id order');
    }

    public function test_sync_pull_without_paging_fields_returns_everything_below_the_page_size(): void
    {
        $user = User::factory()->admin()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->count(3)->create(['kid_id' => $kid->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sync/pull', ['lastPulledAt' => null, 'schemaVersion' => 2])
            ->assertOk()
            ->assertJsonCount(3, 'changes.moments.created')
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('next_cursor', null);
    }

    public function test_admin_sets_a_member_password_and_the_member_is_signed_out(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->family()->create(['password' => 'old-password']);
        $member->createToken('phone');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/members/{$member->id}", ['password' => 'short'])->assertStatus(422);
        $this->patchJson("/api/v1/members/{$member->id}", ['password' => 'new-password-1'])->assertOk();

        $member->refresh();
        $this->assertTrue(Hash::check('new-password-1', $member->password));
        $this->assertSame(0, $member->tokens()->count());
        $this->assertSame('family', $member->role, 'the role is not touched');
    }

    public function test_only_the_admin_sets_passwords(): void
    {
        User::factory()->admin()->create();
        $partner = User::factory()->create();
        $member = User::factory()->family()->create();
        Sanctum::actingAs($partner);

        $this->patchJson("/api/v1/members/{$member->id}", ['password' => 'new-password-1'])->assertStatus(403);
    }

    public function test_reset_password_command(): void
    {
        $user = User::factory()->admin()->create(['email' => 'ann@example.com']);
        $user->createToken('phone');

        $this->artisan('kinlore:reset-password', ['email' => 'nobody@example.com', '--password' => 'new-password-1'])->assertFailed();
        $this->artisan('kinlore:reset-password', ['email' => 'ann@example.com', '--password' => 'short'])->assertFailed();
        $this->artisan('kinlore:reset-password', ['email' => 'ann@example.com', '--password' => 'new-password-1'])->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password-1', $user->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_original_file_is_streamed_with_its_full_content(): void
    {
        Storage::fake('seaweedfs');
        $admin = User::factory()->admin()->create();
        $kid = Kid::factory()->create(['user_id' => $admin->id]);
        $moment = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'video', 'mime_type' => 'video/mp4', 'photo_url' => 'media/k/v/original.mp4']);
        $bytes = random_bytes(200_000);
        Storage::disk('seaweedfs')->put('media/k/v/original.mp4', $bytes);
        Sanctum::actingAs($admin);

        $response = $this->get("/api/v1/moments/{$moment->id}/file/original")->assertOk()
            ->assertHeader('content-length', '200000');
        $this->assertSame($bytes, $response->streamedContent());
    }

    public function test_upload_is_stored_complete(): void
    {
        Storage::fake('seaweedfs');
        $admin = User::factory()->admin()->create();
        $kid = Kid::factory()->create(['user_id' => $admin->id]);
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);
        $size = $file->getSize();
        $id = $this->post('/api/v1/moments', ['kid_id' => $kid->id, 'file' => $file], ['Accept' => 'application/json'])
            ->assertSuccessful()->json('data.moment.id');

        $path = Moment::find($id)->getRawOriginal('photo_url');
        $this->assertSame($size, Storage::disk('seaweedfs')->size($path));
    }

    public function test_api_answers_json_without_an_accept_header(): void
    {
        // A browser or plain curl once got a 500 page here (the missing "login" route)
        $this->get('/api/v1/kids')->assertStatus(401)->assertJsonPath('success', false);
        $this->get('/api/v1/nothing-here')->assertStatus(404)->assertJsonPath('success', false);
    }
}
