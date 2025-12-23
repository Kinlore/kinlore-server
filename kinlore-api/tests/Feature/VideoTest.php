<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class VideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('seaweedfs');
    }

    public function test_user_can_upload_video(): void
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('video.mp4', 10000, 'video/mp4');

        $response = $this->postJson('/api/videos/upload', [
            'video' => $file,
            'original_filename' => 'my-video.mp4',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'status',
                'original_filename',
                'file_size',
                'file_size_formatted',
            ],
        ]);

        // Verify video record was created
        $this->assertDatabaseHas('videos', [
            'user_id' => $user->id,
            'original_filename' => 'my-video.mp4',
            'status' => 'uploading',
        ]);
    }

    public function test_video_upload_requires_video_file(): void
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/videos/upload', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['video']);
    }

    public function test_video_upload_validates_file_type(): void
    {
        $user = User::factory()->create();
        
        Sanctum::actingAs($user);

        // Try uploading a text file
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $response = $this->postJson('/api/videos/upload', [
            'video' => $file,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['video']);
    }

    public function test_user_can_list_their_videos(): void
    {
        $user = User::factory()->create();
        Video::factory()->count(3)->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/videos');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_see_other_users_videos(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Video::factory()->count(2)->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/videos');

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }

    public function test_user_can_check_video_status(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/videos/{$video->id}/status");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'processing');
    }

    public function test_user_cannot_check_other_users_video_status(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/videos/{$video->id}/status");

        $response->assertStatus(403);
    }

    public function test_user_can_delete_their_video(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/videos/{$video->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('videos', ['id' => $video->id]);
    }

    public function test_user_cannot_delete_other_users_video(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $video = Video::factory()->create(['user_id' => $otherUser->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/videos/{$video->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('videos', ['id' => $video->id]);
    }

    public function test_user_can_filter_videos_by_status(): void
    {
        $user = User::factory()->create();
        Video::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
        Video::factory()->create(['user_id' => $user->id, 'status' => 'processing']);
        Video::factory()->create(['user_id' => $user->id, 'status' => 'failed']);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/videos?status=completed');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }
}
