<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Kid;
use App\Models\User;
use App\Models\Moment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KidTest extends TestCase
{
    use RefreshDatabase;

    public function test_kid_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $kid = Kid::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $kid->user->id);
    }

    public function test_kid_calculates_age_correctly(): void
    {
        $kid = Kid::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(5)->subMonths(3),
        ]);

        $this->assertSame(5, $kid->age);
    }

    public function test_kid_calculates_age_in_months_correctly(): void
    {
        $kid = Kid::factory()->create([
            'date_of_birth' => Carbon::now()->subMonths(18),
        ]);

        $this->assertSame(18, $kid->age_in_months);
    }

    public function test_kid_generates_detailed_age_string(): void
    {
        $kid = Kid::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(3)->subMonths(6),
        ]);

        $this->assertSame('3 years, 6 months', $kid->age_detailed);
    }

    public function test_kid_generates_random_avatar_color_on_creation(): void
    {
        $kid = Kid::factory()->create(['avatar_color' => null]);

        $this->assertNotNull($kid->avatar_color);
        $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $kid->avatar_color);
    }

    public function test_kid_can_get_moments_count(): void
    {
        $kid = Kid::factory()->create();
        Moment::factory()->count(3)->create(['kid_id' => $kid->id]);

        $this->assertEquals(3, $kid->getMomentsCount());
    }

    public function test_kid_can_get_first_moment(): void
    {
        $kid = Kid::factory()->create();
        $firstMoment = Moment::factory()->create([
            'kid_id' => $kid->id,
            'exif_date' => Carbon::now()->subYears(2),
        ]);
        Moment::factory()->create([
            'kid_id' => $kid->id,
            'exif_date' => Carbon::now()->subYear(),
        ]);

        $this->assertEquals($firstMoment->id, $kid->getFirstMoment()->id);
    }

    public function test_kid_can_get_latest_moment(): void
    {
        $kid = Kid::factory()->create();
        Moment::factory()->create([
            'kid_id' => $kid->id,
            'exif_date' => Carbon::now()->subYears(2),
        ]);
        $latestMoment = Moment::factory()->create([
            'kid_id' => $kid->id,
            'exif_date' => Carbon::now()->subDay(),
        ]);

        $this->assertEquals($latestMoment->id, $kid->getLatestMoment()->id);
    }
}
