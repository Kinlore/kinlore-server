<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Kid;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MomentFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'kid_id' => Kid::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'photo_url' => 'moments/test/originals/'.Str::uuid().'.jpg',
            'medium_url' => 'moments/test/medium/'.Str::uuid().'.jpg',
            'thumbnail_url' => 'moments/test/thumbnails/'.Str::uuid().'.jpg',
            'file_size' => fake()->numberBetween(100000, 5000000),
            'mime_type' => 'image/jpeg',
            'original_filename' => fake()->word().'.jpg',
            'exif_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
        ];
    }

    public function withLocation(): static
    {
        return $this->state(fn (): array => [
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
        ]);
    }
}
