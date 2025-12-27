<?php

namespace Database\Seeders;

use App\Models\PredefinedMilestone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PredefinedMilestoneSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Reads milestones from milestones.json and seeds predefined_milestones table.
     * Images are bundled with the mobile app as assets/milestones/{id}.png
     */
    public function run(): void
    {
        // Read milestones from JSON file
        $jsonPath = database_path('seeders/milestones.json');
        
        if (!File::exists($jsonPath)) {
            $this->command->error("milestones.json not found at: {$jsonPath}");
            return;
        }

        $json = File::get($jsonPath);
        $data = json_decode($json, true);

        if (!isset($data['storyMilestones'])) {
            $this->command->error("Invalid milestones.json structure: 'storyMilestones' key not found");
            return;
        }

        $sortOrder = 0;

        foreach ($data['storyMilestones'] as $stage) {
            $stageKey = $stage['stageKey'];
            $stageLabel = $stage['stageLabel'];

            foreach ($stage['milestones'] as $milestone) {
                $sortOrder++;

                PredefinedMilestone::updateOrCreate(
                    ['id' => $milestone['id']],
                    [
                        'title' => $milestone['title'],
                        'description' => $milestone['description'],
                        'stage_key' => $stageKey,
                        'stage_label' => $stageLabel,
                        'image_path' => "assets/milestones/{$milestone['id']}.png",
                        'sort_order' => $sortOrder,
                    ]
                );
            }
        }

        $this->command->info("Seeded {$sortOrder} predefined milestones from milestones.json");
    }
}
