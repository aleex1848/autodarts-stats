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
            $table->text('match_prompt')->nullable()->after('max_completion_tokens');
            $table->text('matchday_prompt')->nullable()->after('match_prompt');
        });

        // Set default prompts for existing records
        $defaultMatchPrompt = "Schreibe einen spannenden, sportjournalistischen Spielbericht für ein Dart-Match.\n\n";
        $defaultMatchPrompt .= "Match-Informationen:\n";
        $defaultMatchPrompt .= "- Liga: {league_name}\n";
        $defaultMatchPrompt .= "- Saison: {season_name}\n";
        $defaultMatchPrompt .= "- Spieltag: {matchday_number}\n";
        $defaultMatchPrompt .= "- Format: {match_format}\n";
        $defaultMatchPrompt .= "- Spiel: {home_player} vs {away_player}\n";
        $defaultMatchPrompt .= "- Gewinner: {winner}\n\n";
        $defaultMatchPrompt .= "Spieler-Statistiken:\n";
        $defaultMatchPrompt .= "{player_stats}\n\n";
        $defaultMatchPrompt .= "Spielverlauf:\n";
        $defaultMatchPrompt .= "{match_progression}\n\n";
        $defaultMatchPrompt .= "{highlights}\n\n";
        $defaultMatchPrompt .= "Schreibe einen spannenden, sportjournalistischen Artikel, der den Spielverlauf beschreibt. ";
        $defaultMatchPrompt .= "Erwähne wichtige Wendepunkte, wie z.B. 'Player A konnte das Spiel im dritten Leg drehen und an sich reißen. ";
        $defaultMatchPrompt .= "Mit einem High Finish von 132 ließ er Player B hinter sich.' ";
        $defaultMatchPrompt .= "Der Artikel soll zwischen 300 und 500 Wörtern lang sein und auf Deutsch verfasst werden.";

        $defaultMatchdayPrompt = "Schreibe einen spannenden, sportjournalistischen Spieltagsbericht für einen Dart-Spieltag.\n\n";
        $defaultMatchdayPrompt .= "Spieltag-Informationen:\n";
        $defaultMatchdayPrompt .= "- Liga: {league_name}\n";
        $defaultMatchdayPrompt .= "- Saison: {season_name}\n";
        $defaultMatchdayPrompt .= "- Spieltag: {matchday_number}\n";
        $defaultMatchdayPrompt .= "- Gesamtspiele: {total_fixtures}\n";
        $defaultMatchdayPrompt .= "- Abgeschlossene Spiele: {completed_fixtures}\n\n";
        $defaultMatchdayPrompt .= "Spielergebnisse:\n";
        $defaultMatchdayPrompt .= "{match_results}\n\n";
        $defaultMatchdayPrompt .= "{highlights}\n\n";
        $defaultMatchdayPrompt .= "{table_changes}\n\n";
        $defaultMatchdayPrompt .= "Schreibe einen spannenden, sportjournalistischen Artikel über den Spieltag. ";
        $defaultMatchdayPrompt .= "Erwähne Überraschungssieger, wichtige Tabellenänderungen und besondere Vorkommnisse. ";
        $defaultMatchdayPrompt .= "Der Artikel soll zwischen 400 und 600 Wörtern lang sein und auf Deutsch verfasst werden.";

        DB::table('openai_settings')->update([
            'match_prompt' => $defaultMatchPrompt,
            'matchday_prompt' => $defaultMatchdayPrompt,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('openai_settings', function (Blueprint $table) {
            $table->dropColumn(['match_prompt', 'matchday_prompt']);
        });
    }
};
