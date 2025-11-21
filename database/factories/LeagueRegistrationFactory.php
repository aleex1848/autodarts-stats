<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\League;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeagueRegistration>
 */
class LeagueRegistrationFactory extends Factory
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
            'player_id' => Player::factory(),
            'user_id' => User::factory(),
            'status' => RegistrationStatus::Pending->value,
            'registered_at' => now(),
        ];
    }
}
