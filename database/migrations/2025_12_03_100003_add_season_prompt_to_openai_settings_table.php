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
        Schema::table('openai_settings', function (Blueprint $table) {
            $table->text('season_prompt')->nullable()->after('matchday_prompt');
        });

        // Set default prompt for existing records
        $defaultSeasonPrompt = "Schreibe einen spannenden, sportjournalistischen Saisonbericht für eine Dart-Saison.\n\n";
        $defaultSeasonPrompt .= "Saison-Informationen:\n";
        $defaultSeasonPrompt .= "- Liga: {league_name}\n";
        $defaultSeasonPrompt .= "- Saison: {season_name}\n";
        $defaultSeasonPrompt .= "- Gesamtspieltage: {total_matchdays}\n";
        $defaultSeasonPrompt .= "- Abgeschlossene Spieltage: {completed_matchdays}\n\n";
        $defaultSeasonPrompt .= "Saisonergebnisse:\n";
        $defaultSeasonPrompt .= "{season_results}\n\n";
        $defaultSeasonPrompt .= "{final_standings}\n\n";
        $defaultSeasonPrompt .= "{highlights}\n\n";
        $defaultSeasonPrompt .= "{champion}\n\n";
        $defaultSeasonPrompt .= "Schreibe einen spannenden, sportjournalistischen Artikel über die gesamte Saison. ";
        $defaultSeasonPrompt .= "Erwähne den Saisonverlauf, wichtige Wendepunkte, Überraschungen und die Entwicklung der Tabelle. ";
        $defaultSeasonPrompt .= "Der Artikel soll zwischen 500 und 800 Wörtern lang sein und auf Deutsch verfasst werden.";

        DB::table('openai_settings')->update([
            'season_prompt' => $defaultSeasonPrompt,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('openai_settings', function (Blueprint $table) {
            $table->dropColumn('season_prompt');
        });
    }
};
