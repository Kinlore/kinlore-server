<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Kid;
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
        Storage::fake('minio');
    }

    public function test_user_can_list_moments_for_their_kid(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->count(5)->create(['kid_id' => $kid->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/kids/{$kid->id}/moments");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'title', 'thumbnail_url', 'medium_url', 'photo_url'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_user_cannot_list_moments_for_another_users_kid(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/kids/{$kid->id}/moments");

        $response->assertStatus(403);
    }

    public function test_user_can_upload_photo(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->postJson("/api/kids/{$kid->id}/moments", [
            'photo' => $file,
            'title' => 'Test Photo',
            'description' => 'Test Description',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Test Photo');
        $response->assertJsonPath('data.description', 'Test Description');

        $this->assertDatabaseHas('moments', [
            'kid_id' => $kid->id,
            'title' => 'Test Photo',
        ]);

        // Verify files were created
        $momentId = $response->json('data.id');
        Storage::disk('seaweedfs')->assertExists("moments/{$kid->id}/originals/{$momentId}.jpg");
        Storage::disk('seaweedfs')->assertExists("moments/{$kid->id}/medium/{$momentId}.jpg");
        Storage::disk('seaweedfs')->assertExists("moments/{$kid->id}/thumbnails/{$momentId}.jpg");
    }

    public function test_photo_upload_requires_image_file(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/kids/{$kid->id}/moments", [
            'title' => 'Test Photo',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['photo']);
    }

    public function test_user_can_upload_photo_with_tags(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->postJson("/api/kids/{$kid->id}/moments", [
            'photo' => $file,
            'tags' => ['birthday', 'family'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('moments', [
            'kid_id' => $kid->id,
        ]);

        $moment = Moment::first();
        $this->assertEquals(['birthday', 'family'], $moment->tags);
    }

    public function test_user_can_get_uploaded_identifiers(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        Moment::factory()->create([
            'kid_id' => $kid->id,
            'device_asset_id' => 'device-123',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/kids/{$kid->id}/moments/uploaded-identifiers");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['device_asset_id', 'device_fingerprint', 'file_size', 'filename'],
            ],
        ]);
    }

    public function test_user_can_delete_their_moment(): void
    {
        Storage::fake('minio');
        
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        $moment = Moment::factory()->create(['kid_id' => $kid->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/moments/{$moment->id}");

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

        $response = $this->deleteJson("/api/moments/{$moment->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('moments', ['id' => $moment->id]);
    }

    public function test_user_can_create_story_moment_with_exif_date(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/kids/{$kid->id}/moments", [
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
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/kids/{$kid->id}/moments", [
            'moment_type' => 'milestone',
            'milestone_id' => 'first_smile', // Assuming this exists or using a string
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
}
