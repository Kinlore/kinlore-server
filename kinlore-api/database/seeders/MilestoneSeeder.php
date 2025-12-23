<?php

namespace Database\Seeders;

use App\Models\Milestone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class MilestoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
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

        foreach ($data['storyMilestones'] as $stage) {
            $stageKey = $stage['stageKey'];
            $stageLabel = $stage['stageLabel'];
            $milestones = $stage['milestones'] ?? [];

            foreach ($milestones as $index => $milestone) {
                Milestone::updateOrCreate(
                    ['id' => $milestone['id']],
                    [
                        'title' => $milestone['title'],
                        'description' => $milestone['description'] ?? null,
                        'stage_key' => $stageKey,
                        'stage_label' => $stageLabel,
                        'sort_order' => $index,
                    ]
                );
                $totalCount++;
            }
        }

        $this->command->info("Seeded {$totalCount} milestones across " . count($data['storyMilestones']) . " stages.");
    }
}
