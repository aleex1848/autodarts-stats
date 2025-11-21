<?php

namespace Database\Factories;

use App\Models\League;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Matchday>
 */
class MatchdayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'league_id' => League::factory(),
            'matchday_number' => 1,
            'is_return_round' => false,
            'deadline_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'is_playoff' => false,
        ];
    }
}
