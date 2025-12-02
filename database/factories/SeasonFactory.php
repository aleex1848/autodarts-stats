<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true) . ' Saison';

        return [
            'league_id' => League::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'max_players' => fake()->randomElement([8, 10, 12, 16, 20]),
            'mode' => fake()->randomElement(['single_round', 'double_round']),
            'variant' => fake()->randomElement(['501_single_single', '501_single_double']),
            'match_format' => fake()->randomElement(['best_of_3', 'best_of_5']),
            'registration_deadline' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'days_per_matchday' => fake()->numberBetween(3, 14),
            'status' => 'registration',
            'created_by_user_id' => User::factory(),
        ];
    }
}
