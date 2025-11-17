<?php

namespace App\Support;

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Player;
use App\Models\Turn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class WebhookProcessing extends ProcessWebhookJob
{
    public function handle(): void
    {
        $event = $this->webhookCall->payload['event'];
        Log::debug("event received: $event");

        switch ($event) {
            case 'throw':
                $this->handleThrow();
                break;
            case 'match_state':
                $this->handleMatchState();
                break;
        }
    }

    public function handleThrow(): void
    {
        $payload = $this->webhookCall->payload;
        $data = $payload['data'];

        DB::transaction(function () use ($payload, $data) {
            // Get or create player with deadlock retry
            $player = $this->firstOrCreateWithRetry(
                Player::class,
                ['autodarts_user_id' => $data['playerId']],
                ['name' => $data['playerName']]
            );

            // Get or create match with deadlock retry
            $match = $this->firstOrCreateWithRetry(
                DartMatch::class,
                ['autodarts_match_id' => $payload['matchId']],
                [
                    'variant' => 'X01',
                    'type' => 'Online',
                    'started_at' => now(),
                ]
            );

            // Get or create leg with deadlock retry
            $leg = $this->firstOrCreateWithRetry(
                Leg::class,
                [
                    'match_id' => $match->id,
                    'leg_number' => $data['leg'],
                    'set_number' => $data['set'],
                ],
                ['started_at' => now()]
            );

            // Get or create turn with deadlock retry
            $turn = $this->firstOrCreateWithRetry(
                Turn::class,
                ['autodarts_turn_id' => $data['turnId']],
                [
                    'leg_id' => $leg->id,
                    'player_id' => $player->id,
                    'round_number' => $data['round'],
                    'turn_number' => $data['throw']['throw'] ?? 0,
                    'points' => $data['score'] ?? 0,
                    'started_at' => $data['throw']['createdAt'] ?? now(),
                ]
            );

            // Create throw
            $throwData = $data['throw'];
            DartThrow::create([
                'turn_id' => $turn->id,
                'autodarts_throw_id' => $throwData['id'],
                'webhook_call_id' => $this->webhookCall->id,
                'dart_number' => $throwData['throw'],
                'segment_number' => $throwData['segment']['number'] ?? null,
                'multiplier' => $throwData['segment']['multiplier'] ?? 1,
                'points' => ($throwData['segment']['number'] ?? 0) * ($throwData['segment']['multiplier'] ?? 1),
                'segment_name' => $throwData['segment']['name'] ?? null,
                'segment_bed' => $throwData['segment']['bed'] ?? null,
                'coords_x' => $throwData['coords']['x'] ?? null,
                'coords_y' => $throwData['coords']['y'] ?? null,
            ]);
        });

        Log::debug('throw processed', [
            'matchId' => $payload['matchId'],
            'player' => $data['playerName'],
            'segment' => $data['throw']['segment']['name'] ?? 'unknown',
        ]);
    }

    public function handleMatchState(): void
    {
        $payload = $this->webhookCall->payload;
        $matchData = $payload['data']['match'];

        DB::transaction(function () use ($payload, $matchData) {
            // Get or create match with deadlock retry
            $match = $this->firstOrCreateWithRetry(
                DartMatch::class,
                ['autodarts_match_id' => $payload['matchId']],
                [
                    'variant' => $payload['variant'] ?? 'X01',
                    'type' => $matchData['type'] ?? 'Online',
                    'started_at' => $matchData['createdAt'] ?? now(),
                ]
            );

            // Update match settings
            if (isset($matchData['settings'])) {
                $match->update([
                    'base_score' => $matchData['settings']['baseScore'] ?? 501,
                    'in_mode' => $matchData['settings']['inMode'] ?? 'Straight',
                    'out_mode' => $matchData['settings']['outMode'] ?? 'Straight',
                    'bull_mode' => $matchData['settings']['bullMode'] ?? '25/50',
                    'max_rounds' => $matchData['settings']['maxRounds'] ?? 20,
                ]);
            }

            // Sync players FIRST
            foreach ($matchData['players'] as $index => $playerData) {
                // For bots/guests without userId, generate a deterministic fake UUID based on name
                $userId = $playerData['userId'] ?? $this->generateBotUuid($playerData['name']);

                $player = $this->firstOrCreateWithRetry(
                    Player::class,
                    ['autodarts_user_id' => $userId],
                    [
                        'name' => $playerData['name'],
                        'email' => $playerData['user']['userSettings']['email'] ?? null,
                        'country' => $playerData['user']['country'] ?? null,
                        'avatar_url' => $playerData['avatarUrl'] ?? null,
                    ]
                );

                // Sync pivot table
                $match->players()->syncWithoutDetaching([
                    $player->id => [
                        'player_index' => $index,
                        'legs_won' => $matchData['scores'][$index]['legs'] ?? 0,
                        'sets_won' => $matchData['scores'][$index]['sets'] ?? 0,
                    ],
                ]);
            }

            // Update match status (after players are synced)
            if ($matchData['finished'] ?? false) {
                $winnerIndex = $matchData['winner'] ?? null;
                if ($winnerIndex !== null && isset($matchData['players'][$winnerIndex])) {
                    $winnerPlayerId = $matchData['players'][$winnerIndex]['userId'];
                    $winner = Player::where('autodarts_user_id', $winnerPlayerId)->first();

                    $match->update([
                        'finished_at' => now(),
                        'winner_player_id' => $winner?->id,
                    ]);
                }
            }

            // Process turns from match state (for correction detection)
            if (isset($matchData['turns']) && is_array($matchData['turns'])) {
                $this->processTurnsFromMatchState($match, $matchData['turns']);
            }
        });

        Log::debug('match_state processed', [
            'matchId' => $payload['matchId'],
            'finished' => $matchData['finished'] ?? false,
        ]);
    }

    protected function processTurnsFromMatchState(DartMatch $match, array $turns): void
    {
        foreach ($turns as $turnData) {
            $player = Player::where('autodarts_user_id', $turnData['playerId'])->first();
            if (! $player) {
                continue;
            }

            $leg = Leg::where('match_id', $match->id)
                ->where('leg_number', $match->leg ?? 1)
                ->first();

            if (! $leg) {
                continue;
            }

            // Parse timestamps, treating invalid dates as null
            $startedAt = $this->parseTimestamp($turnData['createdAt'] ?? null);
            $finishedAt = $this->parseTimestamp($turnData['finishedAt'] ?? null);

            $turn = $this->firstOrCreateWithRetry(
                Turn::class,
                ['autodarts_turn_id' => $turnData['id']],
                [
                    'leg_id' => $leg->id,
                    'player_id' => $player->id,
                    'round_number' => $turnData['round'],
                    'turn_number' => $turnData['turn'] ?? 0,
                    'points' => $turnData['points'] ?? 0,
                    'score_after' => $turnData['score'] ?? null,
                    'busted' => $turnData['busted'] ?? false,
                    'started_at' => $startedAt ?? now(),
                    'finished_at' => $finishedAt,
                ]
            );

            // Process throws within this turn
            if (isset($turnData['throws']) && is_array($turnData['throws'])) {
                $this->syncThrowsForTurn($turn, $turnData['throws']);
            }
        }
    }

    protected function syncThrowsForTurn(Turn $turn, array $throwsData): void
    {
        // Get existing throws for this turn
        $existingThrows = $turn->throws()->notCorrected()->get()->keyBy('autodarts_throw_id');

        foreach ($throwsData as $throwData) {
            $throwId = $throwData['id'];

            // If throw already exists with same ID, no correction needed
            if ($existingThrows->has($throwId)) {
                continue;
            }

            // Check if this dart_number position already has a throw
            $dartNumber = $throwData['throw'];
            $existingThrowAtPosition = $turn->throws()
                ->notCorrected()
                ->where('dart_number', $dartNumber)
                ->first();

            if ($existingThrowAtPosition) {
                // Mark old throw as corrected
                $existingThrowAtPosition->update([
                    'is_corrected' => true,
                    'corrected_at' => now(),
                ]);
            }

            // Create new throw
            $newThrow = DartThrow::create([
                'turn_id' => $turn->id,
                'autodarts_throw_id' => $throwId,
                'webhook_call_id' => $this->webhookCall->id,
                'dart_number' => $dartNumber,
                'segment_number' => $throwData['segment']['number'] ?? null,
                'multiplier' => $throwData['segment']['multiplier'] ?? 1,
                'points' => ($throwData['segment']['number'] ?? 0) * ($throwData['segment']['multiplier'] ?? 1),
                'segment_name' => $throwData['segment']['name'] ?? null,
                'segment_bed' => $throwData['segment']['bed'] ?? null,
                'coords_x' => $throwData['coords']['x'] ?? null,
                'coords_y' => $throwData['coords']['y'] ?? null,
            ]);

            // Link correction
            if ($existingThrowAtPosition) {
                $existingThrowAtPosition->update([
                    'corrected_by_throw_id' => $newThrow->id,
                ]);
            }
        }
    }

    protected function firstOrCreateWithRetry(string $modelClass, array $attributes, array $values = [], int $maxAttempts = 3): mixed
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                return $modelClass::firstOrCreate($attributes, $values);
            } catch (\Illuminate\Database\QueryException $e) {
                $attempts++;

                // Check if it's a deadlock or duplicate key error
                if ($e->getCode() === 'HY000' || str_contains($e->getMessage(), '1020') || str_contains($e->getMessage(), 'Duplicate entry')) {
                    // If last attempt, just try to find the record
                    if ($attempts >= $maxAttempts) {
                        return $modelClass::where($attributes)->first() ?? throw $e;
                    }

                    // Wait a bit before retrying (exponential backoff)
                    usleep(50000 * $attempts); // 50ms, 100ms, 150ms

                    continue;
                }

                // If it's not a deadlock, rethrow
                throw $e;
            }
        }

        // Final fallback: just get it
        return $modelClass::where($attributes)->firstOrFail();
    }

    protected function generateBotUuid(string $botName): string
    {
        // Generate deterministic UUIDs for bot players based on their name
        // This ensures all games against "Bot Level 2" use the same player ID

        // Extract level number if present (e.g., "Bot Level 2" -> 2)
        if (preg_match('/Bot Level (\d+)/i', $botName, $matches)) {
            $level = str_pad($matches[1], 3, '0', STR_PAD_LEFT);

            return "00000000-0000-0000-0001-0000000{$level}";
        }

        // For other bot names, generate UUID from hash
        $hash = md5($botName);

        return sprintf(
            '00000000-0000-0000-0002-%s',
            substr($hash, 0, 12)
        );
    }

    protected function parseTimestamp(?string $timestamp): ?string
    {
        // If no timestamp provided, return null
        if (! $timestamp) {
            return null;
        }

        // Autodarts sometimes sends '0001-01-01 00:00:00' for unfinished turns
        // This is invalid for MySQL, so treat it as null
        if (str_starts_with($timestamp, '0001-')) {
            return null;
        }

        // Return the timestamp as-is for Laravel to cast
        return $timestamp;
    }
}
