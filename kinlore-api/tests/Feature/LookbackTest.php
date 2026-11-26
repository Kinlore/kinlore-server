<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Kid;
use App\Models\Moment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LookbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_this_day_groups_earlier_years_newest_first(): void
    {
        $kid = Kid::factory()->create();
        $make = fn (string $date, string $type = 'photo') => Moment::factory()->create(['kid_id' => $kid->id, 'moment_type' => $type, 'exif_date' => $date]);
        $lastYear = $make('2025-11-21 09:00:00');
        $story = $make('2025-11-21 18:00:00', 'story');
        $twoYears = $make('2024-11-21 12:00:00', 'video');
        $make('2024-11-21 12:00:00', 'growth');   // wrong type
        $make('2025-11-22 12:00:00');             // wrong day
        $make('2026-11-21 08:00:00');             // this year
        foreach (range(1, 11) as $i) {
            $make('2023-11-21 10:00:00');         // more than 10 in one year
        }

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/lookback?date=2026-11-21')->assertOk();

        $days = $response->json('data.on_this_day');
        $this->assertSame([1, 2, 3], array_column($days, 'years_ago'));
        $this->assertSame(['2025-11-21', '2024-11-21', '2023-11-21'], array_column($days, 'date'));
        $this->assertSame([$story->id, $lastYear->id], array_column($days[0]['moments'], 'id'));
        $this->assertSame([$twoYears->id], array_column($days[1]['moments'], 'id'));
        $this->assertCount(10, $days[2]['moments']);
        $this->assertArrayHasKey('display_date', $days[0]['moments'][0]);

        $this->getJson('/api/v1/lookback?date=21-11-2026')->assertStatus(422);
    }

    public function test_then_and_now_pairs_dated_photos_from_birth_six_months_apart(): void
    {
        $mia = Kid::factory()->create(['name' => 'Mia', 'date_of_birth' => '2024-12-20']);
        // before birth (a pregnancy photo) and without a photo date: never part of the pair
        Moment::factory()->create(['kid_id' => $mia->id, 'moment_type' => 'photo', 'exif_date' => '2024-10-01 10:00:00']);
        Moment::factory()->create(['kid_id' => $mia->id, 'moment_type' => 'photo', 'exif_date' => null]);
        $first = Moment::factory()->create(['kid_id' => $mia->id, 'moment_type' => 'photo', 'exif_date' => '2025-01-01 10:00:00']);
        Moment::factory()->create(['kid_id' => $mia->id, 'moment_type' => 'photo', 'exif_date' => '2025-05-01 10:00:00']);
        $last = Moment::factory()->create(['kid_id' => $mia->id, 'moment_type' => 'photo', 'exif_date' => '2025-09-01 10:00:00']);
        Moment::factory()->create(['kid_id' => $mia->id, 'moment_type' => 'story', 'exif_date' => '2026-09-01 10:00:00']);

        $leo = Kid::factory()->create(['name' => 'Leo', 'date_of_birth' => '2024-12-20']);
        Moment::factory()->create(['kid_id' => $leo->id, 'moment_type' => 'photo', 'exif_date' => '2025-01-01 10:00:00']);
        Moment::factory()->create(['kid_id' => $leo->id, 'moment_type' => 'photo', 'exif_date' => '2025-05-01 10:00:00']);

        // not born yet: no pair, even with photos a year apart
        $ava = Kid::factory()->create(['name' => 'Ava', 'date_of_birth' => null, 'due_date' => '2027-01-01']);
        Moment::factory()->create(['kid_id' => $ava->id, 'moment_type' => 'photo', 'exif_date' => '2025-01-01 10:00:00']);
        Moment::factory()->create(['kid_id' => $ava->id, 'moment_type' => 'photo', 'exif_date' => '2026-01-01 10:00:00']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/lookback')
            ->assertOk()
            ->assertJsonCount(1, 'data.then_and_now')
            ->assertJsonPath('data.then_and_now.0.kid_id', $mia->id)
            ->assertJsonPath('data.then_and_now.0.kid_name', 'Mia')
            ->assertJsonPath('data.then_and_now.0.then.id', $first->id)
            ->assertJsonPath('data.then_and_now.0.now.id', $last->id);
    }
}
