<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'autodarts_user_id' => fake()->uuid(),
            'name' => fake()->name(),
            'email' => fake()->optional()->safeEmail(),
            'country' => fake()->optional()->countryCode(),
            'avatar_url' => fake()->optional()->imageUrl(),
        ];
    }
}
