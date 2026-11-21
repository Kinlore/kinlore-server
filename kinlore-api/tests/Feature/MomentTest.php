<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Kid;
use App\Models\Media;
use App\Models\Milestone;
use App\Models\Moment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class MomentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.default'));
        Storage::fake('seaweedfs'); // FileStorageService default disk
    }

    public function test_user_can_list_moments_for_their_kid(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->count(5)->create(['kid_id' => $kid->id]);
        Moment::factory()->count(2)->create(); // other kid, filtered out

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/moments?kid_id={$kid->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'kid_id', 'title', 'thumbnail_url', 'medium_url', 'photo_url'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_user_can_list_moments_for_another_users_kid(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $otherUser->id]);
        Moment::factory()->count(2)->create(['kid_id' => $kid->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/moments?kid_id={$kid->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_unauthenticated_user_cannot_list_moments(): void
    {
        $this->getJson('/api/v1/moments')->assertStatus(401);
    }

    public function test_user_can_upload_photo(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            'title' => 'Test Photo',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.moment.title', 'Test Photo');
        $response->assertJsonPath('data.moment.description', 'Test Description');
        $response->assertJsonPath('data.moment.moment_type', 'photo');

        $this->assertDatabaseHas('moments', [
            'kid_id' => $kid->id,
            'title' => 'Test Photo',
            'moment_type' => 'photo',
        ]);

        $media = Media::findOrFail($response->json('data.media.id'));
        $disk = Storage::disk('seaweedfs');
        $this->assertSame(Media::STATUS_COMPLETED, $media->status);
        $disk->assertExists($media->getRawOriginal('file_path'));
        $disk->assertExists($media->getRawOriginal('medium_url'));
        $disk->assertExists($media->getRawOriginal('thumbnail_url'));
    }

    public function test_photo_upload_requires_file(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'title' => 'Test Photo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);
    }

    public function test_moment_creation_requires_kid(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/moments', [
            'moment_type' => 'story',
            'description' => 'A story',
        ])->assertStatus(422)->assertJsonValidationErrors(['kid_id']);
    }

    public function test_user_can_get_uploaded_identifiers(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->create([
            'kid_id' => $kid->id,
            'moment_type' => 'photo',
            'device_asset_id' => 'device-123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/kids/{$kid->id}/moments/uploaded-identifiers");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.device_asset_id', 'device-123');
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['device_asset_id', 'device_fingerprint', 'file_size', 'filename'],
            ],
        ]);
    }

    public function test_user_can_view_another_users_moment(): void
    {
        $user = User::factory()->create();
        $moment = Moment::factory()->create(['title' => 'Shared']);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/moments/{$moment->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $moment->id)
            ->assertJsonPath('data.title', 'Shared');
    }

    public function test_user_can_update_their_moment(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        $moment = Moment::factory()->create(['kid_id' => $kid->id]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/moments/{$moment->id}", ['title' => 'New title'])
            ->assertStatus(200)
            ->assertJsonPath('data.title', 'New title');
        $this->assertDatabaseHas('moments', ['id' => $moment->id, 'title' => 'New title']);
    }

    public function test_user_cannot_update_another_users_moment(): void
    {
        $user = User::factory()->create();
        $moment = Moment::factory()->create(['title' => 'Original']);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/moments/{$moment->id}", ['title' => 'Hacked'])->assertStatus(403);
        $this->assertDatabaseHas('moments', ['id' => $moment->id, 'title' => 'Original']);
    }

    public function test_user_can_delete_their_moment(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        $moment = Moment::factory()->create(['kid_id' => $kid->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/moments/{$moment->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('moments', ['id' => $moment->id]);
    }

    public function test_user_cannot_delete_another_users_moment(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $otherUser->id]);
        $moment = Moment::factory()->create(['kid_id' => $kid->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/moments/{$moment->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('moments', ['id' => $moment->id]);
    }

    public function test_admin_can_delete_another_users_moment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherUser = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $otherUser->id]);
        $moment = Moment::factory()->create(['kid_id' => $kid->id]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/moments/{$moment->id}")->assertStatus(200);
        $this->assertSoftDeleted('moments', ['id' => $moment->id]);
    }

    public function test_user_can_create_story_moment_with_exif_date(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'story',
            'description' => 'A beautiful story',
            'exif_date' => '2025-12-28 10:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'story',
            'description' => 'A beautiful story',
            'exif_date' => '2025-12-28 10:00:00',
        ]);
    }

    public function test_user_can_create_milestone_moment_with_exif_date(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Milestone::factory()->create(['id' => 'first_smile']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'milestone',
            'milestone_id' => 'first_smile',
            'description' => 'First smile ever',
            'exif_date' => '2025-12-28 10:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'milestone',
            'milestone_id' => 'first_smile',
            'exif_date' => '2025-12-28 10:00:00',
        ]);
    }

    public function test_milestone_moment_requires_existing_milestone(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'milestone',
            'milestone_id' => 'does-not-exist',
        ])->assertStatus(422)->assertJsonValidationErrors(['milestone_id']);
    }
}
