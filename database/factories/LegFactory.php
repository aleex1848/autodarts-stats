<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Leg>
 */
class LegFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_id' => \App\Models\DartMatch::factory(),
            'leg_number' => 1,
            'set_number' => 1,
            'started_at' => now(),
        ];
    }
}
