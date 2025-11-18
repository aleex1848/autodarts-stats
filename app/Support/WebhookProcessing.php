<?php

namespace App\Support;

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Player;
use App\Models\Turn;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
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
            // Also try to find by name to avoid duplicates if userId changed
            $player = $this->findOrCreatePlayer(
                $data['playerId'],
                $data['playerName'],
                []
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

                // Try to find or create player by autodarts_user_id
                // If not found, also try to find by name to avoid duplicates
                $player = $this->findOrCreatePlayer(
                    $userId,
                    $playerData['name'],
                    [
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

    protected function findOrCreatePlayer(string $userId, string $name, array $additionalValues = []): Player
    {
        // First, try to find by autodarts_user_id
        $player = Player::where('autodarts_user_id', $userId)->first();
        if ($player) {
            // Update name and other values if they changed
            $updateData = array_merge(['name' => $name], $additionalValues);
            $player->update($updateData);

            return $player;
        }

        // If not found by userId, check if a player with the same name exists
        // This handles cases where Autodarts sends different userIds for the same user
        $playerByName = Player::where('name', $name)->first();
        if ($playerByName) {
            // Found a player with the same name - update the autodarts_user_id if it's different
            // This helps consolidate duplicate players
            Log::info('Player found by name but different autodarts_user_id', [
                'name' => $name,
                'existing_user_id' => $playerByName->autodarts_user_id,
                'new_user_id' => $userId,
            ]);

            // Only update if the new userId is more "valid" (not a bot UUID)
            // Bot UUIDs start with 00000000, real user IDs don't
            if (str_starts_with($userId, '00000000-0000-0000')) {
                // New userId is a bot UUID, keep the existing one
                $playerByName->update($additionalValues);

                return $playerByName;
            }

            // Try to update autodarts_user_id, but catch unique constraint violations
            try {
                $playerByName->update(array_merge([
                    'autodarts_user_id' => $userId,
                    'name' => $name,
                ], $additionalValues));

                return $playerByName->fresh();
            } catch (UniqueConstraintViolationException|QueryException $e) {
                // Another player already has this userId, just update values
                $playerByName->update(array_merge(['name' => $name], $additionalValues));

                return $playerByName;
            }
        }

        // No player found, create a new one
        return $this->firstOrCreateWithRetry(
            Player::class,
            ['autodarts_user_id' => $userId],
            array_merge(['name' => $name], $additionalValues)
        );
    }

    protected function firstOrCreateWithRetry(string $modelClass, array $attributes, array $values = [], int $maxAttempts = 3): mixed
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            // First, try to find existing record
            $existing = $modelClass::where($attributes)->first();
            if ($existing) {
                // Record exists, update it with new values if provided
                if (! empty($values)) {
                    try {
                        $existing->update($values);
                        $existing->refresh();
                    } catch (\Exception $e) {
                        // Ignore update errors - record exists, that's what matters
                    }
                }

                return $existing;
            }

            // Record doesn't exist, try to create it
            try {
                return $modelClass::create(array_merge($attributes, $values));
            } catch (DeadlockException|UniqueConstraintViolationException|QueryException|\PDOException $e) {
                $attempts++;

                // Check if it's a deadlock, record changed (1020), or duplicate key error
                $errorMessage = $e->getMessage();
                $errorCode = $e->getCode();

                $isDuplicateError = $e instanceof UniqueConstraintViolationException
                    || $errorCode === 23000
                    || $errorCode === '23000'
                    || str_contains($errorMessage, 'Duplicate entry')
                    || str_contains($errorMessage, '1062')
                    || str_contains($errorMessage, 'Integrity constraint violation');

                $isDeadlockError = $e instanceof DeadlockException
                    || $errorCode === 'HY000'
                    || $errorCode === 0
                    || str_contains($errorMessage, '1020')
                    || str_contains($errorMessage, 'Record has changed since last read')
                    || str_contains($errorMessage, 'Deadlock');

                $isRetryableError = $isDuplicateError || $isDeadlockError;

                if ($isRetryableError) {
                    // Record was created by another process, try to find it again
                    // Wait a tiny bit first for the commit to complete
                    usleep(10000 + ($attempts * 5000)); // 10ms, 15ms, 20ms
                    $existing = $modelClass::where($attributes)->first();
                    if ($existing) {
                        // Record exists now, update it with new values if provided
                        if (! empty($values)) {
                            try {
                                $existing->update($values);
                                $existing->refresh();
                            } catch (\Exception $updateException) {
                                // Ignore update errors - record exists, that's what matters
                            }
                        }

                        return $existing;
                    }

                    // If last attempt and still not found, wait a bit longer and try once more
                    if ($attempts >= $maxAttempts) {
                        usleep(50000); // 50ms
                        $finalCheck = $modelClass::where($attributes)->first();
                        if ($finalCheck) {
                            if (! empty($values)) {
                                try {
                                    $finalCheck->update($values);
                                    $finalCheck->refresh();
                                } catch (\Exception $updateException) {
                                    // Ignore update errors
                                }
                            }

                            return $finalCheck;
                        }

                        // Still not found after all retries - this shouldn't happen for duplicate errors
                        // For deadlock errors, it might be legitimately missing
                        if ($isDuplicateError) {
                            // For duplicate errors, we MUST find the record
                            // Log a warning and try one more time
                            Log::warning('Duplicate entry error but record not found', [
                                'model' => $modelClass,
                                'attributes' => $attributes,
                                'attempts' => $attempts,
                            ]);
                            usleep(100000); // 100ms
                            $finalCheck = $modelClass::where($attributes)->first();
                            if ($finalCheck) {
                                return $finalCheck;
                            }
                        }

                        // Only rethrow if it's not a duplicate error (for duplicate, record MUST exist)
                        if (! $isDuplicateError) {
                            throw $e;
                        }

                        // For duplicate errors, return a new instance with the attributes
                        // This is a fallback - should rarely happen
                        return $modelClass::where($attributes)->firstOrFail();
                    }

                    // Wait a bit before retrying (exponential backoff with jitter)
                    $delay = (50000 * $attempts) + random_int(0, 10000); // 50ms, 100ms + jitter
                    usleep($delay);

                    continue;
                }

                // If it's not a retryable error, rethrow
                throw $e;
            }
        }

        // Final fallback: try to find the record (it might exist now)
        $existing = $modelClass::where($attributes)->first();
        if ($existing) {
            if (! empty($values)) {
                try {
                    $existing->update($values);
                    $existing->refresh();
                } catch (\Exception $e) {
                    // Ignore update errors
                }
            }

            return $existing;
        }

        // If still not found, try to get it (will throw ModelNotFoundException if not found)
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
