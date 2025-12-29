<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\ServerMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerMemberFactory extends Factory
{
    protected $model = ServerMember::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'server_id' => Server::factory(),
            'role' => 'viewer',
            'joined_at' => now(),
        ];
    }
}
