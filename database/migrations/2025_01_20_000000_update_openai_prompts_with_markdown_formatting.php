<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
        $defaultMatchPrompt .= "Der Artikel soll zwischen 300 und 500 Wörtern lang sein und auf Deutsch verfasst werden.\n\n";
        $defaultMatchPrompt .= "WICHTIG: Verwende Markdown-Formatierung mit Überschriften (## für Hauptüberschriften, ### für Unterüberschriften). ";
        $defaultMatchPrompt .= "Strukturiere den Artikel mit einer Einleitung, einem Hauptteil mit mehreren Abschnitten und einem Fazit. ";
        $defaultMatchPrompt .= "Verwende Absätze mit doppelten Zeilenumbrüchen für bessere Lesbarkeit.";

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
        $defaultMatchdayPrompt .= "Der Artikel soll zwischen 400 und 600 Wörtern lang sein und auf Deutsch verfasst werden.\n\n";
        $defaultMatchdayPrompt .= "WICHTIG: Verwende Markdown-Formatierung mit Überschriften (## für Hauptüberschriften, ### für Unterüberschriften). ";
        $defaultMatchdayPrompt .= "Strukturiere den Artikel mit einer Einleitung, einem Hauptteil mit mehreren Abschnitten (z.B. 'Spielergebnisse', 'Highlights', 'Tabellenänderungen') und einem Fazit. ";
        $defaultMatchdayPrompt .= "Verwende Absätze mit doppelten Zeilenumbrüchen für bessere Lesbarkeit.";

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
        $defaultSeasonPrompt .= "Der Artikel soll zwischen 500 und 800 Wörtern lang sein und auf Deutsch verfasst werden.\n\n";
        $defaultSeasonPrompt .= "WICHTIG: Verwende Markdown-Formatierung mit Überschriften (## für Hauptüberschriften, ### für Unterüberschriften). ";
        $defaultSeasonPrompt .= "Strukturiere den Artikel mit einer Einleitung, einem Hauptteil mit mehreren Abschnitten (z.B. 'Saisonverlauf', 'Highlights', 'Endtabelle', 'Fazit') und einem abschließenden Fazit. ";
        $defaultSeasonPrompt .= "Verwende Absätze mit doppelten Zeilenumbrüchen für bessere Lesbarkeit.";

        // Update all existing records with the new prompts
        DB::table('openai_settings')->update([
            'match_prompt' => $defaultMatchPrompt,
            'matchday_prompt' => $defaultMatchdayPrompt,
            'season_prompt' => $defaultSeasonPrompt,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old prompts (without markdown formatting instructions)
        $oldMatchPrompt = "Schreibe einen spannenden, sportjournalistischen Spielbericht für ein Dart-Match.\n\n";
        $oldMatchPrompt .= "Match-Informationen:\n";
        $oldMatchPrompt .= "- Liga: {league_name}\n";
        $oldMatchPrompt .= "- Saison: {season_name}\n";
        $oldMatchPrompt .= "- Spieltag: {matchday_number}\n";
        $oldMatchPrompt .= "- Format: {match_format}\n";
        $oldMatchPrompt .= "- Spiel: {home_player} vs {away_player}\n";
        $oldMatchPrompt .= "- Gewinner: {winner}\n\n";
        $oldMatchPrompt .= "Spieler-Statistiken:\n";
        $oldMatchPrompt .= "{player_stats}\n\n";
        $oldMatchPrompt .= "Spielverlauf:\n";
        $oldMatchPrompt .= "{match_progression}\n\n";
        $oldMatchPrompt .= "{highlights}\n\n";
        $oldMatchPrompt .= "Schreibe einen spannenden, sportjournalistischen Artikel, der den Spielverlauf beschreibt. ";
        $oldMatchPrompt .= "Erwähne wichtige Wendepunkte, wie z.B. 'Player A konnte das Spiel im dritten Leg drehen und an sich reißen. ";
        $oldMatchPrompt .= "Mit einem High Finish von 132 ließ er Player B hinter sich.' ";
        $oldMatchPrompt .= "Der Artikel soll zwischen 300 und 500 Wörtern lang sein und auf Deutsch verfasst werden.";

        $oldMatchdayPrompt = "Schreibe einen spannenden, sportjournalistischen Spieltagsbericht für einen Dart-Spieltag.\n\n";
        $oldMatchdayPrompt .= "Spieltag-Informationen:\n";
        $oldMatchdayPrompt .= "- Liga: {league_name}\n";
        $oldMatchdayPrompt .= "- Saison: {season_name}\n";
        $oldMatchdayPrompt .= "- Spieltag: {matchday_number}\n";
        $oldMatchdayPrompt .= "- Gesamtspiele: {total_fixtures}\n";
        $oldMatchdayPrompt .= "- Abgeschlossene Spiele: {completed_fixtures}\n\n";
        $oldMatchdayPrompt .= "Spielergebnisse:\n";
        $oldMatchdayPrompt .= "{match_results}\n\n";
        $oldMatchdayPrompt .= "{highlights}\n\n";
        $oldMatchdayPrompt .= "{table_changes}\n\n";
        $oldMatchdayPrompt .= "Schreibe einen spannenden, sportjournalistischen Artikel über den Spieltag. ";
        $oldMatchdayPrompt .= "Erwähne Überraschungssieger, wichtige Tabellenänderungen und besondere Vorkommnisse. ";
        $oldMatchdayPrompt .= "Der Artikel soll zwischen 400 und 600 Wörtern lang sein und auf Deutsch verfasst werden.";

        $oldSeasonPrompt = "Schreibe einen spannenden, sportjournalistischen Saisonbericht für eine Dart-Saison.\n\n";
        $oldSeasonPrompt .= "Saison-Informationen:\n";
        $oldSeasonPrompt .= "- Liga: {league_name}\n";
        $oldSeasonPrompt .= "- Saison: {season_name}\n";
        $oldSeasonPrompt .= "- Gesamtspieltage: {total_matchdays}\n";
        $oldSeasonPrompt .= "- Abgeschlossene Spieltage: {completed_matchdays}\n\n";
        $oldSeasonPrompt .= "Saisonergebnisse:\n";
        $oldSeasonPrompt .= "{season_results}\n\n";
        $oldSeasonPrompt .= "{final_standings}\n\n";
        $oldSeasonPrompt .= "{highlights}\n\n";
        $oldSeasonPrompt .= "{champion}\n\n";
        $oldSeasonPrompt .= "Schreibe einen spannenden, sportjournalistischen Artikel über die gesamte Saison. ";
        $oldSeasonPrompt .= "Erwähne den Saisonverlauf, wichtige Wendepunkte, Überraschungen und die Entwicklung der Tabelle. ";
        $oldSeasonPrompt .= "Der Artikel soll zwischen 500 und 800 Wörtern lang sein und auf Deutsch verfasst werden.";

        DB::table('openai_settings')->update([
            'match_prompt' => $oldMatchPrompt,
            'matchday_prompt' => $oldMatchdayPrompt,
            'season_prompt' => $oldSeasonPrompt,
        ]);
    }
};
