<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Für jede bestehende Liga eine Saison erstellen
        $leagues = DB::table('leagues')->get();

        foreach ($leagues as $league) {
            $slug = \Illuminate\Support\Str::slug($league->name . ' ' . now()->format('Y'));

            // Stelle sicher, dass der Slug eindeutig ist
            $existingSlug = DB::table('seasons')->where('slug', $slug)->exists();
            if ($existingSlug) {
                $slug = $slug . '-' . $league->id;
            }

            DB::table('seasons')->insert([
                'league_id' => $league->id,
                'name' => $league->name,
                'slug' => $slug,
                'description' => $league->description,
                'max_players' => $league->max_players,
                'mode' => $league->mode,
                'variant' => $league->variant,
                'match_format' => $league->match_format,
                'registration_deadline' => $league->registration_deadline,
                'days_per_matchday' => $league->days_per_matchday,
                'status' => $league->status,
                'created_by_user_id' => $league->created_by_user_id,
                'created_at' => $league->created_at,
                'updated_at' => $league->updated_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Lösche alle Saisons, die von dieser Migration erstellt wurden
        DB::table('seasons')->truncate();
    }
};
