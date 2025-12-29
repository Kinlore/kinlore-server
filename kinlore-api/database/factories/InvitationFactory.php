<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'token' => Invitation::generateToken(),
            'email' => $this->faker->safeEmail(),
            'role' => 'viewer',
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => now()->subDay(),
            ];
        });
    }

    public function accepted()
    {
        return $this->state(function (array $attributes) {
            return [
                'accepted_at' => now(),
            ];
        });
    }
}
