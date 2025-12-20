<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Moment;
use App\Models\Kid;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MomentTest extends TestCase
{
    use RefreshDatabase;

    public function test_moment_belongs_to_kid(): void
    {
        $kid = Kid::factory()->create();
        $moment = Moment::factory()->create(['kid_id' => $kid->id]);

        $this->assertEquals($kid->id, $moment->kid->id);
    }

    public function test_moment_formats_file_size(): void
    {
        $moment = Moment::factory()->create([
            'file_size' => 1024 * 1024 * 2.5, // 2.5MB
        ]);

        $this->assertStringContainsString('MB', $moment->file_size_formatted);
    }

    public function test_moment_detects_location_presence(): void
    {
        $momentWithLocation = Moment::factory()->create([
            'latitude' => 50.8503,
            'longitude' => 4.3517,
        ]);

        $momentWithoutLocation = Moment::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->assertTrue($momentWithLocation->has_location);
        $this->assertFalse($momentWithoutLocation->has_location);
    }

    public function test_moment_can_add_tags(): void
    {
        $moment = Moment::factory()->create(['tags' => null]);

        $moment->addTag('birthday');
        $moment->addTag('family');

        $this->assertCount(2, $moment->tags);
        $this->assertContains('birthday', $moment->tags);
        $this->assertContains('family', $moment->tags);
    }

    public function test_moment_does_not_add_duplicate_tags(): void
    {
        $moment = Moment::factory()->create(['tags' => ['birthday']]);

        $moment->addTag('birthday');

        $this->assertCount(1, $moment->tags);
    }

    public function test_moment_can_remove_tags(): void
    {
        $moment = Moment::factory()->create(['tags' => ['birthday', 'family']]);

        $moment->removeTag('birthday');

        $this->assertCount(1, $moment->tags);
        $this->assertNotContains('birthday', $moment->tags);
    }

    public function test_moment_uses_exif_date_as_display_date_when_available(): void
    {
        $exifDate = now()->subDays(5);
        $moment = Moment::factory()->create([
            'exif_date' => $exifDate,
        ]);

        $this->assertEquals(
            $exifDate->format('Y-m-d H:i:s'),
            $moment->display_date->format('Y-m-d H:i:s')
        );
    }

    public function test_moment_falls_back_to_created_at_when_no_exif_date(): void
    {
        $moment = Moment::factory()->create([
            'exif_date' => null,
        ]);

        $this->assertEquals(
            $moment->created_at->format('Y-m-d'),
            $moment->display_date->format('Y-m-d')
        );
    }
}