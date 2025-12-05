<?php

namespace App\Services;

use App\Enums\LeagueMatchFormat;
use App\Models\MatchdayFixture;
use App\Models\Player;
use App\Support\WebhookProcessing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\WebhookClient\Models\WebhookCall;

class MatchSimulationService
{
    public function simulateMatch(MatchdayFixture $fixture): void
    {
        $season = $fixture->matchday->season;
        $homePlayer = $fixture->homePlayer;
        $awayPlayer = $fixture->awayPlayer;

        if (! $homePlayer || ! $awayPlayer) {
            throw new \InvalidArgumentException('Beide Spieler müssen vorhanden sein.');
        }

        // Generiere Match-ID
        $matchId = (string) Str::uuid();
        $startedAt = now()->subMinutes(fake()->numberBetween(10, 60));

        // Bestimme Match-Format und benötigte Legs zum Sieg
        $matchFormat = LeagueMatchFormat::from($season->match_format);
        $legsToWin = match ($matchFormat) {
            LeagueMatchFormat::BestOf3 => 2,
            LeagueMatchFormat::BestOf5 => 3,
        };

        // Simuliere Match-Ergebnis
        $homeLegsWon = fake()->numberBetween(0, $legsToWin);
        $awayLegsWon = $homeLegsWon < $legsToWin ? $legsToWin : fake()->numberBetween(0, $legsToWin - 1);

        // Stelle sicher, dass genau einer gewinnt
        if ($homeLegsWon === $awayLegsWon) {
            $homeLegsWon = $legsToWin;
            $awayLegsWon = fake()->numberBetween(0, $legsToWin - 1);
        }

        $winnerIndex = $homeLegsWon === $legsToWin ? 0 : 1;
        $winnerPlayer = $homeLegsWon === $legsToWin ? $homePlayer : $awayPlayer;

        // Erstelle Player-ID Mapping (konsistent über alle Legs)
        $homePlayerId = (string) Str::uuid();
        $awayPlayerId = (string) Str::uuid();

        // Erstelle initiales match_state Event
        $initialMatchState = $this->createInitialMatchState(
            $matchId,
            $season,
            $homePlayer,
            $awayPlayer,
            $homePlayerId,
            $awayPlayerId,
            $startedAt
        );

        $webhookCalls = [];

        // Erstelle initiales match_state WebhookCall
        $webhookCalls[] = $this->createWebhookCall('match_state', $matchId, $initialMatchState);

        // Simuliere Legs mit Würfen
        $currentLeg = 1;
        $homeLegsWonCount = 0;
        $awayLegsWonCount = 0;

        while ($homeLegsWonCount < $legsToWin && $awayLegsWonCount < $legsToWin) {
            $legWinnerIndex = ($homeLegsWonCount < $homeLegsWon) ? 0 : 1;
            $legWinner = $legWinnerIndex === 0 ? $homePlayer : $awayPlayer;
            $legLoser = $legWinnerIndex === 1 ? $homePlayer : $awayPlayer;
            $legWinnerPlayerId = $legWinnerIndex === 0 ? $homePlayerId : $awayPlayerId;
            $legLoserPlayerId = $legWinnerIndex === 1 ? $homePlayerId : $awayPlayerId;

            // Simuliere ein Leg
            $legThrows = $this->simulateLeg(
                $matchId,
                $currentLeg,
                $legWinner,
                $legLoser,
                $legWinnerPlayerId,
                $legLoserPlayerId,
                $season,
                $startedAt
            );

            $webhookCalls = array_merge($webhookCalls, $legThrows);

            if ($legWinnerIndex === 0) {
                $homeLegsWonCount++;
            } else {
                $awayLegsWonCount++;
            }

            $currentLeg++;
        }

        // Erstelle finales match_state Event
        $finalMatchState = $this->createFinalMatchState(
            $matchId,
            $season,
            $homePlayer,
            $awayPlayer,
            $homePlayerId,
            $awayPlayerId,
            $homeLegsWon,
            $awayLegsWon,
            $winnerIndex,
            $startedAt,
            now()
        );

        $webhookCalls[] = $this->createWebhookCall('match_state', $matchId, $finalMatchState);

        // Verarbeite alle WebhookCalls in einer Transaktion
        DB::transaction(function () use ($webhookCalls, $fixture, $matchId) {
            foreach ($webhookCalls as $webhookCall) {
                // WebhookCall wird bereits in createWebhookCall erstellt und gespeichert
                $job = new WebhookProcessing($webhookCall);
                $job->handle();
            }

            // Nach der Verarbeitung sollte das Match erstellt sein
            // Finde das Match und ordne es dem Fixture zu
            $match = \App\Models\DartMatch::where('autodarts_match_id', $matchId)->first();

            if ($match && $match->finished_at !== null) {
                try {
                    app(\App\Actions\AssignMatchToFixture::class)->handle($match, $fixture);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to assign match to fixture after simulation', [
                        'fixture_id' => $fixture->id,
                        'match_id' => $match->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    protected function createInitialMatchState(
        string $matchId,
        $season,
        Player $homePlayer,
        Player $awayPlayer,
        string $homePlayerId,
        string $awayPlayerId,
        \DateTimeInterface $startedAt
    ): array {
        $baseScore = $season->base_score ?? 501;
        $inMode = $season->in_mode ?? 'Straight';
        $outMode = $season->out_mode ?? 'Double';
        $bullMode = $season->bull_mode ?? '25/50';
        $maxRounds = $season->max_rounds ?? 20;

        return [
            'event' => 'match_state',
            'timestamp' => $startedAt->format('Y-m-d\TH:i:s.v\Z'),
            'source' => 'Tools for Autodarts',
            'matchId' => $matchId,
            'variant' => 'X01',
            'data' => [
                'match' => [
                    'id' => $matchId,
                    'createdAt' => $startedAt->format('Y-m-d\TH:i:s.u\Z'),
                    'finished' => false,
                    'gameFinished' => false,
                    'type' => 'Online',
                    'variant' => 'X01',
                    'leg' => 1,
                    'set' => 1,
                    'round' => 1,
                    'player' => 0,
                    'players' => [
                        $this->createPlayerData($homePlayer, 0),
                        $this->createPlayerData($awayPlayer, 1),
                    ],
                    'scores' => [
                        ['legs' => 0, 'sets' => 0],
                        ['legs' => 0, 'sets' => 0],
                    ],
                    'settings' => [
                        'baseScore' => $baseScore,
                        'inMode' => $inMode,
                        'outMode' => $outMode,
                        'bullMode' => $bullMode,
                        'maxRounds' => $maxRounds,
                        'matchMode' => 'Legs',
                        'legsToWin' => match ($season->match_format) {
                            'best_of_3' => 2,
                            'best_of_5' => 3,
                            default => 3,
                        },
                    ],
                    'stats' => [
                        [
                            'legStats' => [
                                'average' => 0,
                                'dartsThrown' => 0,
                                'checkouts' => 0,
                                'checkoutsHit' => 0,
                            ],
                            'matchStats' => [
                                'average' => 0,
                                'dartsThrown' => 0,
                                'checkouts' => 0,
                                'checkoutsHit' => 0,
                            ],
                        ],
                        [
                            'legStats' => [
                                'average' => 0,
                                'dartsThrown' => 0,
                                'checkouts' => 0,
                                'checkoutsHit' => 0,
                            ],
                            'matchStats' => [
                                'average' => 0,
                                'dartsThrown' => 0,
                                'checkouts' => 0,
                                'checkoutsHit' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function createFinalMatchState(
        string $matchId,
        $season,
        Player $homePlayer,
        Player $awayPlayer,
        string $homePlayerId,
        string $awayPlayerId,
        int $homeLegsWon,
        int $awayLegsWon,
        int $winnerIndex,
        \DateTimeInterface $startedAt,
        \DateTimeInterface $finishedAt
    ): array {
        $baseScore = $season->base_score ?? 501;

        // Berechne realistische Statistiken
        $homeAverage = fake()->randomFloat(2, 40, 80);
        $awayAverage = fake()->randomFloat(2, 40, 80);
        $homeDartsThrown = fake()->numberBetween(60, 150);
        $awayDartsThrown = fake()->numberBetween(60, 150);
        $homeCheckoutAttempts = fake()->numberBetween(5, 15);
        $awayCheckoutAttempts = fake()->numberBetween(5, 15);
        $homeCheckoutRate = fake()->randomFloat(4, 0.20, 0.50);
        $awayCheckoutRate = fake()->randomFloat(4, 0.20, 0.50);
        $homeCheckoutHits = (int) round($homeCheckoutAttempts * $homeCheckoutRate);
        $awayCheckoutHits = (int) round($awayCheckoutAttempts * $awayCheckoutRate);
        $homeTotal180s = fake()->numberBetween(0, 3);
        $awayTotal180s = fake()->numberBetween(0, 3);

        return [
            'event' => 'match_state',
            'timestamp' => $finishedAt->format('Y-m-d\TH:i:s.v\Z'),
            'source' => 'Tools for Autodarts',
            'matchId' => $matchId,
            'variant' => 'X01',
            'data' => [
                'match' => [
                    'id' => $matchId,
                    'createdAt' => $startedAt->format('Y-m-d\TH:i:s.u\Z'),
                    'finished' => true,
                    'gameFinished' => true,
                    'type' => 'Online',
                    'variant' => 'X01',
                    'leg' => $homeLegsWon + $awayLegsWon,
                    'set' => 1,
                    'round' => 1,
                    'player' => $winnerIndex,
                    'players' => [
                        $this->createPlayerData($homePlayer, 0, $homePlayerId),
                        $this->createPlayerData($awayPlayer, 1, $awayPlayerId),
                    ],
                    'scores' => [
                        ['legs' => $homeLegsWon, 'sets' => 0],
                        ['legs' => $awayLegsWon, 'sets' => 0],
                    ],
                    'gameWinner' => $winnerIndex,
                    'winner' => $winnerIndex,
                    'settings' => [
                        'baseScore' => $baseScore,
                        'inMode' => $season->in_mode ?? 'Straight',
                        'outMode' => $season->out_mode ?? 'Double',
                        'bullMode' => $season->bull_mode ?? '25/50',
                        'maxRounds' => $season->max_rounds ?? 20,
                        'matchMode' => 'Legs',
                        'legsToWin' => match ($season->match_format) {
                            'best_of_3' => 2,
                            'best_of_5' => 3,
                            default => 3,
                        },
                    ],
                    'stats' => [
                        [
                            'legStats' => [
                                'average' => $homeAverage,
                                'dartsThrown' => $homeDartsThrown,
                                'checkouts' => $homeCheckoutAttempts,
                                'checkoutsHit' => $homeCheckoutHits,
                                'checkoutPercent' => $homeCheckoutRate,
                                'total180' => $homeTotal180s,
                            ],
                            'matchStats' => [
                                'average' => $homeAverage,
                                'dartsThrown' => $homeDartsThrown,
                                'checkouts' => $homeCheckoutAttempts,
                                'checkoutsHit' => $homeCheckoutHits,
                                'checkoutPercent' => $homeCheckoutRate,
                                'total180' => $homeTotal180s,
                            ],
                        ],
                        [
                            'legStats' => [
                                'average' => $awayAverage,
                                'dartsThrown' => $awayDartsThrown,
                                'checkouts' => $awayCheckoutAttempts,
                                'checkoutsHit' => $awayCheckoutHits,
                                'checkoutPercent' => $awayCheckoutRate,
                                'total180' => $awayTotal180s,
                            ],
                            'matchStats' => [
                                'average' => $awayAverage,
                                'dartsThrown' => $awayDartsThrown,
                                'checkouts' => $awayCheckoutAttempts,
                                'checkoutsHit' => $awayCheckoutHits,
                                'checkoutPercent' => $awayCheckoutRate,
                                'total180' => $awayTotal180s,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function createPlayerData(Player $player, int $index, ?string $playerId = null): array
    {
        $userId = $player->autodarts_user_id ?? (string) Str::uuid();

        return [
            'id' => $playerId ?? (string) Str::uuid(), // Spiel-spezifische ID
            'index' => $index,
            'name' => $player->name,
            'userId' => $userId,
            'avatarUrl' => $player->avatar_url,
        ];
    }

    protected function simulateLeg(
        string $matchId,
        int $legNumber,
        Player $winner,
        Player $loser,
        string $winnerPlayerId,
        string $loserPlayerId,
        $season,
        \DateTimeInterface $startedAt
    ): array {
        $baseScore = $season->base_score ?? 501;
        $webhookCalls = [];
        $legStartTime = clone $startedAt;
        $legStartTime->modify('+' . (($legNumber - 1) * 5) . ' minutes');
        $currentTime = clone $legStartTime;

        // Simuliere abwechselnd zwischen den Spielern
        $players = [$winner, $loser];
        $playerIds = [$winnerPlayerId, $loserPlayerId];
        $scores = [$baseScore, $baseScore];
        $currentPlayerIndex = 0; // Gewinner beginnt
        $roundNumber = 1;
        $legFinished = false;

        while (! $legFinished) {
            $currentPlayer = $players[$currentPlayerIndex];
            $currentPlayerId = $playerIds[$currentPlayerIndex];
            $currentScore = $scores[$currentPlayerIndex];
            $turnId = (string) Str::uuid();
            $turnScore = 0;
            $throws = [];
            $busted = false;

            // Simuliere einen Turn (3 Würfe)
            for ($throwNumber = 0; $throwNumber < 3; $throwNumber++) {
                $throwId = (string) Str::uuid();
                $throwScore = $this->generateRealisticThrow($currentScore, $throwNumber);

                // Prüfe auf Checkout-Möglichkeit
                if ($currentScore <= 170 && $currentScore > 0 && $currentScore % 2 === 0) {
                    // Checkout möglich (nur gerade Zahlen bei Double-Out)
                    if (fake()->boolean(30)) {
                        // Erfolgreicher Checkout
                        $checkoutScore = $currentScore;
                        $throwScore = $checkoutScore;
                        $currentScore = 0;
                        $legFinished = true;
                        $scores[$currentPlayerIndex] = 0;

                        $throwTimestamp = (clone $currentTime)->modify('+' . ($throwNumber * 2) . ' seconds');
                        $throws[] = $this->createThrowData($throwId, $checkoutScore, $throwTimestamp, $throwNumber);
                        $turnScore = $checkoutScore;
                        break;
                    }
                }

                // Wenn Score zu niedrig für Checkout, versuche es trotzdem (kann bust sein)
                if ($currentScore < 170 && $currentScore > 0 && $currentScore % 2 !== 0) {
                    // Ungerade Zahl - muss auf gerade kommen
                    $throwScore = fake()->numberBetween(1, min(20, $currentScore - 1));
                    if (($currentScore - $throwScore) % 2 === 0 && ($currentScore - $throwScore) > 0) {
                        // Jetzt gerade, kann beim nächsten Wurf checkouten
                        $currentScore -= $throwScore;
                        $scores[$currentPlayerIndex] = $currentScore;
                        $throwTimestamp = (clone $currentTime)->modify('+' . ($throwNumber * 2) . ' seconds');
                        $throws[] = $this->createThrowData($throwId, $throwScore, $throwTimestamp, $throwNumber);
                        $turnScore += $throwScore;
                        continue;
                    }
                }

                // Normaler Wurf
                $newScore = $currentScore - $throwScore;

                if ($newScore < 0 || ($newScore === 1)) {
                    // Bust (Score < 0 oder 1 übrig, kann nicht checkouten)
                    $busted = true;
                    $scores[$currentPlayerIndex] = $baseScore; // Reset bei Bust
                    $throwScore = fake()->numberBetween(1, 60); // Realistischer Bust-Wurf
                    $throwTimestamp = (clone $currentTime)->modify('+' . ($throwNumber * 2) . ' seconds');
                    $throws[] = $this->createThrowData($throwId, $throwScore, $throwTimestamp, $throwNumber);
                    $turnScore = 0; // Turn zählt nicht
                    break;
                }

                $currentScore = $newScore;
                $scores[$currentPlayerIndex] = $currentScore;
                $throwTimestamp = (clone $currentTime)->modify('+' . ($throwNumber * 2) . ' seconds');
                $throws[] = $this->createThrowData($throwId, $throwScore, $throwTimestamp, $throwNumber);
                $turnScore += $throwScore;
            }

            // Erstelle throw Events für jeden Wurf
            foreach ($throws as $index => $throwData) {
                $webhookCalls[] = $this->createThrowWebhookCall(
                    $matchId,
                    $turnId,
                    $currentPlayer,
                    $currentPlayerId,
                    $legNumber,
                    1, // set
                    $roundNumber,
                    $scores[$currentPlayerIndex],
                    $throwData,
                    $index
                );
            }

            if ($legFinished) {
                break;
            }

            // Wechsle Spieler
            $currentPlayerIndex = ($currentPlayerIndex + 1) % 2;
            $roundNumber++;
            $currentTime = (clone $currentTime)->modify('+5 seconds');
        }

        return $webhookCalls;
    }

    protected function generateRealisticThrow(int $currentScore, int $throwNumber): int
    {
        // Realistische Punktverteilung
        $average = fake()->randomFloat(1, 40, 80);

        // 180 ist selten
        if (fake()->boolean(5)) {
            return 180;
        }

        // Triple 20 (60) ist häufig
        if (fake()->boolean(30)) {
            return 60;
        }

        // Andere Triple
        if (fake()->boolean(20)) {
            return fake()->randomElement([57, 54, 51, 45, 42, 39, 33, 30, 27, 24, 21, 18, 15, 12, 9, 6, 3]);
        }

        // Single/Double
        return fake()->numberBetween(1, 60);
    }

    protected function generateCheckout(int $score): int
    {
        // Einfache Checkout-Logik: nur Double-Out
        if ($score <= 40 && $score % 2 === 0) {
            return $score; // Direkter Checkout möglich
        }

        // Für komplexere Checkouts, verwende einfache Logik
        if ($score <= 170) {
            // Versuche einen realistischen Checkout
            $possibleCheckouts = [
                170 => 170, // T20, T20, Bull
                167 => 167, // T20, T19, Bull
                164 => 164, // T20, T18, Bull
                161 => 161, // T20, T17, Bull
                160 => 160, // T20, T20, D20
            ];

            if (isset($possibleCheckouts[$score])) {
                return $score;
            }

            // Für andere Scores, versuche es mit einem einfachen Double
            if ($score <= 40) {
                return $score;
            }
        }

        return $score; // Fallback
    }

    protected function createThrowData(string $throwId, int $points, \DateTimeInterface $timestamp, int $throwNumber = 0): array
    {
        $segmentNumber = $this->pointsToSegment($points);
        $multiplier = $this->pointsToMultiplier($points, $segmentNumber);

        return [
            'id' => $throwId,
            'throw' => $throwNumber,
            'segment' => [
                'number' => $segmentNumber,
                'multiplier' => $multiplier,
                'name' => $this->segmentName($segmentNumber, $multiplier),
                'bed' => $this->segmentBed($multiplier),
            ],
            'coords' => [
                'x' => fake()->randomFloat(4, -0.5, 0.5),
                'y' => fake()->randomFloat(4, -0.5, 0.5),
            ],
            'createdAt' => $timestamp->format('Y-m-d\TH:i:s.u\Z'),
            'entry' => 'detected',
            'marks' => null,
        ];
    }

    protected function pointsToSegment(int $points): int
    {
        if ($points === 0) {
            return 0;
        }

        if ($points === 25 || $points === 50) {
            return 25;
        }

        if ($points === 180) {
            return 20;
        }

        if ($points % 3 === 0 && $points <= 60) {
            return $points / 3;
        }

        if ($points % 2 === 0 && $points <= 40) {
            return $points / 2;
        }

        return min(20, $points);
    }

    protected function pointsToMultiplier(int $points, int $segmentNumber): int
    {
        if ($points === 0) {
            return 0;
        }

        if ($points === 25) {
            return 1;
        }

        if ($points === 50) {
            return 2;
        }

        if ($points === 180) {
            return 3;
        }

        if ($segmentNumber > 0 && $points % $segmentNumber === 0) {
            $multiplier = $points / $segmentNumber;
            if ($multiplier <= 3) {
                return (int) $multiplier;
            }
        }

        return 1;
    }

    protected function segmentName(int $segmentNumber, int $multiplier): string
    {
        if ($segmentNumber === 0) {
            return 'MISS';
        }

        if ($segmentNumber === 25) {
            return $multiplier === 2 ? 'DB' : 'SB';
        }

        $prefix = match ($multiplier) {
            3 => 'T',
            2 => 'D',
            default => 'S',
        };

        return $prefix . $segmentNumber;
    }

    protected function segmentBed(int $multiplier): string
    {
        return match ($multiplier) {
            3 => 'TripleOuter',
            2 => 'DoubleOuter',
            default => 'SingleInner',
        };
    }

    protected function createThrowWebhookCall(
        string $matchId,
        string $turnId,
        Player $player,
        string $playerId,
        int $leg,
        int $set,
        int $round,
        int $score,
        array $throwData,
        int $throwNumber
    ): WebhookCall {
        // Parse timestamp from throwData
        $timestampStr = $throwData['createdAt'] ?? null;
        $timestamp = null;
        if ($timestampStr) {
            try {
                $timestamp = Carbon::parse($timestampStr);
            } catch (\Exception $e) {
                $timestamp = now();
            }
        } else {
            $timestamp = now();
        }

        $payload = [
            'event' => 'throw',
            'timestamp' => $timestamp->format('Y-m-d\TH:i:s.v\Z'),
            'source' => 'Tools for Autodarts',
            'matchId' => $matchId,
            'data' => [
                'matchId' => $matchId,
                'turnId' => $turnId,
                'playerId' => $playerId,
                'playerName' => $player->name,
                'leg' => $leg,
                'set' => $set,
                'round' => $round,
                'score' => $score,
                'throw' => $throwData,
            ],
        ];

        return $this->createWebhookCall('throw', $matchId, $payload);
    }

    protected function createWebhookCall(string $event, string $matchId, array $payload): WebhookCall
    {
        // Create WebhookCall with array payload - Spatie package will handle JSON encoding
        $webhookCall = WebhookCall::create([
            'name' => 'default',
            'url' => '',
            'headers' => [],
            'payload' => $payload,
        ]);

        return $webhookCall;
    }
}
