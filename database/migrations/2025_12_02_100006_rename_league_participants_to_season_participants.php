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
        // Erstelle neue season_participants Tabelle
        Schema::create('season_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_won')->default(0);
            $table->unsignedInteger('matches_lost')->default(0);
            $table->unsignedInteger('matches_draw')->default(0);
            $table->unsignedInteger('legs_won')->default(0);
            $table->unsignedInteger('legs_lost')->default(0);
            $table->integer('penalty_points')->default(0);
            $table->unsignedInteger('final_position')->nullable();
            $table->timestamps();

            $table->unique(['season_id', 'player_id']);
            $table->index(['season_id', 'points']);
        });

        // Migriere Daten: Jeder LeagueParticipant zu SeasonParticipant
        $participants = DB::table('league_participants')->get();

        foreach ($participants as $participant) {
            // Finde die Saison für diese Liga
            $season = DB::table('seasons')
                ->where('league_id', $participant->league_id)
                ->first();

            if ($season) {
                DB::table('season_participants')->insert([
                    'season_id' => $season->id,
                    'player_id' => $participant->player_id,
                    'points' => $participant->points,
                    'matches_played' => $participant->matches_played,
                    'matches_won' => $participant->matches_won,
                    'matches_lost' => $participant->matches_lost,
                    'matches_draw' => $participant->matches_draw,
                    'legs_won' => $participant->legs_won,
                    'legs_lost' => $participant->legs_lost,
                    'penalty_points' => $participant->penalty_points,
                    'final_position' => $participant->final_position,
                    'created_at' => $participant->created_at,
                    'updated_at' => $participant->updated_at,
                ]);
            }
        }

        // Lösche alte Tabelle
        Schema::dropIfExists('league_participants');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Erstelle league_participants Tabelle wieder
        Schema::create('league_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->unsignedInteger('points')->default(0);
            $table->unsignedInteger('matches_played')->default(0);
            $table->unsignedInteger('matches_won')->default(0);
            $table->unsignedInteger('matches_lost')->default(0);
            $table->unsignedInteger('matches_draw')->default(0);
            $table->unsignedInteger('legs_won')->default(0);
            $table->unsignedInteger('legs_lost')->default(0);
            $table->integer('penalty_points')->default(0);
            $table->unsignedInteger('final_position')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'player_id']);
            $table->index(['league_id', 'points']);
        });

        // Migriere Daten zurück
        $participants = DB::table('season_participants')->get();

        foreach ($participants as $participant) {
            $season = DB::table('seasons')->where('id', $participant->season_id)->first();

            if ($season) {
                DB::table('league_participants')->insert([
                    'league_id' => $season->league_id,
                    'player_id' => $participant->player_id,
                    'points' => $participant->points,
                    'matches_played' => $participant->matches_played,
                    'matches_won' => $participant->matches_won,
                    'matches_lost' => $participant->matches_lost,
                    'matches_draw' => $participant->matches_draw,
                    'legs_won' => $participant->legs_won,
                    'legs_lost' => $participant->legs_lost,
                    'penalty_points' => $participant->penalty_points,
                    'final_position' => $participant->final_position,
                    'created_at' => $participant->created_at,
                    'updated_at' => $participant->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('season_participants');
    }
};
