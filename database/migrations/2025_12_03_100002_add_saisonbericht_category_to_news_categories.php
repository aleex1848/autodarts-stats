<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if category already exists
        $exists = DB::table('news_categories')
            ->where('slug', 'saisonbericht')
            ->exists();

        if (! $exists) {
            DB::table('news_categories')->insert([
                'name' => 'Saisonbericht',
                'slug' => 'saisonbericht',
                'description' => 'Berichte über eine gesamte Saison',
                'color' => 'purple',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('news_categories')
            ->where('slug', 'saisonbericht')
            ->delete();
    }
};
