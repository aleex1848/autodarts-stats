<?php

namespace Database\Factories;

use App\Models\FrontpageLogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FrontpageLogo>
 */
class FrontpageLogoFactory extends Factory
{
    protected $model = FrontpageLogo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'is_active' => fake()->boolean(30), // 30% chance of being active
        ];
    }

    /**
     * Indicate that the frontpage logo is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the frontpage logo is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
