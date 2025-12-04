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
        Schema::table('matchdays', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('id')->constrained('seasons')->onDelete('cascade');
        });

        // Migriere Daten: Jedes Matchday von league_id zu season_id
        // Da jede Liga zu einer Saison migriert wurde, können wir die erste Saison einer Liga nehmen
        $matchdays = DB::table('matchdays')->get();

        foreach ($matchdays as $matchday) {
            $season = DB::table('seasons')
                ->where('league_id', $matchday->league_id)
                ->first();

            if ($season) {
                DB::table('matchdays')
                    ->where('id', $matchday->id)
                    ->update(['season_id' => $season->id]);
            }
        }

        // Entferne nullable und alte league_id
        Schema::table('matchdays', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable(false)->change();
            $table->dropForeign(['league_id']);
            $table->dropColumn('league_id');
            $table->index(['season_id', 'matchday_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matchdays', function (Blueprint $table) {
            $table->foreignId('league_id')->nullable()->after('id')->constrained('leagues')->onDelete('cascade');
        });

        // Migriere Daten zurück
        $matchdays = DB::table('matchdays')->get();

        foreach ($matchdays as $matchday) {
            $season = DB::table('seasons')->where('id', $matchday->season_id)->first();

            if ($season) {
                DB::table('matchdays')
                    ->where('id', $matchday->id)
                    ->update(['league_id' => $season->league_id]);
            }
        }

        Schema::table('matchdays', function (Blueprint $table) {
            $table->foreignId('league_id')->nullable(false)->change();
            $table->dropForeign(['season_id']);
            $table->dropColumn('season_id');
            $table->index(['league_id', 'matchday_number']);
        });
    }
};

