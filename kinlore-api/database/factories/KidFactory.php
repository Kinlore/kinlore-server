<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KidFactory extends Factory
{
    #[\Override]
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'date_of_birth' => fake()->dateTimeBetween('-10 years', '-1 year'),
            'avatar_color' => fake()->randomElement([
                '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', 
                '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'
            ]),
        ];
    }
}
