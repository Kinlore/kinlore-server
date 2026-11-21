<?php

namespace Database\Factories;

use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    public function definition(): array
    {
        $stages = [
            ['key' => 'infancy_0_12m', 'label' => 'Infancy (0–12 months)'],
            ['key' => 'toddler_1_3y', 'label' => 'Toddlerhood (1–3 years)'],
            ['key' => 'early_childhood_3_6y', 'label' => 'Early Childhood (3–6 years)'],
        ];
        
        $stage = $this->faker->randomElement($stages);
        
        return [
            'id' => $this->faker->unique()->slug(3),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(2),
            'stage_key' => $stage['key'],
            'stage_label' => $stage['label'],
            'sort_order' => $this->faker->numberBetween(0, 20),
        ];
    }
}
