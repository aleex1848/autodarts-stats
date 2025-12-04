<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpenAISetting extends Model
{
    use HasFactory;

    protected $table = 'openai_settings';

    protected $fillable = [
        'model',
        'max_tokens',
        'max_completion_tokens',
        'match_prompt',
        'matchday_prompt',
        'season_prompt',
    ];

    protected function casts(): array
    {
        return [
            'max_tokens' => 'integer',
            'max_completion_tokens' => 'integer',
        ];
    }

    /**
     * Get the current OpenAI settings (singleton pattern).
     */
    public static function getCurrent(): self
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

        return static::firstOrCreate([], [
            'model' => config('openai.default_model', 'o1-preview'),
            'max_tokens' => config('openai.max_tokens', 2000),
            'max_completion_tokens' => config('openai.max_completion_tokens', 4000),
            'match_prompt' => $defaultMatchPrompt,
            'matchday_prompt' => $defaultMatchdayPrompt,
            'season_prompt' => $defaultSeasonPrompt,
        ]);
    }
}

