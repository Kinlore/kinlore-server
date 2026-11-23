<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrowthAndDueDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_expecting_kid_can_be_created_with_due_date_only(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $due = now()->addMonths(2)->toDateString();

        $this->postJson('/api/v1/kids', ['name' => 'Bump', 'due_date' => $due])
            ->assertStatus(201)
            ->assertJsonPath('data.due_date', $due)
            ->assertJsonPath('data.date_of_birth', null)
            ->assertJsonPath('data.age', null)
            ->assertJsonPath('data.age_months', null)
            ->assertJsonPath('data.is_expecting', true);
    }

    public function test_kid_born_today_keeps_due_date_and_is_not_expecting(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/kids', [
            'name' => 'Newborn',
            'date_of_birth' => today()->toDateString(),
            'due_date' => today()->subDays(3)->toDateString(),
        ])->assertStatus(201)
            ->assertJsonPath('data.is_expecting', false)
            ->assertJsonPath('data.age', 0)
            ->assertJsonPath('data.age_months', 0);
    }

    public function test_kid_needs_date_of_birth_or_due_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/kids', ['name' => 'Nobody'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth', 'due_date']);

        $kid = Kid::factory()->create(['user_id' => $user->id, 'due_date' => null]);
        $this->putJson("/api/v1/kids/{$kid->id}", ['date_of_birth' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }

    public function test_family_user_cannot_create_kid(): void
    {
        Sanctum::actingAs(User::factory()->family()->create());

        $this->postJson('/api/v1/kids', ['name' => 'Emma', 'date_of_birth' => '2020-01-01'])->assertStatus(403);
    }

    public function test_growth_moment_is_created_with_measurements(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/moments', [
            'kid_id' => $kid->id,
            'moment_type' => 'growth',
            'exif_date' => '2026-10-01',
            'height_cm' => 61.5,
            'weight_kg' => 6.25,
            'description' => 'Check-up',
        ])->assertStatus(201)
            ->assertJsonPath('data.moment.moment_type', 'growth')
            ->assertJsonPath('data.moment.height_cm', 61.5)
            ->assertJsonPath('data.moment.weight_kg', 6.25)
            ->assertJsonPath('data.moment.head_circumference_cm', null);

        $this->assertDatabaseHas('moments', ['kid_id' => $kid->id, 'moment_type' => 'growth', 'height_cm' => 61.5]);
    }

    public function test_growth_moment_validation(): void
    {
        $kid = Kid::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'moment_type' => 'growth', 'exif_date' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['height_cm']);

        $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'moment_type' => 'growth', 'weight_kg' => 5])
            ->assertStatus(422)->assertJsonValidationErrors(['exif_date']);

        $this->postJson('/api/v1/moments', ['kid_id' => $kid->id, 'moment_type' => 'growth', 'exif_date' => '2026-10-01', 'weight_kg' => 900])
            ->assertStatus(422)->assertJsonValidationErrors(['weight_kg']);
    }

    public function test_growth_moment_can_be_updated(): void
    {
        $moment = Moment::factory()->create(['moment_type' => 'growth', 'height_cm' => 60, 'exif_date' => '2026-10-01']);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/moments/{$moment->id}", ['height_cm' => 62, 'head_circumference_cm' => 40.5])
            ->assertOk()
            ->assertJsonPath('data.height_cm', 62)
            ->assertJsonPath('data.head_circumference_cm', 40.5);

        $this->putJson("/api/v1/moments/{$moment->id}", ['height_cm' => -1])->assertStatus(422);
    }
}
