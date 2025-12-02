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
        // Erstelle neue season_registrations Tabelle
        Schema::create('season_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained('seasons')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, confirmed, rejected
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['season_id', 'player_id']);
            $table->index('status');
        });

        // Migriere Daten: Jede LeagueRegistration zu SeasonRegistration
        $registrations = DB::table('league_registrations')->get();

        foreach ($registrations as $registration) {
            // Finde die Saison für diese Liga
            $season = DB::table('seasons')
                ->where('league_id', $registration->league_id)
                ->first();

            if ($season) {
                DB::table('season_registrations')->insert([
                    'season_id' => $season->id,
                    'player_id' => $registration->player_id,
                    'user_id' => $registration->user_id,
                    'status' => $registration->status,
                    'registered_at' => $registration->registered_at,
                    'created_at' => $registration->created_at,
                    'updated_at' => $registration->updated_at,
                ]);
            }
        }

        // Lösche alte Tabelle
        Schema::dropIfExists('league_registrations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Erstelle league_registrations Tabelle wieder
        Schema::create('league_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->onDelete('cascade');
            $table->foreignId('player_id')->constrained('players')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, confirmed, rejected
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['league_id', 'player_id']);
            $table->index('status');
        });

        // Migriere Daten zurück
        $registrations = DB::table('season_registrations')->get();

        foreach ($registrations as $registration) {
            $season = DB::table('seasons')->where('id', $registration->season_id)->first();

            if ($season) {
                DB::table('league_registrations')->insert([
                    'league_id' => $season->league_id,
                    'player_id' => $registration->player_id,
                    'user_id' => $registration->user_id,
                    'status' => $registration->status,
                    'registered_at' => $registration->registered_at,
                    'created_at' => $registration->created_at,
                    'updated_at' => $registration->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('season_registrations');
    }
};
