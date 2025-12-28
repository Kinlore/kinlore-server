<?php

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

    /**
     * Run the database seeds.
     * 
     * Reads milestones from milestones.json and seeds the milestones table.
     * Uses updateOrCreate to prevent duplicates when run multiple times (e.g., via Docker).
     */
    public function run(): void
    {
        // Read the JSON file
        $jsonPath = database_path('seeders/milestones.json');
        
        if (!File::exists($jsonPath)) {
            $this->command->error('milestones.json not found at: ' . $jsonPath);
            return;
        }

        $json = File::get($jsonPath);
        $data = json_decode($json, true);

        if (!$data || !isset($data['storyMilestones'])) {
            $this->command->error('Invalid JSON structure in milestones.json');
            return;
        }

        $totalCount = 0;
        $sortOrder = 0;

        foreach ($data['storyMilestones'] as $stage) {
            $stageKey = $stage['stageKey'];
            $stageLabel = $stage['stageLabel'];
            $milestones = $stage['milestones'] ?? [];
            
            // Get age range for this stage
            $ageRange = self::STAGE_AGE_RANGES[$stageKey] ?? ['min' => null, 'max' => null];

            foreach ($milestones as $milestone) {
                $sortOrder++;
                
                Milestone::updateOrCreate(
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
                $totalCount++;
            }
        }

        $this->command->info("Seeded {$totalCount} milestones across " . count($data['storyMilestones']) . " stages.");
    }
}
