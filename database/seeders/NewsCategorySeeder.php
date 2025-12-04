<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class NewsCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Spielberichte',
                'slug' => 'spielberichte',
                'description' => 'Berichte über einzelne Spiele und Matches',
                'color' => 'blue',
            ],
            [
                'name' => 'Spieltagsbericht',
                'slug' => 'spieltagsbericht',
                'description' => 'Berichte über komplette Spieltage',
                'color' => 'green',
            ],
            [
                'name' => 'Organisation',
                'slug' => 'organisation',
                'description' => 'Organisatorische Ankündigungen und Informationen',
                'color' => 'purple',
            ],
            [
                'name' => 'Allgemein',
                'slug' => 'allgemein',
                'description' => 'Allgemeine News und Ankündigungen',
                'color' => 'yellow',
            ],
        ];

        foreach ($categories as $category) {
            NewsCategory::firstOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}

