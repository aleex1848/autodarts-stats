<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DartThrow>
 */
class DartThrowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->numberBetween(1, 20);
        $multiplier = fake()->randomElement([1, 2, 3]);

        return [
            'turn_id' => \App\Models\Turn::factory(),
            'autodarts_throw_id' => fake()->uuid(),
            'dart_number' => 0,
            'segment_number' => $number,
            'multiplier' => $multiplier,
            'points' => $number * $multiplier,
            'segment_name' => "S{$number}",
            'segment_bed' => 'SingleInner',
            'coords_x' => fake()->randomFloat(8, -1, 1),
            'coords_y' => fake()->randomFloat(8, -1, 1),
            'is_corrected' => false,
        ];
    }
}
