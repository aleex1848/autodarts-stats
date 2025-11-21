<?php

namespace Database\Factories;

use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Download>
 */
class DownloadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => DownloadCategory::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'is_active' => fake()->boolean(80),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the download should be active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the download should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the download should not have a category.
     */
    public function withoutCategory(): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => null,
        ]);
    }
}
