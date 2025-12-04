<?php

namespace App\Services;

use App\Models\DartMatch;
use App\Models\Matchday;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    public function __construct(
        protected MatchDataExtractor $matchDataExtractor,
        protected MatchdayDataExtractor $matchdayDataExtractor
    ) {
    }

    public function generateMatchReport(DartMatch $match): string
    {
        $data = $this->matchDataExtractor->extract($match);
        $model = $this->getModel();

        $prompt = $this->buildMatchReportPrompt($data);

        return $this->callOpenAI($prompt, $model);
    }

    public function generateMatchdayReport(Matchday $matchday): string
    {
        $data = $this->matchdayDataExtractor->extract($matchday);
        $model = $this->getModel();

        $prompt = $this->buildMatchdayReportPrompt($data);

        return $this->callOpenAI($prompt, $model);
    }

    public function generateSeasonReport(\App\Models\Season $season): string
    {
        $data = $this->extractSeasonData($season);
        $model = $this->getModel();

        $prompt = $this->buildSeasonReportPrompt($data);

        return $this->callOpenAI($prompt, $model);
    }

    public function getModel(): string
    {
        // Get model from database settings or use default
        $settings = \App\Models\OpenAISetting::getCurrent();
        
        return $settings->model ?? config('openai.default_model', 'o1-preview');
    }

    public function getMaxTokens(): int
    {
        $settings = \App\Models\OpenAISetting::getCurrent();
        
        return $settings->max_tokens ?? config('openai.max_tokens', 2000);
    }

    public function getMaxCompletionTokens(): int
    {
        $settings = \App\Models\OpenAISetting::getCurrent();
        
        return $settings->max_completion_tokens ?? config('openai.max_completion_tokens', 4000);
    }

    /**
     * Get available models from OpenAI API.
     */
    public function getAvailableModels(): array
    {
        return Cache::remember('openai_available_models', now()->addHours(24), function () {
            $apiKey = config('openai.api_key');

            if (! $apiKey) {
                // Fallback to config if no API key
                return config('openai.available_models', []);
            }

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                    ])
                    ->get('https://api.openai.com/v1/models');

                if ($response->failed()) {
                    Log::warning('OpenAI API Error fetching models', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    // Fallback to config
                    return config('openai.available_models', []);
                }

                $data = $response->json();
                $models = [];

                if (isset($data['data']) && is_array($data['data'])) {
                    foreach ($data['data'] as $model) {
                        $modelId = $model['id'] ?? null;
                        
                        if ($modelId) {
                            // Filter for chat/completion models and exclude deprecated ones
                            if (
                                str_contains($modelId, 'gpt') ||
                                str_contains($modelId, 'o1') ||
                                str_contains($modelId, 'o3')
                            ) {
                                // Skip deprecated models
                                if (isset($model['deprecated']) && $model['deprecated']) {
                                    continue;
                                }

                                // Use a readable name
                                $readableName = $this->getReadableModelName($modelId);
                                $models[$modelId] = $readableName;
                            }
                        }
                    }
                }

                // Sort models: GPT-5 first, then GPT-4, then O1, then others
                uksort($models, function ($a, $b) {
                    $order = ['gpt-5' => 1, 'gpt5' => 2, 'gpt-4' => 3, 'gpt4' => 4, 'gpt-3.5' => 5, 'gpt3.5' => 6, 'o1' => 7, 'o3' => 8];
                    
                    foreach ($order as $prefix => $priority) {
                        if (str_starts_with($a, $prefix) && ! str_starts_with($b, $prefix)) {
                            return -1;
                        }
                        if (! str_starts_with($a, $prefix) && str_starts_with($b, $prefix)) {
                            return 1;
                        }
                    }
                    
                    return strcmp($a, $b);
                });

                // If no models found, use config fallback
                if (empty($models)) {
                    return config('openai.available_models', []);
                }

                return $models;
            } catch (\Exception $e) {
                Log::error('Error fetching OpenAI models', [
                    'message' => $e->getMessage(),
                ]);

                // Fallback to config
                return config('openai.available_models', []);
            }
        });
    }

    /**
     * Get a readable name for a model ID.
     */
    protected function getReadableModelName(string $modelId): string
    {
        // Map common model IDs to readable names
        $nameMap = [
            'gpt-5' => 'GPT-5',
            'gpt5-mini' => 'GPT-5 Mini',
            'gpt5-nano' => 'GPT-5 Nano',
            'gpt-4' => 'GPT-4',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'o1-preview' => 'O1 Preview',
            'o1-mini' => 'O1 Mini',
            'o1-nano' => 'O1 Nano',
            'o3' => 'O3',
            'o3-mini' => 'O3 Mini',
        ];

        // Check exact match first
        if (isset($nameMap[$modelId])) {
            return $nameMap[$modelId];
        }

        // Generate readable name from model ID
        $name = str_replace(['-', '_'], ' ', $modelId);
        $name = ucwords($name);
        
        // Clean up common patterns
        $name = str_replace('Gpt', 'GPT', $name);
        $name = str_replace('O1', 'O1', $name);
        $name = str_replace('O3', 'O3', $name);

        return $name;
    }

    protected function buildMatchReportPrompt(array $data): string
    {
        $matchInfo = $data['match_info'];
        $players = $data['players'];
        $sets = $data['sets'];
        $highlights = $data['highlights'];

        // Get prompt template from database or use default
        $settings = \App\Models\OpenAISetting::getCurrent();
        $promptTemplate = $settings->match_prompt;

        // If no template in DB, use default
        if (empty($promptTemplate)) {
            $promptTemplate = "Schreibe einen spannenden, sportjournalistischen Spielbericht für ein Dart-Match.\n\n";
            $promptTemplate .= "Match-Informationen:\n";
            $promptTemplate .= "- Liga: {league_name}\n";
            $promptTemplate .= "- Saison: {season_name}\n";
            $promptTemplate .= "- Spieltag: {matchday_number}\n";
            $promptTemplate .= "- Format: {match_format}\n";
            $promptTemplate .= "- Spiel: {home_player} vs {away_player}\n";
            $promptTemplate .= "- Gewinner: {winner}\n\n";
            $promptTemplate .= "Spieler-Statistiken:\n";
            $promptTemplate .= "{player_stats}\n\n";
            $promptTemplate .= "Spielverlauf:\n";
            $promptTemplate .= "{match_progression}\n\n";
            $promptTemplate .= "{highlights}\n\n";
            $promptTemplate .= "Schreibe einen spannenden, sportjournalistischen Artikel, der den Spielverlauf beschreibt. ";
            $promptTemplate .= "Erwähne wichtige Wendepunkte, wie z.B. 'Player A konnte das Spiel im dritten Leg drehen und an sich reißen. ";
            $promptTemplate .= "Mit einem High Finish von 132 ließ er Player B hinter sich.' ";
            $promptTemplate .= "Der Artikel soll zwischen 300 und 500 Wörtern lang sein und auf Deutsch verfasst werden.";
        }

        // Build player stats
        $playerStats = '';
        foreach ($players as $player) {
            $playerStats .= "- {$player['name']}: {$player['legs_won']} Legs gewonnen, ";
            $playerStats .= "Durchschnitt: {$player['match_average']}, ";
            $playerStats .= "Checkout-Rate: {$player['checkout_rate']}%, ";
            $playerStats .= "180er: {$player['total_180s']}, ";
            if ($player['best_checkout_points'] > 0) {
                $playerStats .= "Höchstes Finish: {$player['best_checkout_points']}";
            }
            $playerStats .= "\n";
        }

        // Build match progression
        $matchProgression = '';
        foreach ($sets as $set) {
            $matchProgression .= "Set {$set['set_number']}:\n";
            foreach ($set['legs'] as $leg) {
                $matchProgression .= "  Leg {$leg['leg_number']}: Gewinner: {$leg['winner']}\n";
            }
        }

        // Build highlights
        $highlightsText = '';
        if (!empty($highlights)) {
            $highlightsText = "\nHighlights:\n";
            foreach ($highlights as $highlight) {
                $highlightsText .= "- {$highlight['description']}\n";
            }
        }

        // Replace placeholders
        $prompt = str_replace('{league_name}', $data['season_info']['league_name'], $promptTemplate);
        $prompt = str_replace('{season_name}', $data['season_info']['season_name'], $prompt);
        $prompt = str_replace('{matchday_number}', $data['season_info']['matchday_number'], $prompt);
        $prompt = str_replace('{match_format}', $matchInfo['match_format'], $prompt);
        $prompt = str_replace('{home_player}', $data['home_player'], $prompt);
        $prompt = str_replace('{away_player}', $data['away_player'], $prompt);
        $prompt = str_replace('{winner}', $matchInfo['winner'], $prompt);
        $prompt = str_replace('{player_stats}', $playerStats, $prompt);
        $prompt = str_replace('{match_progression}', $matchProgression, $prompt);
        $prompt = str_replace('{highlights}', $highlightsText, $prompt);

        return $prompt;
    }

    protected function buildMatchdayReportPrompt(array $data): string
    {
        $matchdayInfo = $data['matchday_info'];
        $matchResults = $data['match_results'];
        $highlights = $data['highlights'];
        $tableChanges = $data['table_changes'];

        // Get prompt template from database or use default
        $settings = \App\Models\OpenAISetting::getCurrent();
        $promptTemplate = $settings->matchday_prompt;

        // If no template in DB, use default
        if (empty($promptTemplate)) {
            $promptTemplate = "Schreibe einen spannenden, sportjournalistischen Spieltagsbericht für einen Dart-Spieltag.\n\n";
            $promptTemplate .= "Spieltag-Informationen:\n";
            $promptTemplate .= "- Liga: {league_name}\n";
            $promptTemplate .= "- Saison: {season_name}\n";
            $promptTemplate .= "- Spieltag: {matchday_number}\n";
            $promptTemplate .= "- Gesamtspiele: {total_fixtures}\n";
            $promptTemplate .= "- Abgeschlossene Spiele: {completed_fixtures}\n\n";
            $promptTemplate .= "Spielergebnisse:\n";
            $promptTemplate .= "{match_results}\n\n";
            $promptTemplate .= "{highlights}\n\n";
            $promptTemplate .= "{table_changes}\n\n";
            $promptTemplate .= "Schreibe einen spannenden, sportjournalistischen Artikel über den Spieltag. ";
            $promptTemplate .= "Erwähne Überraschungssieger, wichtige Tabellenänderungen und besondere Vorkommnisse. ";
            $promptTemplate .= "Der Artikel soll zwischen 400 und 600 Wörtern lang sein und auf Deutsch verfasst werden.";
        }

        // Build match results
        $matchResultsText = '';
        foreach ($matchResults as $result) {
            $matchResultsText .= "- {$result['home_player']} vs {$result['away_player']}: ";
            $matchResultsText .= "{$result['home_legs_won']}:{$result['away_legs_won']} ";
            $matchResultsText .= "(Gewinner: {$result['winner']})\n";
        }

        // Build highlights
        $highlightsText = '';
        if (!empty($highlights)) {
            $highlightsText = "\nHighlights des Spieltags:\n";
            foreach ($highlights as $highlight) {
                $highlightsText .= "- {$highlight['description']} ({$highlight['match']})\n";
            }
        }

        // Build table changes
        $tableChangesText = '';
        if (!empty($tableChanges)) {
            $tableChangesText = "\nTabellenänderungen:\n";
            foreach ($tableChanges as $change) {
                if ($change['position_change'] > 0) {
                    $tableChangesText .= "- {$change['player_name']}: Aufstieg von Platz {$change['previous_position']} auf Platz {$change['current_position']} ";
                    $tableChangesText .= "({$change['points']} Punkte, {$change['legs_won']}:{$change['legs_lost']} Legs)\n";
                } elseif ($change['position_change'] < 0) {
                    $tableChangesText .= "- {$change['player_name']}: Abstieg von Platz {$change['previous_position']} auf Platz {$change['current_position']} ";
                    $tableChangesText .= "({$change['points']} Punkte, {$change['legs_won']}:{$change['legs_lost']} Legs)\n";
                }
            }
        }

        // Replace placeholders
        $prompt = str_replace('{league_name}', $data['season_info']['league_name'], $promptTemplate);
        $prompt = str_replace('{season_name}', $data['season_info']['season_name'], $prompt);
        $prompt = str_replace('{matchday_number}', $matchdayInfo['matchday_number'], $prompt);
        $prompt = str_replace('{total_fixtures}', $matchdayInfo['total_fixtures'], $prompt);
        $prompt = str_replace('{completed_fixtures}', $matchdayInfo['completed_fixtures'], $prompt);
        $prompt = str_replace('{match_results}', $matchResultsText, $prompt);
        $prompt = str_replace('{highlights}', $highlightsText, $prompt);
        $prompt = str_replace('{table_changes}', $tableChangesText, $prompt);

        return $prompt;
    }

    protected function extractSeasonData(\App\Models\Season $season): array
    {
        $season->load([
            'league',
            'matchdays.fixtures.homePlayer',
            'matchdays.fixtures.awayPlayer',
            'matchdays.fixtures.dartMatch.matchPlayers.player',
        ]);

        $matchdays = $season->matchdays()->orderBy('matchday_number')->get();
        $completedMatchdays = $matchdays->filter(function ($matchday) {
            return $matchday->fixtures()->where('status', \App\Enums\FixtureStatus::Completed->value)->exists();
        });

        // Get all season results
        $seasonResults = [];
        $highlights = [];

        foreach ($matchdays as $matchday) {
            $fixtures = $matchday->fixtures()
                ->where('status', \App\Enums\FixtureStatus::Completed->value)
                ->with(['homePlayer', 'awayPlayer', 'dartMatch.matchPlayers.player'])
                ->get();

            foreach ($fixtures as $fixture) {
                $homePlayer = $fixture->homePlayer;
                $awayPlayer = $fixture->awayPlayer;
                $winner = $fixture->winner;

                $seasonResults[] = [
                    'matchday_number' => $matchday->matchday_number,
                    'home_player' => $homePlayer?->name ?? 'Unbekannt',
                    'away_player' => $awayPlayer?->name ?? 'Unbekannt',
                    'home_legs_won' => $fixture->home_legs_won ?? 0,
                    'away_legs_won' => $fixture->away_legs_won ?? 0,
                    'winner' => $winner?->name ?? 'Unbekannt',
                ];

                // Collect highlights
                if ($fixture->dartMatch) {
                    $matchPlayers = $fixture->dartMatch->matchPlayers()->with('player')->get();
                    foreach ($matchPlayers as $matchPlayer) {
                        if (($matchPlayer->best_checkout_points ?? 0) >= 100) {
                            $highlights[] = [
                                'type' => 'high_checkout',
                                'matchday' => $matchday->matchday_number,
                                'match' => "{$homePlayer?->name} vs {$awayPlayer?->name}",
                                'player' => $matchPlayer->player->name,
                                'value' => $matchPlayer->best_checkout_points,
                                'description' => "High Finish von {$matchPlayer->best_checkout_points} Punkten",
                            ];
                        }
                        if (($matchPlayer->total_180s ?? 0) >= 3) {
                            $highlights[] = [
                                'type' => 'many_180s',
                                'matchday' => $matchday->matchday_number,
                                'match' => "{$homePlayer?->name} vs {$awayPlayer?->name}",
                                'player' => $matchPlayer->player->name,
                                'value' => $matchPlayer->total_180s,
                                'description' => "{$matchPlayer->total_180s} x 180 geworfen",
                            ];
                        }
                    }
                }
            }
        }

        // Get final standings
        $standingsCalculator = app(\App\Services\LeagueStandingsCalculator::class);
        $finalStandings = $standingsCalculator->calculateStandings($season);

        // Find champion
        $champion = null;
        if ($finalStandings->isNotEmpty()) {
            $champion = $finalStandings->first()->player?->name ?? 'Unbekannt';
        }

        return [
            'season_info' => [
                'league_name' => $season->league->name,
                'season_name' => $season->name,
                'total_matchdays' => $matchdays->count(),
                'completed_matchdays' => $completedMatchdays->count(),
            ],
            'season_results' => $seasonResults,
            'final_standings' => $finalStandings,
            'highlights' => $highlights,
            'champion' => $champion,
        ];
    }

    protected function buildSeasonReportPrompt(array $data): string
    {
        $seasonInfo = $data['season_info'];
        $seasonResults = $data['season_results'];
        $finalStandings = $data['final_standings'];
        $highlights = $data['highlights'];
        $champion = $data['champion'];

        // Get prompt template from database or use default
        $settings = \App\Models\OpenAISetting::getCurrent();
        $promptTemplate = $settings->season_prompt;

        // If no template in DB, use default
        if (empty($promptTemplate)) {
            $promptTemplate = "Schreibe einen spannenden, sportjournalistischen Saisonbericht für eine Dart-Saison.\n\n";
            $promptTemplate .= "Saison-Informationen:\n";
            $promptTemplate .= "- Liga: {league_name}\n";
            $promptTemplate .= "- Saison: {season_name}\n";
            $promptTemplate .= "- Gesamtspieltage: {total_matchdays}\n";
            $promptTemplate .= "- Abgeschlossene Spieltage: {completed_matchdays}\n\n";
            $promptTemplate .= "Saisonergebnisse:\n";
            $promptTemplate .= "{season_results}\n\n";
            $promptTemplate .= "{final_standings}\n\n";
            $promptTemplate .= "{highlights}\n\n";
            $promptTemplate .= "{champion}\n\n";
            $promptTemplate .= "Schreibe einen spannenden, sportjournalistischen Artikel über die gesamte Saison. ";
            $promptTemplate .= "Erwähne den Saisonverlauf, wichtige Wendepunkte, Überraschungen und die Entwicklung der Tabelle. ";
            $promptTemplate .= "Der Artikel soll zwischen 500 und 800 Wörtern lang sein und auf Deutsch verfasst werden.";
        }

        // Build season results text
        $seasonResultsText = '';
        $currentMatchday = null;
        foreach ($seasonResults as $result) {
            if ($currentMatchday !== $result['matchday_number']) {
                if ($currentMatchday !== null) {
                    $seasonResultsText .= "\n";
                }
                $seasonResultsText .= "Spieltag {$result['matchday_number']}:\n";
                $currentMatchday = $result['matchday_number'];
            }
            $seasonResultsText .= "- {$result['home_player']} vs {$result['away_player']}: ";
            $seasonResultsText .= "{$result['home_legs_won']}:{$result['away_legs_won']} ";
            $seasonResultsText .= "(Gewinner: {$result['winner']})\n";
        }

        // Build final standings text
        $finalStandingsText = "\nEndtabelle:\n";
        foreach ($finalStandings as $index => $participant) {
            $position = $index + 1;
            $playerName = $participant->player?->name ?? 'Unbekannt';
            $finalStandingsText .= "{$position}. {$playerName}: ";
            $finalStandingsText .= "{$participant->points} Punkte, ";
            $finalStandingsText .= "{$participant->legs_won}:{$participant->legs_lost} Legs\n";
        }

        // Build highlights text
        $highlightsText = '';
        if (!empty($highlights)) {
            $highlightsText = "\nHighlights der Saison:\n";
            foreach ($highlights as $highlight) {
                $highlightsText .= "- {$highlight['description']} ";
                $highlightsText .= "({$highlight['player']} in Spieltag {$highlight['matchday']}: {$highlight['match']})\n";
            }
        }

        // Build champion text
        $championText = '';
        if ($champion) {
            $championText = "\nSaisonmeister: {$champion}\n";
        }

        // Replace placeholders
        $prompt = str_replace('{league_name}', $seasonInfo['league_name'], $promptTemplate);
        $prompt = str_replace('{season_name}', $seasonInfo['season_name'], $prompt);
        $prompt = str_replace('{total_matchdays}', (string) $seasonInfo['total_matchdays'], $prompt);
        $prompt = str_replace('{completed_matchdays}', (string) $seasonInfo['completed_matchdays'], $prompt);
        $prompt = str_replace('{season_results}', $seasonResultsText, $prompt);
        $prompt = str_replace('{final_standings}', $finalStandingsText, $prompt);
        $prompt = str_replace('{highlights}', $highlightsText, $prompt);
        $prompt = str_replace('{champion}', $championText, $prompt);

        return $prompt;
    }

    protected function callOpenAI(string $prompt, string $model): string
    {
        $apiKey = config('openai.api_key');

        if (! $apiKey) {
            throw new \Exception('OpenAI API Key ist nicht konfiguriert.');
        }

        try {
            // Log the request for debugging
            Log::info('OpenAI API Request', [
                'model' => $model,
                'prompt_length' => strlen($prompt),
                'prompt_preview' => substr($prompt, 0, 200),
                'uses_max_completion_tokens' => $this->modelUsesMaxCompletionTokens($model),
            ]);
            
            // For o1 models and newer models (gpt-5, etc.), use max_completion_tokens
            if ($this->modelUsesMaxCompletionTokens($model)) {
                // GPT-5 and O1 models use reasoning tokens, so we need more completion tokens
                $maxCompletionTokens = $this->getMaxCompletionTokens();
                
                $requestData = [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_completion_tokens' => $maxCompletionTokens,
                ];
                
                Log::info('OpenAI API: Request data for max_completion_tokens model', [
                    'model' => $model,
                    'request_data' => $requestData,
                ]);
                
                $response = Http::timeout(config('openai.timeout', 120))
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.openai.com/v1/chat/completions', $requestData);
            } else {
                // For other models (GPT-4, GPT-3.5, etc.)
                $maxTokens = $this->getMaxTokens();
                
                $requestData = [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Du bist ein erfahrener Sportjournalist, der spannende und unterhaltsame Artikel über Dart-Matches schreibt.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.7,
                ];
                
                Log::info('OpenAI API: Request data for max_tokens model', [
                    'model' => $model,
                    'request_data' => $requestData,
                ]);
                
                $response = Http::timeout(config('openai.timeout', 120))
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.openai.com/v1/chat/completions', $requestData);
            }

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMessage = 'OpenAI API Fehler';
                
                // Extract error message from response
                if (isset($errorBody['error']['message'])) {
                    $errorMessage = $errorBody['error']['message'];
                } elseif (isset($errorBody['error']['type'])) {
                    $errorMessage = $errorBody['error']['type'] . ': ' . ($errorBody['error']['message'] ?? 'Unbekannter Fehler');
                } elseif (is_string($errorBody)) {
                    $errorMessage = $errorBody;
                } else {
                    $errorMessage = 'OpenAI API Fehler: ' . json_encode($errorBody);
                }

                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'error_message' => $errorMessage,
                ]);

                throw new \Exception($errorMessage);
            }

            $data = $response->json();

            // Always log the full response for debugging when there's an issue
            Log::info('OpenAI API Response', [
                'model' => $model,
                'status' => $response->status(),
                'response_keys' => array_keys($data),
                'has_choices' => isset($data['choices']),
                'choices_count' => isset($data['choices']) ? count($data['choices']) : 0,
                'full_response' => $data, // Log complete response for debugging
            ]);

            if (! isset($data['choices']) || empty($data['choices'])) {
                Log::error('OpenAI API: No choices in response', [
                    'model' => $model,
                    'response' => $data,
                    'raw_body' => $response->body(),
                ]);
                throw new \Exception('OpenAI API hat keine Antwort zurückgegeben. Bitte versuchen Sie es erneut oder wählen Sie ein anderes Modell.');
            }

            $firstChoice = $data['choices'][0];
            
            // Log the first choice structure
            Log::info('OpenAI API: First choice structure', [
                'model' => $model,
                'choice_keys' => array_keys($firstChoice),
                'has_message' => isset($firstChoice['message']),
                'message_keys' => isset($firstChoice['message']) ? array_keys($firstChoice['message']) : [],
                'finish_reason' => $firstChoice['finish_reason'] ?? null,
                'full_choice' => $firstChoice,
            ]);
            
            $content = $firstChoice['message']['content'] ?? null;
            $finishReason = $firstChoice['finish_reason'] ?? null;

            // Check finish reason
            if ($finishReason === 'length') {
                // For GPT-5/O1 models, length might mean reasoning tokens were exhausted
                $usage = $data['usage'] ?? [];
                $reasoningTokens = $usage['completion_tokens_details']['reasoning_tokens'] ?? 0;
                $completionTokens = $usage['completion_tokens'] ?? 0;
                $maxTokens = $this->modelUsesMaxCompletionTokens($model) 
                    ? $this->getMaxCompletionTokens() 
                    : $this->getMaxTokens();
                
                Log::warning('OpenAI API: Response was truncated due to length', [
                    'model' => $model,
                    'content_length' => strlen($content ?? ''),
                    'reasoning_tokens' => $reasoningTokens,
                    'completion_tokens' => $completionTokens,
                    'total_completion_tokens' => $usage['completion_tokens'] ?? 0,
                    'max_tokens' => $maxTokens,
                ]);
                
                // If content is empty but we have reasoning tokens, the model might need more tokens
                if (empty($content) && $reasoningTokens > 0) {
                    throw new \Exception("Das Modell hat alle Tokens für interne Überlegungen verwendet ({$reasoningTokens} reasoning tokens von {$maxTokens} max tokens), bevor der Inhalt generiert wurde. Bitte erhöhen Sie max_completion_tokens in den OpenAI-Einstellungen oder wählen Sie ein anderes Modell.");
                }
                
                // If content exists but was truncated, warn the user but don't throw - return partial content
                if (!empty($content)) {
                    Log::warning('OpenAI API: Content was truncated but returning partial content', [
                        'model' => $model,
                        'content_length' => strlen($content),
                        'completion_tokens' => $completionTokens,
                        'max_tokens' => $maxTokens,
                    ]);
                    // Don't throw here - return the partial content with a warning
                    // The user will see the truncated content
                }
            } elseif ($finishReason === 'content_filter') {
                Log::error('OpenAI API: Response was filtered', [
                    'model' => $model,
                ]);
                throw new \Exception('OpenAI API hat die Antwort gefiltert. Bitte versuchen Sie es erneut oder wählen Sie ein anderes Modell.');
            } elseif ($finishReason === 'stop' && empty($content)) {
                Log::error('OpenAI API: Response finished but content is empty', [
                    'model' => $model,
                    'finish_reason' => $finishReason,
                    'choice' => $firstChoice,
                ]);
                throw new \Exception('OpenAI API hat eine leere Antwort zurückgegeben. Bitte versuchen Sie es erneut oder wählen Sie ein anderes Modell.');
            }

            if ($content === null) {
                Log::error('OpenAI API: Content key missing in response', [
                    'model' => $model,
                    'response' => $data,
                    'choice' => $firstChoice,
                ]);
                throw new \Exception('OpenAI API hat keine Content-Daten zurückgegeben. Bitte versuchen Sie es erneut oder wählen Sie ein anderes Modell.');
            }

            if (empty(trim($content))) {
                Log::error('OpenAI API: Empty or whitespace-only content', [
                    'model' => $model,
                    'finish_reason' => $finishReason,
                    'content_length' => strlen($content ?? ''),
                    'content_type' => gettype($content),
                    'content_value' => $content,
                    'content_preview' => $content ? substr($content, 0, 100) : 'NULL',
                    'full_choice' => $firstChoice,
                    'full_response' => $data,
                ]);
                throw new \Exception('OpenAI API hat einen leeren Inhalt zurückgegeben. Bitte versuchen Sie es erneut oder wählen Sie ein anderes Modell. Modell: ' . $model);
            }

            $trimmedContent = trim($content);
            
            Log::info('OpenAI API: Successfully received content', [
                'model' => $model,
                'content_length' => strlen($trimmedContent),
                'content_preview' => substr($trimmedContent, 0, 200),
            ]);

            return $trimmedContent;
        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            throw $e;
        }
    }

    /**
     * Check if a model uses max_completion_tokens instead of max_tokens.
     */
    protected function modelUsesMaxCompletionTokens(string $model): bool
    {
        // O1 models and GPT-5 models use max_completion_tokens
        return str_starts_with($model, 'o1-') 
            || str_starts_with($model, 'o3-')
            || str_starts_with($model, 'gpt-5')
            || str_starts_with($model, 'gpt5-');
    }
}

