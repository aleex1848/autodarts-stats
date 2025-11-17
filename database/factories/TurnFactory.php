<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Turn>
 */
class TurnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leg_id' => \App\Models\Leg::factory(),
            'player_id' => \App\Models\Player::factory(),
            'autodarts_turn_id' => fake()->uuid(),
            'round_number' => 1,
            'turn_number' => 0,
            'points' => 0,
            'score_after' => 501,
            'busted' => false,
            'started_at' => now(),
        ];
    }
}
