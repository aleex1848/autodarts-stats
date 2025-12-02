<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SeasonParticipant>
 */
class SeasonParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'player_id' => Player::factory(),
            'points' => 0,
            'matches_played' => 0,
            'matches_won' => 0,
            'matches_lost' => 0,
            'matches_draw' => 0,
            'legs_won' => 0,
            'legs_lost' => 0,
            'penalty_points' => 0,
        ];
    }
}
