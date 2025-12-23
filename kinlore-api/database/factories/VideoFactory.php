<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => $this->faker->word() . '.mp4',
            'file_size' => $this->faker->numberBetween(1000000, 500000000), // 1MB to 500MB
            'mime_type' => 'video/mp4',
            'duration' => $this->faker->randomFloat(2, 5, 600), // 5 seconds to 10 minutes
            'status' => $this->faker->randomElement(['uploading', 'processing', 'completed', 'failed']),
            'storage_path' => 'videos/' . $this->faker->uuid() . '/original.mp4',
            'hls_url' => null,
            'thumbnail_url' => null,
            'error_message' => null,
        ];
    }

    /**
     * Video in uploading state
     */
    public function uploading(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'uploading',
            'hls_url' => null,
            'thumbnail_url' => null,
            'duration' => null,
        ]);
    }

    /**
     * Video in processing state
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processing_started_at' => now(),
            'hls_url' => null,
            'thumbnail_url' => null,
        ]);
    }

    /**
     * Video in completed state
     */
    public function completed(): static
    {
        $id = $this->faker->uuid();
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'processing_started_at' => now()->subMinutes(5),
            'processing_completed_at' => now(),
            'hls_url' => "videos/{$id}/hls/playlist.m3u8",
            'thumbnail_url' => "videos/{$id}/thumbnail.jpg",
        ]);
    }

    /**
     * Video in failed state
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'processing_started_at' => now()->subMinutes(5),
            'processing_completed_at' => now(),
            'error_message' => 'Video processing failed: Invalid codec',
        ]);
    }
}
