<?php

namespace Database\Factories;

use App\Enums\FixtureStatus;
use App\Models\Matchday;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MatchdayFixture>
 */
class MatchdayFixtureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matchday_id' => Matchday::factory(),
            'home_player_id' => Player::factory(),
            'away_player_id' => Player::factory(),
            'status' => FixtureStatus::Scheduled->value,
            'points_awarded_home' => 0,
            'points_awarded_away' => 0,
        ];
    }
}
