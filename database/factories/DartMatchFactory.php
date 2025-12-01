<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DartMatch>
 */
class DartMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'autodarts_match_id' => \fake()->uuid(),
            'variant' => 'X01',
            'type' => 'Online',
            'base_score' => 501,
            'in_mode' => 'Straight',
            'out_mode' => 'Straight',
            'bull_mode' => '25/50',
            'max_rounds' => 20,
            'started_at' => now(),
        ];
    }
}
