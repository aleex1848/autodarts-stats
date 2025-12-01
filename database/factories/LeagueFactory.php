<?php

namespace Database\Factories;

use App\Enums\LeagueMatchFormat;
use App\Enums\LeagueMode;
use App\Enums\LeagueStatus;
use App\Enums\LeagueVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\League>
 */
class LeagueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = \fake()->words(3, true) . ' Liga';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => \fake()->optional()->sentence(),
            'max_players' => \fake()->randomElement([8, 10, 12, 16, 20]),
            'mode' => \fake()->randomElement(LeagueMode::cases())->value,
            'variant' => \fake()->randomElement(LeagueVariant::cases())->value,
            'match_format' => \fake()->randomElement(LeagueMatchFormat::cases())->value,
            'registration_deadline' => \fake()->optional()->dateTimeBetween('now', '+30 days'),
            'days_per_matchday' => \fake()->numberBetween(3, 14),
            'status' => LeagueStatus::Registration->value,
            'created_by_user_id' => User::factory(),
        ];
    }
}
