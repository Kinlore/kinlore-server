<?php

namespace Database\Factories;

use App\Models\CustomMilestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomMilestoneFactory extends Factory
{
    protected $model = CustomMilestone::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'icon_image_id' => null,
        ];
    }
}
