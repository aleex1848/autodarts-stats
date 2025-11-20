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

            // Create throw with retry logic to handle race conditions
            $throwData = $data['throw'];
            $this->createThrowWithRetry(
                $turn,
                $throwData['id'],
                $throwData,
                $throwData['throw']
            );
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

                // Get statistics from webhook if available
                $matchStats = $matchData['stats'][$index]['matchStats'] ?? null;
                $matchAverage = null;
                $checkoutRate = null;
                $checkoutAttempts = null;
                $checkoutHits = null;
                $total180s = null;
                $dartsThrown = null;

                if ($matchStats) {
                    // Average is already calculated by Autodarts
                    $matchAverage = isset($matchStats['average']) ? round((float) $matchStats['average'], 2) : null;

                    // Checkout rate is already calculated by Autodarts (as decimal between 0 and 1)
                    $checkoutRate = isset($matchStats['checkoutPercent']) ? round((float) $matchStats['checkoutPercent'], 4) : null;

                    // Checkout attempts and hits
                    $checkoutAttempts = isset($matchStats['checkouts']) ? (int) $matchStats['checkouts'] : null;
                    $checkoutHits = isset($matchStats['checkoutsHit']) ? (int) $matchStats['checkoutsHit'] : null;

                    // Total 180s
                    $total180s = isset($matchStats['total180']) ? (int) $matchStats['total180'] : null;

                    // Total darts thrown
                    $dartsThrown = isset($matchStats['dartsThrown']) ? (int) $matchStats['dartsThrown'] : null;
                }

                // Sync pivot table with retry logic to handle race conditions
                $this->syncMatchPlayerWithRetry($match, $player->id, [
                    'player_index' => $index,
                    'legs_won' => $matchData['scores'][$index]['legs'] ?? 0,
                    'sets_won' => $matchData['scores'][$index]['sets'] ?? 0,
                    'match_average' => $matchAverage,
                    'checkout_rate' => $checkoutRate,
                    'checkout_attempts' => $checkoutAttempts,
                    'checkout_hits' => $checkoutHits,
                    'total_180s' => $total180s ?? 0,
                    'darts_thrown' => $dartsThrown,
                ]);
            }

            // Update match status (after players are synced)
            if ($matchData['finished'] ?? false) {
                $updateData = [
                    'finished_at' => now(),
                ];

                // Try to find winner if winner index is provided
                $winnerIndex = $matchData['winner'] ?? $matchData['gameWinner'] ?? null;
                if ($winnerIndex !== null && isset($matchData['players'][$winnerIndex])) {
                    $winnerPlayerId = $matchData['players'][$winnerIndex]['userId'] ?? null;
                    if ($winnerPlayerId) {
                        $winner = Player::where('autodarts_user_id', $winnerPlayerId)->first();
                        if ($winner) {
                            $updateData['winner_player_id'] = $winner->id;
                        }
                    }
                }

                $match->update($updateData);

                // Calculate and update final positions for all players
                $this->updateFinalPositions($match, $matchData);

                // Derive winner from final_position if winner_player_id is still null
                if ($match->winner_player_id === null) {
                    $this->deriveWinnerFromFinalPosition($match);
                }
            }

            // Process turns from match state (for correction detection)
            if (isset($matchData['turns']) && is_array($matchData['turns'])) {
                $this->processTurnsFromMatchState($match, $matchData['turns'], $matchData);
            }

            // Update leg winners and statistics
            $this->updateLegs($match, $matchData);

            // Only calculate statistics if not available in webhook (for backwards compatibility)
            // Statistics are now extracted directly from match_state webhook above
            if (! isset($matchData['stats'])) {
                $this->updatePlayerStatistics($match);
            }
        });

        Log::debug('match_state processed', [
            'matchId' => $payload['matchId'],
            'finished' => $matchData['finished'] ?? false,
        ]);
    }

    protected function processTurnsFromMatchState(DartMatch $match, array $turns, array $matchData): void
    {
        foreach ($turns as $turnData) {
            $player = Player::where('autodarts_user_id', $turnData['playerId'])->first();
            if (! $player) {
                continue;
            }

            // Check if turn already exists - if so, use its existing leg
            $existingTurn = Turn::where('autodarts_turn_id', $turnData['id'])->first();

            if ($existingTurn) {
                // Turn already exists - update busted flag and other fields, but keep existing leg
                $leg = $existingTurn->leg;

                // Parse timestamps, treating invalid dates as null
                $startedAt = $this->parseTimestamp($turnData['createdAt'] ?? null);
                $finishedAt = $this->parseTimestamp($turnData['finishedAt'] ?? null);

                // Update fields that may have changed
                $updateData = [
                    'score_after' => $turnData['score'] ?? $existingTurn->score_after,
                    'points' => $turnData['points'] ?? $existingTurn->points,
                ];

                if ($startedAt) {
                    $updateData['started_at'] = $startedAt;
                }

                if ($finishedAt) {
                    $updateData['finished_at'] = $finishedAt;
                }

                // Always update busted flag if provided (it may change after turn is created)
                if (isset($turnData['busted'])) {
                    $updateData['busted'] = (bool) $turnData['busted'];
                }

                $existingTurn->update($updateData);
                $turn = $existingTurn;
            } else {
                // Turn doesn't exist - create it for the current leg
                $currentLegNumber = $matchData['leg'] ?? 1;

                $leg = Leg::where('match_id', $match->id)
                    ->where('leg_number', $currentLegNumber)
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

                // Ensure busted flag is always updated from match_state (it may change after turn is created)
                if (isset($turnData['busted'])) {
                    $busted = (bool) $turnData['busted'];
                    if ($turn->busted !== $busted) {
                        $turn->update(['busted' => $busted]);
                    }
                }
            }

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

            // Create new throw with retry logic to handle race conditions
            $newThrow = $this->createThrowWithRetry($turn, $throwId, $throwData, $dartNumber);

            // Link correction
            if ($existingThrowAtPosition && $newThrow) {
                $existingThrowAtPosition->update([
                    'corrected_by_throw_id' => $newThrow->id,
                ]);
            }
        }
    }

    protected function createThrowWithRetry(Turn $turn, string $throwId, array $throwData, int $dartNumber, int $maxAttempts = 3): ?DartThrow
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            // First, check if throw already exists (might have been created by another process)
            $existing = DartThrow::where('autodarts_throw_id', $throwId)->first();
            if ($existing) {
                return $existing;
            }

            // Try to create the throw
            try {
                return DartThrow::create([
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
            } catch (DeadlockException|QueryException|\PDOException $e) {
                $attempts++;

                $errorMessage = $e->getMessage();
                $errorCode = $e->getCode();

                $isDeadlockError = $e instanceof DeadlockException
                    || $errorCode === 'HY000'
                    || $errorCode === 0
                    || str_contains($errorMessage, '1020')
                    || str_contains($errorMessage, 'Record has changed since last read')
                    || str_contains($errorMessage, 'Deadlock');

                if ($isDeadlockError) {
                    // Wait a bit for the other transaction to complete
                    usleep(10000 + ($attempts * 5000)); // 10ms, 15ms, 20ms

                    // Check again if throw exists now
                    $existing = DartThrow::where('autodarts_throw_id', $throwId)->first();
                    if ($existing) {
                        return $existing;
                    }

                    // If last attempt, wait a bit longer
                    if ($attempts >= $maxAttempts) {
                        usleep(50000); // 50ms
                        $finalCheck = DartThrow::where('autodarts_throw_id', $throwId)->first();
                        if ($finalCheck) {
                            return $finalCheck;
                        }
                    }

                    continue;
                }

                // If it's not a retryable error, rethrow
                throw $e;
            }
        }

        // Final fallback: try to find the throw (it might exist now)
        return DartThrow::where('autodarts_throw_id', $throwId)->first();
    }

    protected function syncMatchPlayerWithRetry(DartMatch $match, int $playerId, array $pivotData, int $maxAttempts = 3): void
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            try {
                // Check if player is already attached to this match
                $existingPivot = DB::table('match_player')
                    ->where('match_id', $match->id)
                    ->where('player_id', $playerId)
                    ->first();

                if ($existingPivot) {
                    // Update existing pivot record
                    DB::table('match_player')
                        ->where('match_id', $match->id)
                        ->where('player_id', $playerId)
                        ->update(array_merge($pivotData, ['updated_at' => now()]));
                } else {
                    // Use syncWithoutDetaching to create/update
                    $match->players()->syncWithoutDetaching([
                        $playerId => $pivotData,
                    ]);
                }

                return;
            } catch (DeadlockException|QueryException|\PDOException $e) {
                $attempts++;

                $errorMessage = $e->getMessage();
                $errorCode = $e->getCode();

                $isDeadlockError = $e instanceof DeadlockException
                    || $errorCode === 'HY000'
                    || $errorCode === 0
                    || str_contains($errorMessage, '1020')
                    || str_contains($errorMessage, 'Record has changed since last read')
                    || str_contains($errorMessage, 'Deadlock');

                if ($isDeadlockError && $attempts < $maxAttempts) {
                    // Wait a bit before retrying (exponential backoff with jitter)
                    $delay = (50000 * $attempts) + random_int(0, 10000); // 50ms, 100ms + jitter
                    usleep($delay);

                    continue;
                }

                // If it's not a retryable error or we've exhausted attempts, rethrow
                throw $e;
            }
        }

        // Final fallback: try direct update using DB facade
        try {
            DB::table('match_player')
                ->where('match_id', $match->id)
                ->where('player_id', $playerId)
                ->update(array_merge($pivotData, ['updated_at' => now()]));
        } catch (\Exception $e) {
            // Log but don't throw - the record might have been updated by another process
            Log::warning('Failed to sync match_player after retries', [
                'match_id' => $match->id,
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
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

    protected function updateLegs(DartMatch $match, array $matchData): void
    {
        // Get all legs for this match
        $legs = $match->legs;

        // Get current leg and set from webhook
        $currentLegNumber = $matchData['leg'] ?? null;
        $currentSetNumber = $matchData['set'] ?? 1;

        // Get legs_won statistics if available (to help determine winners for legs without score_after = 0)
        $legsWonByPlayer = [];
        if (isset($matchData['scores'])) {
            foreach ($matchData['scores'] as $index => $score) {
                $player = $match->players->firstWhere('pivot.player_index', $index);
                if ($player) {
                    $legsWonByPlayer[$player->id] = $score['legs'] ?? 0;
                }
            }
        }

        foreach ($legs as $leg) {
            // Find winner by looking for turns with score_after = 0 (successful checkout)
            // First try with finished_at, then without (as fallback)
            $winningTurn = Turn::query()
                ->where('leg_id', $leg->id)
                ->where('score_after', 0)
                ->whereNotNull('finished_at')
                ->orderBy('finished_at', 'desc')
                ->first();

            // If no turn with finished_at, try without (some turns might not have finished_at set)
            if (! $winningTurn) {
                $winningTurn = Turn::query()
                    ->where('leg_id', $leg->id)
                    ->where('score_after', 0)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            // If still no winner, try to find the turn with the lowest score_after (closest to 0)
            // This handles cases where the winning turn might have been corrected or not saved properly
            if (! $winningTurn) {
                $lowestScoreTurn = Turn::query()
                    ->where('leg_id', $leg->id)
                    ->whereNotNull('score_after')
                    ->orderBy('score_after', 'asc')
                    ->orderBy('id', 'desc')
                    ->first();

                // If the lowest score is 0 or very low (e.g., checkout situation), use it
                if ($lowestScoreTurn && $lowestScoreTurn->score_after === 0) {
                    $winningTurn = $lowestScoreTurn;
                }
            }

            // Verify winner against legs_won statistics if match is finished
            // This prevents incorrectly assigning legs when turns have score_after = 0 but the player didn't actually win
            if ($winningTurn && ($matchData['finished'] ?? false) && ! empty($legsWonByPlayer)) {
                $expectedWins = $legsWonByPlayer[$winningTurn->player_id] ?? 0;
                $currentWins = $legs->where('winner_player_id', $winningTurn->player_id)->count();

                // If this player already has enough wins, this leg might belong to another player
                if ($currentWins >= $expectedWins) {
                    // Find the player who still needs wins
                    foreach ($match->players as $player) {
                        $playerExpectedWins = $legsWonByPlayer[$player->id] ?? 0;
                        $playerCurrentWins = $legs->where('winner_player_id', $player->id)->count();

                        if ($playerCurrentWins < $playerExpectedWins) {
                            // This player needs more wins - check if they have a turn in this leg
                            $playerTurn = Turn::query()
                                ->where('leg_id', $leg->id)
                                ->where('player_id', $player->id)
                                ->orderBy('id', 'desc')
                                ->first();

                            if ($playerTurn) {
                                $winningTurn = $playerTurn;
                                break;
                            }
                        }
                    }
                }
            }

            // If still no winner found and match is finished, try to determine winner from last turn
            // The player who has the last turn in a finished leg likely won it
            if (! $winningTurn && ($matchData['finished'] ?? false)) {
                $lastTurn = Turn::query()
                    ->where('leg_id', $leg->id)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastTurn) {
                    // Check if this player has won more legs than others, suggesting they won this one
                    $playerWins = $legsWonByPlayer[$lastTurn->player_id] ?? 0;
                    $otherPlayers = $match->players->where('id', '!=', $lastTurn->player_id);
                    $hasMoreWins = true;

                    foreach ($otherPlayers as $otherPlayer) {
                        $otherWins = $legsWonByPlayer[$otherPlayer->id] ?? 0;
                        if ($otherWins >= $playerWins) {
                            $hasMoreWins = false;
                            break;
                        }
                    }

                    // If this player has significantly more wins and this leg has no other winner,
                    // it's likely they won this leg too
                    if ($hasMoreWins && $playerWins > 0) {
                        $winningTurn = $lastTurn;
                    }
                }
            }

            $updateData = [];

            if ($winningTurn) {
                // Leg has a winner
                $updateData['winner_player_id'] = $winningTurn->player_id;
                // Use finished_at if available, otherwise use started_at or now
                $updateData['finished_at'] = $winningTurn->finished_at ?? $winningTurn->started_at ?? now();
            }

            // Set started_at from first turn if not set
            if (! $leg->started_at) {
                $firstTurn = Turn::query()
                    ->where('leg_id', $leg->id)
                    ->whereNotNull('started_at')
                    ->orderBy('started_at', 'asc')
                    ->first();

                if ($firstTurn) {
                    $updateData['started_at'] = $firstTurn->started_at;
                }
            }

            // Update leg if we have changes
            if (! empty($updateData)) {
                $leg->update($updateData);
            }

            // Save leg statistics if this is the current leg and stats are available
            if ($leg->leg_number === $currentLegNumber
                && $leg->set_number === $currentSetNumber
                && isset($matchData['stats'])
            ) {
                $this->saveLegStatistics($leg, $matchData['stats']);
            } elseif ($leg->finished_at) {
                // If leg is finished but no stats from webhook, calculate from turns
                \App\Support\LegStatisticsCalculator::calculateAndUpdate($leg);
            }
        }
    }

    protected function saveLegStatistics(Leg $leg, array $stats): void
    {
        foreach ($stats as $index => $playerStats) {
            $legStats = $playerStats['legStats'] ?? null;
            if (! $legStats) {
                continue;
            }

            // Get player by index from match
            $match = $leg->match;
            $players = $match->players;
            $player = $players->firstWhere('pivot.player_index', $index);

            if (! $player) {
                continue;
            }

            $average = isset($legStats['average']) ? round((float) $legStats['average'], 2) : null;
            $checkoutRate = isset($legStats['checkoutPercent']) ? round((float) $legStats['checkoutPercent'], 4) : null;
            $dartsThrown = isset($legStats['dartsThrown']) ? (int) $legStats['dartsThrown'] : null;
            $checkoutAttempts = isset($legStats['checkouts']) ? (int) $legStats['checkouts'] : null;
            $checkoutHits = isset($legStats['checkoutsHit']) ? (int) $legStats['checkoutsHit'] : null;

            // Update or create leg_player record
            DB::table('leg_player')->updateOrInsert(
                [
                    'leg_id' => $leg->id,
                    'player_id' => $player->id,
                ],
                [
                    'average' => $average,
                    'checkout_rate' => $checkoutRate,
                    'darts_thrown' => $dartsThrown,
                    'checkout_attempts' => $checkoutAttempts,
                    'checkout_hits' => $checkoutHits,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    protected function updateFinalPositions(DartMatch $match, array $matchData): void
    {
        $winnerIndex = $matchData['winner'] ?? $matchData['gameWinner'] ?? null;
        $players = $match->players;

        if ($winnerIndex !== null) {
            // Simple case: winner index is provided
            // Winner gets position 1, others get position 2
            foreach ($players as $player) {
                $position = ($player->pivot->player_index === (int) $winnerIndex) ? 1 : 2;
                $match->players()->updateExistingPivot($player->id, [
                    'final_position' => $position,
                ]);
            }
        } elseif (isset($matchData['scores']) && is_array($matchData['scores'])) {
            // Calculate positions based on scores (Sets first, then Legs)
            $playerScores = [];
            foreach ($players as $player) {
                $index = $player->pivot->player_index;
                if (isset($matchData['scores'][$index])) {
                    $playerScores[] = [
                        'player_id' => $player->id,
                        'sets' => $matchData['scores'][$index]['sets'] ?? 0,
                        'legs' => $matchData['scores'][$index]['legs'] ?? 0,
                    ];
                }
            }

            // Sort by sets (descending), then by legs (descending)
            usort($playerScores, function ($a, $b) {
                if ($a['sets'] !== $b['sets']) {
                    return $b['sets'] <=> $a['sets'];
                }

                return $b['legs'] <=> $a['legs'];
            });

            // Update positions based on sorted order
            foreach ($playerScores as $position => $playerScore) {
                $match->players()->updateExistingPivot($playerScore['player_id'], [
                    'final_position' => $position + 1,
                ]);
            }
        }
    }

    protected function deriveWinnerFromFinalPosition(DartMatch $match): void
    {
        // Only derive winner if match is finished and winner is not already set
        if (! $match->finished_at || $match->winner_player_id !== null) {
            return;
        }

        // Reload players with pivot data to ensure we have the latest data
        $match->load('players');
        $players = $match->players;

        // First, try to find winner based on legs_won and sets_won (most reliable)
        $playerScores = $players->map(function ($player) {
            return [
                'player' => $player,
                'sets' => $player->pivot->sets_won ?? 0,
                'legs' => $player->pivot->legs_won ?? 0,
            ];
        })->sortByDesc(function ($data) {
            // Sort by sets first, then by legs
            return [$data['sets'], $data['legs']];
        })->values();

        // Check if there's a clear winner (more sets or more legs)
        if ($playerScores->count() >= 2) {
            $first = $playerScores->first();
            $second = $playerScores->get(1);

            // Winner has more sets, or same sets but more legs
            if ($first['sets'] > $second['sets'] || 
                ($first['sets'] === $second['sets'] && $first['legs'] > $second['legs'])) {
                
                $winner = $first['player'];
                
                // Update final positions based on actual scores
                foreach ($playerScores as $index => $data) {
                    $match->players()->updateExistingPivot($data['player']->id, [
                        'final_position' => $index + 1,
                    ]);
                }
                
                $match->update(['winner_player_id' => $winner->id]);
                return;
            }
        }

        // Fallback: use final_position = 1 if set
        $winner = $players->firstWhere('pivot.final_position', 1);
        
        if ($winner) {
            $match->update(['winner_player_id' => $winner->id]);
        }
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

    protected function updatePlayerStatistics(DartMatch $match): void
    {
        \App\Support\MatchStatisticsCalculator::calculateAndUpdate($match);
    }
}
