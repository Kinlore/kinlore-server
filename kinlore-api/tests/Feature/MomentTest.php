<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Media;
use App\Models\Milestone;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
        $user = User::factory()->family()->create();
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
        $user = User::factory()->family()->create();
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

    public function test_story_title_is_stored_and_uploader_is_set(): void
    {
        $user = User::factory()->create(['name' => 'Sam']);
        $kid = Kid::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'story',
            'title' => 'Beach day',
            'description' => 'Sand everywhere',
        ])->assertStatus(201)
            ->assertJsonPath('data.moment.title', 'Beach day')
            ->assertJsonPath('data.moment.user_id', $user->id)
            ->assertJsonPath('data.moment.uploader_name', 'Sam')
            ->assertJsonPath('data.moment.likes_count', 0)
            ->assertJsonPath('data.moment.is_liked', false);

        $this->assertNotNull($response->json('data.moment.display_date'));
        $this->assertDatabaseHas('moments', ['title' => 'Beach day', 'user_id' => $user->id]);
    }

    public function test_milestone_title_is_stored(): void
    {
        $kid = Kid::factory()->create();
        Milestone::factory()->create(['id' => 'first_step']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'milestone',
            'milestone_id' => 'first_step',
            'title' => 'Walked to the cat',
        ])->assertStatus(201)->assertJsonPath('data.moment.title', 'Walked to the cat');
    }

    public function test_family_user_without_add_content_cannot_create_moment(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->family(['can_add_content' => false])->create());

        $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'story',
            'description' => 'Not allowed',
        ])->assertStatus(403)->assertJsonPath('message', 'You do not have permission to add content.');
    }

    public function test_update_changes_exif_date(): void
    {
        $user = User::factory()->create();
        $moment = Moment::factory()->create(['moment_type' => 'story']);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/moments/{$moment->id}", ['exif_date' => '2024-05-01T08:00:00Z'])
            ->assertOk()
            ->assertJsonPath('data.exif_date', '2024-05-01T08:00:00+00:00')
            ->assertJsonPath('data.display_date', '2024-05-01T08:00:00+00:00');
    }

    public function test_uploader_can_update_own_moment_on_another_users_kid(): void
    {
        $family = User::factory()->family()->create();
        $moment = Moment::factory()->create(['user_id' => $family->id]);

        Sanctum::actingAs($family);

        $this->putJson("/api/v1/moments/{$moment->id}", ['title' => 'Mine'])->assertOk();
    }

    public function test_list_filters_and_sorts_by_display_date(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create();
        $old = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'photo', 'exif_date' => '2024-01-10 10:00:00']);
        $new = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'photo', 'exif_date' => '2024-03-10 10:00:00']);
        $story = Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => 'story', 'exif_date' => '2024-02-10 10:00:00']);
        Moment::factory()->create(['moment_type' => 'video', 'exif_date' => '2024-02-11 10:00:00']);
        $story->interactions()->create(['user_id' => $user->id, 'type' => 'like']);

        Sanctum::actingAs($user);

        $ids = fn ($query) => array_column($this->getJson("/api/v1/moments?{$query}")->assertOk()->json('data'), 'id');

        $this->assertSame([$new->id, $story->id, $old->id], $ids("kid_id={$kid->id}"));
        $this->assertSame([$new->id, $story->id, $old->id], $ids("kid_id={$kid->id}&moment_type=photo,story"));
        $this->assertSame([$new->id, $old->id], $ids('moment_type=photo'));
        $this->assertSame([$story->id], $ids('liked=true'));
        $this->assertSame([$story->id], $ids("kid_id={$kid->id}&from=2024-02-01&to=2024-02-10"));
        $this->assertCount(1, $ids('per_page=1'));

        $this->getJson('/api/v1/moments?moment_type=bogus')->assertStatus(422);
        $this->getJson('/api/v1/moments?per_page=101')->assertStatus(422);
        $this->getJson('/api/v1/moments?liked=true')->assertJsonPath('data.0.is_liked', true)->assertJsonPath('data.0.likes_count', 1);
    }

    public function test_original_file_needs_download_permission(): void
    {
        $moment = Moment::factory()->create();
        Storage::disk('seaweedfs')->put($moment->getRawOriginal('photo_url'), 'jpeg-bytes');
        Storage::disk('seaweedfs')->put($moment->getRawOriginal('thumbnail_url'), 'thumb-bytes');

        Sanctum::actingAs(User::factory()->family(['can_download' => false])->create());
        $this->get("/api/v1/moments/{$moment->id}/file/original")->assertStatus(403);
        $this->get("/api/v1/moments/{$moment->id}/file/thumbnail")->assertOk();

        Sanctum::actingAs(User::factory()->family()->create());
        $this->get("/api/v1/moments/{$moment->id}/file/original")->assertOk();
    }
}
