<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Milestone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MilestoneSeeder extends Seeder
{
    /**
     * Stage key to age range mapping (in months).
     * This provides a unified age range that simplifies database queries.
     */
    private const STAGE_AGE_RANGES = [
        'infancy_0_12m' => ['min' => 0, 'max' => 12],
        'toddler_1_3y' => ['min' => 12, 'max' => 36],
        'early_childhood_3_6y' => ['min' => 36, 'max' => 72],
        'middle_childhood_6_12y' => ['min' => 72, 'max' => 144],
        'adolescence_12_18y' => ['min' => 144, 'max' => 216],
        'young_adulthood_18_25y' => ['min' => 216, 'max' => 300],
        'partnership_marriage' => ['min' => 216, 'max' => null], // No upper limit
    ];

    // updateOrCreate prevents duplicates when the seeder runs more than once (e.g., via Docker).
    public function run(): void
    {
        $jsonPath = database_path('seeders/milestones.json');

        $this->command->info('Looking for milestones.json at: '.$jsonPath);

        if (! File::exists($jsonPath)) {
            $this->command->error('milestones.json not found at: '.$jsonPath);

            return;
        }

        $this->command->info('✓ Found milestones.json');

        $json = File::get($jsonPath);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->command->error('JSON parse error: '.json_last_error_msg());

            return;
        }

        if (! $data || ! isset($data['storyMilestones'])) {
            $this->command->error('Invalid JSON structure in milestones.json - missing storyMilestones key');
            $this->command->info('Keys found: '.implode(', ', array_keys($data ?? [])));

            return;
        }

        $this->command->info('✓ JSON parsed successfully');
        $this->command->info('Found '.count($data['storyMilestones']).' stages');

        $totalCount = 0;
        $sortOrder = 0;

        foreach ($data['storyMilestones'] as $stageIndex => $stage) {
            $stageKey = $stage['stageKey'];
            $stageLabel = $stage['stageLabel'];
            $milestones = $stage['milestones'] ?? [];

            // Get age range for this stage
            $ageRange = self::STAGE_AGE_RANGES[$stageKey] ?? ['min' => null, 'max' => null];

            $this->command->info("Processing stage [{$stageIndex}]: {$stageLabel} ({$stageKey}) - ".count($milestones).' milestones');

            foreach ($milestones as $milestone) {
                $sortOrder++;

                try {
                    $result = Milestone::updateOrCreate(
                        ['id' => $milestone['id']],
                        [
                            'title' => $milestone['title'],
                            'description' => $milestone['description'] ?? null,
                            'stage_key' => $stageKey,
                            'stage_label' => $stageLabel,
                            'age_min_months' => $ageRange['min'],
                            'age_max_months' => $ageRange['max'],
                            'sort_order' => $sortOrder,
                        ]
                    );

                    $action = $result->wasRecentlyCreated ? 'created' : 'updated';
                    $this->command->line("  [{$action}] {$milestone['id']}: {$milestone['title']}");

                    $totalCount++;
                } catch (\Exception $e) {
                    $this->command->error("  [FAILED] {$milestone['id']}: ".$e->getMessage());
                }
            }
        }

        $this->command->newLine();
        $this->command->info("✓ Seeded {$totalCount} milestones across ".count($data['storyMilestones']).' stages.');

        // Verify what's in the database
        $dbCount = Milestone::count();
        $this->command->info("Database milestone count: {$dbCount}");
    }
}
