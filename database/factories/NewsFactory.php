<?php

namespace Database\Factories;

use App\Models\League;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\News>
 */
class NewsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'type' => fake()->randomElement(['platform', 'league']),
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => '<p>' . fake()->paragraphs(3, true) . '</p>',
            'excerpt' => fake()->optional()->sentence(),
            'category_id' => NewsCategory::factory(),
            'league_id' => null,
            'season_id' => null,
            'matchday_id' => null,
            'matchday_fixture_id' => null,
            'created_by_user_id' => User::factory(),
            'published_at' => fake()->optional()->dateTime(),
            'is_published' => fake()->boolean(70),
        ];
    }

    /**
     * Indicate that the news is platform news.
     */
    public function platform(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'platform',
            'league_id' => null,
            'season_id' => null,
            'matchday_id' => null,
            'matchday_fixture_id' => null,
        ]);
    }

    /**
     * Indicate that the news is league news.
     */
    public function league(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'league',
            'league_id' => League::factory(),
        ]);
    }

    /**
     * Indicate that the news is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'published_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ]);
    }

    /**
     * Indicate that the news is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
            'published_at' => null,
        ]);
    }
}

