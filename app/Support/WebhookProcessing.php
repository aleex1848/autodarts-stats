<?php

namespace App\Support;

use App\Events\MatchdayGameStarted;
use App\Events\MatchUpdated;
use App\Events\PlayerIdentified;
use App\Models\BullOff;
use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\MatchdayFixture;
use App\Models\Player;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
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

        // Prüfe ob bereits ein match_state Event für dieses Match existiert
        // Wenn ja, ignorieren wir throw Events, da alle Daten bereits aus match_state kommen
        if ($this->matchStateEventExists($payload['matchId'])) {
            Log::debug('throw event ignored - match_state event exists', [
                'matchId' => $payload['matchId'],
                'player' => $data['playerName'],
            ]);

            return;
        }

        DB::transaction(function () use ($payload, $data) {
            // Get or create match first to access player mapping
            $match = $this->firstOrCreateWithRetry(
                DartMatch::class,
                ['autodarts_match_id' => $payload['matchId']],
                [
                    'variant' => 'X01',
                    'type' => 'Online',
                    'started_at' => now(),
                ]
            );

            // Find userId from playerId (spiel-spezifische ID) by looking up in match_state webhook
            $userId = $this->findUserIdFromPlayerId($payload['matchId'], $data['playerId'], $data['playerName']);

            // Get or create player with deadlock retry using the unique userId
            $player = $this->findOrCreatePlayer(
                $userId,
                $data['playerName'],
                []
            );

            // Check if this is a Bull-Off throw (negative score in round 1, leg 1, set 1, before any normal turns)
            $isBullOff = $this->isBullOffThrow($match, $data);

            if ($isBullOff) {
                // Check if this turn was already created as a regular Turn (should be converted to Bull-Off)
                $existingTurn = Turn::where('autodarts_turn_id', $data['turnId'])->first();
                if ($existingTurn) {
                    // Delete the existing Turn - it should be a Bull-Off instead
                    $existingTurn->delete();
                    Log::debug('Converted existing Turn to Bull-Off', [
                        'matchId' => $payload['matchId'],
                        'turnId' => $data['turnId'],
                        'player' => $data['playerName'],
                    ]);
                }

                // Store as Bull-Off instead of a Turn
                $this->firstOrCreateWithRetry(
                    BullOff::class,
                    ['autodarts_turn_id' => $data['turnId']],
                    [
                        'match_id' => $match->id,
                        'player_id' => $player->id,
                        'score' => $data['score'],
                        'thrown_at' => $data['throw']['createdAt'] ?? now(),
                    ]
                );

                Log::debug('bull-off throw processed', [
                    'matchId' => $payload['matchId'],
                    'player' => $data['playerName'],
                    'score' => $data['score'],
                ]);

                return;
            }

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

            // Reset incomplete status if match was marked as incomplete but is now continuing
            // Refresh match to get latest state
            $match->refresh();
            if ($match->is_incomplete === true && $match->finished_at === null) {
                $match->update(['is_incomplete' => false]);
                Log::info('Match resumed from incomplete status', [
                    'matchId' => $payload['matchId'],
                    'match_id' => $match->id,
                    'reason' => 'New throw event received',
                ]);
            }

            // Broadcast MatchUpdated event after processing throw
            broadcast(new MatchUpdated($match->fresh()));
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
        $match = null;

        DB::transaction(function () use ($payload, $matchData, &$match) {
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
                $averageUntil170 = null;
                $first9Average = null;
                $checkoutRate = null;
                $checkoutAttempts = null;
                $checkoutHits = null;
                $bestCheckoutPoints = null;
                $total180s = null;
                $dartsThrown = null;

                if ($matchStats) {
                    // Average is already calculated by Autodarts
                    $matchAverage = isset($matchStats['average']) ? round((float) $matchStats['average'], 2) : null;

                    // Average until 170
                    $averageUntil170 = isset($matchStats['averageUntil170']) ? round((float) $matchStats['averageUntil170'], 2) : null;

                    // First 9 Average
                    $first9Average = isset($matchStats['first9Average']) ? round((float) $matchStats['first9Average'], 2) : null;

                    // Checkout rate is already calculated by Autodarts (as decimal between 0 and 1)
                    $checkoutRate = isset($matchStats['checkoutPercent']) ? round((float) $matchStats['checkoutPercent'], 4) : null;

                    // Checkout attempts and hits
                    $checkoutAttempts = isset($matchStats['checkouts']) ? (int) $matchStats['checkouts'] : null;
                    $checkoutHits = isset($matchStats['checkoutsHit']) ? (int) $matchStats['checkoutsHit'] : null;

                    // Best checkout points
                    $bestCheckoutPoints = isset($matchStats['checkoutPoints']) ? (int) $matchStats['checkoutPoints'] : null;

                    // Total 180s
                    $total180s = isset($matchStats['total180']) ? (int) $matchStats['total180'] : null;

                    // Total darts thrown
                    $dartsThrown = isset($matchStats['dartsThrown']) ? (int) $matchStats['dartsThrown'] : null;
                }

                // Cricket-specific statistics
                $mpr = null;
                $first9MPR = null;
                if (($payload['variant'] ?? $match->variant) === 'Cricket' && $matchStats) {
                    // MPR (Marks Per Round) for Cricket
                    $mpr = isset($matchStats['mpr']) ? round((float) $matchStats['mpr'], 2) : null;

                    // First 9 MPR for Cricket
                    $first9MPR = isset($matchStats['first9MPR']) ? round((float) $matchStats['first9MPR'], 2) : null;
                }

                // Sync pivot table with retry logic to handle race conditions
                $this->syncMatchPlayerWithRetry($match, $player->id, [
                    'player_index' => $index,
                    'legs_won' => $matchData['scores'][$index]['legs'] ?? 0,
                    'sets_won' => $matchData['scores'][$index]['sets'] ?? 0,
                    'match_average' => $matchAverage,
                    'average_until_170' => $averageUntil170,
                    'first_9_average' => $first9Average,
                    'checkout_rate' => $checkoutRate,
                    'checkout_attempts' => $checkoutAttempts,
                    'checkout_hits' => $checkoutHits,
                    'best_checkout_points' => $bestCheckoutPoints,
                    'total_180s' => $total180s ?? 0,
                    'darts_thrown' => $dartsThrown,
                    'mpr' => $mpr,
                    'first_9_mpr' => $first9MPR,
                ]);
            }

            // Update match status (after players are synced)
            if ($matchData['finished'] ?? false) {
                // Refresh match to avoid race conditions with concurrent webhook processing
                $match->refresh();

                // Only update if not already finished (to avoid race condition issues)
                if ($match->finished_at === null) {
                    // Calculate started_at and finished_at from turn timestamps
                    $firstTurn = Turn::query()
                        ->select('turns.*')
                        ->join('legs', 'turns.leg_id', '=', 'legs.id')
                        ->where('legs.match_id', $match->id)
                        ->whereNotNull('turns.started_at')
                        ->orderBy('turns.started_at', 'asc')
                        ->first();

                    $lastTurn = Turn::query()
                        ->select('turns.*')
                        ->join('legs', 'turns.leg_id', '=', 'legs.id')
                        ->where('legs.match_id', $match->id)
                        ->whereNotNull('turns.started_at')
                        ->orderBy('turns.started_at', 'desc')
                        ->first();

                    $updateData = [
                        'started_at' => $firstTurn?->started_at ?? $match->started_at ?? now(),
                        'finished_at' => $lastTurn?->started_at ?? now(),
                    ];

                    // Try to find winner if winner index is provided
                    // Prefer gameWinner over winner as it's more reliable (array index vs player index confusion)
                    $winnerIndex = $matchData['gameWinner'] ?? $matchData['winner'] ?? null;
                    if ($winnerIndex !== null && isset($matchData['players'][$winnerIndex])) {
                        $winnerPlayerId = $matchData['players'][$winnerIndex]['userId'] ?? null;
                        if ($winnerPlayerId) {
                            $winner = Player::where('autodarts_user_id', $winnerPlayerId)->first();
                            if ($winner) {
                                $updateData['winner_player_id'] = $winner->id;
                            }
                        } else {
                            // Winner has no userId (Bot) - find by player_index
                            $match->load('players');
                            foreach ($match->players as $player) {
                                if ($player->pivot->player_index === (int) $winnerIndex) {
                                    $updateData['winner_player_id'] = $player->id;
                                    break;
                                }
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
            }

            // Reset incomplete status if match was marked as incomplete but is now continuing
            // Refresh match to get latest state after all updates
            $match->refresh();
            $isFinished = $matchData['finished'] ?? false;
            if ($match->is_incomplete === true && ! $isFinished && $match->finished_at === null) {
                // A match_state event indicates the match is active, reset incomplete status
                $match->update(['is_incomplete' => false]);
                Log::info('Match resumed from incomplete status', [
                    'matchId' => $payload['matchId'],
                    'match_id' => $match->id,
                    'reason' => 'New match_state event received',
                ]);
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

            // Broadcast MatchUpdated event after processing match_state
            broadcast(new MatchUpdated($match->fresh()));
        });

        // Check for player identification (outside transaction to ensure broadcasts work correctly)
        if ($match) {
            $this->handlePlayerIdentification($match, $matchData);

            // Check for matchday assignment (outside transaction to ensure broadcasts work correctly)
            $this->handleMatchdayAssignment($match, $matchData);
        }

        Log::debug('match_state processed', [
            'matchId' => $payload['matchId'],
            'finished' => $matchData['finished'] ?? false,
        ]);
    }

    protected function handlePlayerIdentification(DartMatch $match, array $matchData): void
    {
        // Try to identify the user from the webhook request
        $user = $this->getUserFromWebhook();

        // If we can't identify the user from the webhook, fall back to finding all users in identifying mode
        if (! $user) {
            $identifyingUsers = User::where('is_identifying', true)->get();

            if ($identifyingUsers->isEmpty()) {
                return;
            }

            $user = $identifyingUsers->first();
        } else {
            // Check if this user is in identifying mode
            if (! $user->is_identifying) {
                return;
            }
        }

        // Check if user has provided an autodarts name
        if (empty($user->autodarts_name)) {
            $user->update(['is_identifying' => false]);
            broadcast(new PlayerIdentified(
                $user,
                null,
                false,
                'Bitte gib zuerst deinen AutoDarts-Namen in den Einstellungen ein.'
            ));

            return;
        }

        // Find the player that matches the user's autodarts name
        $nonBotPlayer = null;
        // Normalize: trim and replace multiple spaces with single space
        $expectedName = preg_replace('/\s+/', ' ', trim($user->autodarts_name));
        
        foreach ($matchData['players'] as $playerData) {
            // Normalize: trim and replace multiple spaces with single space
            $playerName = preg_replace('/\s+/', ' ', trim($playerData['name'] ?? ''));
            $userId = $playerData['userId'] ?? null;

            // Skip bots (name starts with "Bot Level" or no userId)
            if (str_starts_with($playerName, 'Bot Level') || ! $userId) {
                continue;
            }

            // Check if this is a bot UUID (starts with 00000000)
            if (str_starts_with($userId, '00000000-0000-0000')) {
                continue;
            }

            // Check if the player name matches the expected name (case-insensitive)
            if (strcasecmp($playerName, $expectedName) !== 0) {
                continue;
            }

            // Find the player in our database
            $player = Player::where('autodarts_user_id', $userId)->first();
            if ($player) {
                $nonBotPlayer = $player;
                break;
            }
        }

        if (! $nonBotPlayer) {
            // No suitable player found - deactivate identifying mode
            $user->update(['is_identifying' => false]);
            broadcast(new PlayerIdentified(
                $user,
                null,
                false,
                "Kein Spieler mit dem Namen '{$expectedName}' gefunden. Bitte stelle sicher, dass du gegen einen Bot spielst und dein AutoDarts-Name korrekt eingegeben wurde."
            ));

            return;
        }

        // Check if player is already linked to another user
        if ($nonBotPlayer->user_id !== null && $nonBotPlayer->user_id !== $user->id) {
            $user->update(['is_identifying' => false]);
            broadcast(new PlayerIdentified(
                $user,
                null,
                false,
                'Dieser Spieler ist bereits mit einem anderen Account verknüpft.'
            ));

            return;
        }

        // Link player to user
        $nonBotPlayer->update(['user_id' => $user->id]);
        $user->update(['is_identifying' => false]);

        // Broadcast success event
        broadcast(new PlayerIdentified(
            $user,
            $nonBotPlayer,
            true,
            "Spieler '{$nonBotPlayer->name}' wurde erfolgreich mit deinem Account verknüpft."
        ));

        Log::info('Player identified', [
            'user_id' => $user->id,
            'player_id' => $nonBotPlayer->id,
            'player_name' => $nonBotPlayer->name,
        ]);
    }

    /**
     * Handle matchday assignment when a user is playing a matchday game.
     * Links the incoming match to the appropriate fixture and resets the playing_matchday_id.
     */
    protected function handleMatchdayAssignment(DartMatch $match, array $matchData): void
    {
        // Try to identify the user from the webhook request
        $user = $this->getUserFromWebhook();

        // If we can't identify the user from the webhook, fall back to finding users in playing mode
        if (! $user) {
            $playingUsers = User::whereNotNull('playing_matchday_id')->get();

            if ($playingUsers->isEmpty()) {
                return;
            }

            // Try to match by player name in the match
            $user = null;
            foreach ($playingUsers as $playingUser) {
                if (! $playingUser->player) {
                    continue;
                }

                // Check if any player in the match matches the user's player
                foreach ($matchData['players'] as $playerData) {
                    $playerName = preg_replace('/\s+/', ' ', trim($playerData['name'] ?? ''));
                    $userId = $playerData['userId'] ?? null;

                    // Skip bots
                    if (str_starts_with($playerName, 'Bot Level') || ! $userId) {
                        continue;
                    }

                    if (str_starts_with($userId, '00000000-0000-0000')) {
                        continue;
                    }

                    // Check if this player matches the user's player
                    if ($playingUser->player->autodarts_user_id === $userId) {
                        $user = $playingUser;
                        break 2;
                    }

                    // Also check by name (case-insensitive)
                    if (strcasecmp($playerName, $playingUser->player->name) === 0) {
                        $user = $playingUser;
                        break 2;
                    }
                }
            }

            if (! $user) {
                return;
            }
        } else {
            // Check if this user is in playing mode
            if (! $user->playing_matchday_id) {
                return;
            }
        }

        // Get the matchday
        $matchday = $user->playingMatchday;
        if (! $matchday) {
            Log::warning('User has playing_matchday_id but matchday not found', [
                'user_id' => $user->id,
                'playing_matchday_id' => $user->playing_matchday_id,
            ]);
            $user->update(['playing_matchday_id' => null]);

            return;
        }

        // Find the user's player
        if (! $user->player) {
            Log::warning('User in playing mode but has no player', [
                'user_id' => $user->id,
            ]);
            $user->update(['playing_matchday_id' => null]);

            return;
        }

        // Find the fixture for this matchday that involves the user's player
        $fixture = MatchdayFixture::where('matchday_id', $matchday->id)
            ->where(function ($query) use ($user) {
                $query->where('home_player_id', $user->player->id)
                    ->orWhere('away_player_id', $user->player->id);
            })
            ->whereNull('dart_match_id')
            ->where('status', 'scheduled')
            ->first();

        if (! $fixture) {
            Log::info('No matching fixture found for matchday assignment', [
                'user_id' => $user->id,
                'matchday_id' => $matchday->id,
                'player_id' => $user->player->id,
            ]);
            $user->update(['playing_matchday_id' => null]);
            broadcast(new MatchdayGameStarted(
                $user,
                $matchday,
                $match,
                false,
                'Kein passendes Spiel für diesen Spieltag gefunden.'
            ));

            return;
        }

        // Link the match to the fixture
        // If match is finished, use AssignMatchToFixture to properly calculate legs, winner, and points
        if ($match->finished_at !== null) {
            try {
                app(\App\Actions\AssignMatchToFixture::class)->handle($match, $fixture);
            } catch (\Exception $e) {
                Log::warning('Failed to assign match to fixture using AssignMatchToFixture', [
                    'user_id' => $user->id,
                    'matchday_id' => $matchday->id,
                    'fixture_id' => $fixture->id,
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);

                // Fallback: just link the match without calculating stats
                $fixture->update([
                    'dart_match_id' => $match->id,
                    'status' => 'completed',
                    'played_at' => $match->started_at ?? now(),
                ]);
            }
        } else {
            // Match is not finished yet, just link it
            // The fixture will be updated when the match finishes
            $fixture->update([
                'dart_match_id' => $match->id,
                'played_at' => $match->started_at ?? now(),
            ]);
        }

        // Reset playing_matchday_id
        $user->update(['playing_matchday_id' => null]);

        // Broadcast success event
        broadcast(new MatchdayGameStarted(
            $user,
            $matchday,
            $match,
            true,
            "Spiel wurde erfolgreich Spieltag {$matchday->matchday_number} zugeordnet."
        ));

        Log::info('Matchday game assigned', [
            'user_id' => $user->id,
            'matchday_id' => $matchday->id,
            'fixture_id' => $fixture->id,
            'match_id' => $match->id,
            'match_finished' => $match->finished_at !== null,
        ]);
    }

    /**
     * Try to get the user from the webhook request by extracting the Sanctum token from headers.
     */
    protected function getUserFromWebhook(): ?User
    {
        try {
            $headers = $this->webhookCall->headers ?? [];

            // Headers might be stored as JSON string or array
            if (is_string($headers)) {
                $headers = json_decode($headers, true);
            }

            if (! is_array($headers)) {
                return null;
            }

            // Look for Authorization header
            $authorization = null;
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization' || strtolower($key) === 'authorization-header') {
                    $authorization = is_array($value) ? ($value[0] ?? null) : $value;
                    break;
                }
            }

            if (! $authorization) {
                return null;
            }

            // Extract token from "Bearer {token}" format
            if (preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
                $token = $matches[1];
            } else {
                $token = $authorization;
            }

            // Find the token in the database
            $accessToken = PersonalAccessToken::findToken($token);

            if (! $accessToken) {
                return null;
            }

            return $accessToken->tokenable instanceof User ? $accessToken->tokenable : null;
        } catch (\Exception $e) {
            Log::debug('Failed to get user from webhook', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function processTurnsFromMatchState(DartMatch $match, array $turns, array $matchData): void
    {
        // Build a mapping from playerId (spiel-spezifische ID) to userId (eindeutige ID)
        $playerIdToUserIdMap = [];
        foreach ($matchData['players'] as $playerData) {
            if (isset($playerData['id']) && isset($playerData['userId'])) {
                $playerIdToUserIdMap[$playerData['id']] = $playerData['userId'];
            }
        }

        foreach ($turns as $turnData) {
            // Get userId from playerId using the mapping
            $playerId = $turnData['playerId'] ?? null;
            if (! $playerId) {
                continue;
            }

            // Find player data from matchData to get name and userId
            $playerDataFromMatch = null;
            $playerName = null;
            foreach ($matchData['players'] as $playerData) {
                if (($playerData['id'] ?? null) === $playerId) {
                    $playerDataFromMatch = $playerData;
                    $playerName = $playerData['name'] ?? null;
                    break;
                }
            }

            // Get userId from mapping or generate for bots
            $userId = $playerIdToUserIdMap[$playerId] ?? null;
            if (! $userId) {
                // Bot or guest without userId - generate deterministic UUID based on name
                if ($playerName) {
                    $userId = $this->generateBotUuid($playerName);
                } else {
                    // Fallback: try to find player by playerId (for backwards compatibility)
                    $existingPlayer = Player::where('autodarts_user_id', $playerId)->first();
                    if ($existingPlayer) {
                        $userId = $existingPlayer->autodarts_user_id;
                    } else {
                        // If we can't find the player and don't have a name, skip this turn
                        continue;
                    }
                }
            }

            // Find or create player by the unique userId
            $player = $this->findOrCreatePlayer(
                $userId,
                $playerName ?? 'Unknown Player',
                $playerDataFromMatch ? [
                    'email' => $playerDataFromMatch['user']['userSettings']['email'] ?? null,
                    'country' => $playerDataFromMatch['user']['country'] ?? null,
                    'avatar_url' => $playerDataFromMatch['avatarUrl'] ?? null,
                ] : []
            );

            // Check if this is a Bull-Off turn (only if throws are present, otherwise check after throws are added)
            $score = $turnData['score'] ?? null;
            $throws = $turnData['throws'] ?? [];
            $isBullOff = ! empty($throws) && $this->isBullOffTurnFromMatchState($match, $turnData, $matchData);

            if ($isBullOff) {
                // Check if this turn was already created as a regular Turn (should be converted to Bull-Off)
                $existingTurn = Turn::where('autodarts_turn_id', $turnData['id'])->first();
                if ($existingTurn) {
                    // Delete the existing Turn - it should be a Bull-Off instead
                    $existingTurn->delete();
                    Log::debug('Converted existing Turn to Bull-Off', [
                        'matchId' => $match->autodarts_match_id,
                        'turnId' => $turnData['id'],
                        'player' => $player->name,
                    ]);
                }

                // Store as Bull-Off instead of a Turn
                $this->firstOrCreateWithRetry(
                    BullOff::class,
                    ['autodarts_turn_id' => $turnData['id']],
                    [
                        'match_id' => $match->id,
                        'player_id' => $player->id,
                        'score' => $score,
                        'thrown_at' => $this->parseTimestamp($turnData['createdAt'] ?? null) ?? now(),
                    ]
                );

                // Process throws for Bull-Off (if needed for display)
                if (isset($turnData['throws']) && is_array($turnData['throws'])) {
                    // Store throws in a separate table or skip them for Bull-Off
                    // For now, we'll skip storing throws for Bull-Off as they're not part of the game
                }

                continue;
            }

            // Check if turn already exists - if so, use its existing leg
            $existingTurn = Turn::where('autodarts_turn_id', $turnData['id'])->first();

            if ($existingTurn) {
                // Turn already exists - update busted flag and other fields, but keep existing leg
                // Refresh to avoid race conditions with concurrent webhook processing
                $existingTurn->refresh();
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

                // Use try-catch to silently handle race condition errors
                try {
                    $existingTurn->update($updateData);
                } catch (\Illuminate\Database\QueryException $e) {
                    // If error 1020 (record changed), another webhook already updated it - that's fine
                    if ($e->getCode() !== 'HY000' || ! str_contains($e->getMessage(), '1020')) {
                        throw $e; // Re-throw if it's a different error
                    }
                    // Silently ignore race condition errors and refresh to get latest data
                    $existingTurn->refresh();
                }

                $turn = $existingTurn;
            } else {
                // Turn doesn't exist - use the current leg from matchData
                // IMPORTANT: Always use the leg from matchData, not from existing turns
                // because match_state events contain turns from the current leg only
                $currentLegNumber = $matchData['leg'] ?? 1;
                $currentSetNumber = $matchData['set'] ?? 1;

                // Find or create the leg for the current leg number
                $leg = Leg::where('match_id', $match->id)
                    ->where('leg_number', $currentLegNumber)
                    ->where('set_number', $currentSetNumber)
                    ->first();

                // If leg doesn't exist, create it
                if (! $leg) {
                    $leg = $this->firstOrCreateWithRetry(
                        Leg::class,
                        [
                            'match_id' => $match->id,
                            'leg_number' => $currentLegNumber,
                            'set_number' => $currentSetNumber,
                        ],
                        ['started_at' => $this->parseTimestamp($turnData['createdAt'] ?? null) ?? now()]
                    );
                }

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

            // After processing throws (or if throws are already present), check if this turn should be a Bull-Off
            // This check runs for both new and existing turns
            if (isset($turnData['throws']) && ! empty($turnData['throws'])) {
                $isBullOffAfterThrows = $this->isBullOffTurnFromMatchState($match, $turnData, $matchData);
                if ($isBullOffAfterThrows) {
                    // Check if this turn is already a Bull-Off
                    $existingBullOff = BullOff::where('autodarts_turn_id', $turnData['id'])->first();
                    if (! $existingBullOff) {
                        // Convert Turn to Bull-Off
                        // First, delete any throws associated with this turn
                        $turn->throws()->delete();
                        // Then delete the turn
                        $turn->delete();
                        // Create Bull-Off
                        $this->firstOrCreateWithRetry(
                            BullOff::class,
                            ['autodarts_turn_id' => $turnData['id']],
                            [
                                'match_id' => $match->id,
                                'player_id' => $player->id,
                                'score' => $turnData['score'] ?? null,
                                'thrown_at' => $this->parseTimestamp($turnData['createdAt'] ?? null) ?? now(),
                            ]
                        );
                        Log::debug('Converted Turn to Bull-Off after throws were added', [
                            'matchId' => $match->autodarts_match_id,
                            'turnId' => $turnData['id'],
                            'player' => $player->name,
                        ]);

                        continue; // Skip to next turn
                    }
                }
            }
        }
    }

    protected function syncThrowsForTurn(Turn $turn, array $throwsData): void
    {
        // Get existing throws for this turn
        $existingThrows = $turn->throws()->notCorrected()->get()->keyBy('autodarts_throw_id');

        foreach ($throwsData as $throwData) {
            $throwId = $throwData['id'];
            $dartNumber = $throwData['throw'];
            $segmentNumber = $throwData['segment']['number'] ?? null;
            $multiplier = $throwData['segment']['multiplier'] ?? 1;

            // Check if throw already exists with same ID
            $existingThrowWithSameId = $existingThrows->get($throwId);

            if ($existingThrowWithSameId) {
                // Check if the throw data has changed (correction detected)
                $hasChanged = $existingThrowWithSameId->segment_number !== $segmentNumber
                    || $existingThrowWithSameId->multiplier !== $multiplier;

                if ($hasChanged) {
                    // Mark old throw as corrected
                    $existingThrowWithSameId->update([
                        'is_corrected' => true,
                        'corrected_at' => now(),
                    ]);

                    // Create new throw with updated data
                    // Note: createThrowWithRetry will create a new throw since the old one is now marked as corrected
                    $newThrow = $this->createThrowWithRetry($turn, $throwId, $throwData, $dartNumber);

                    // Link correction
                    if ($newThrow && $newThrow->id !== $existingThrowWithSameId->id) {
                        $existingThrowWithSameId->update([
                            'corrected_by_throw_id' => $newThrow->id,
                        ]);
                    }
                }

                // If no change, continue to next throw
                continue;
            }

            // Check if this dart_number position already has a throw (different throw ID)
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
            // First, check if throw already exists and is not corrected (might have been created by another process)
            // Only return existing throw if it's not corrected, otherwise create a new one
            $existing = DartThrow::where('autodarts_throw_id', $throwId)
                ->where('is_corrected', false)
                ->first();
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
            // Detect if this is a bot player (name like "Bot Level X" or starts with "Bot")
            $isBot = preg_match('/^Bot\s+/i', $name);
            $newUuidIsBot = str_starts_with($userId, '00000000-0000-0000');
            $existingUuidIsBot = str_starts_with($playerByName->autodarts_user_id, '00000000-0000-0000');

            // Only log warning for real users (not bots), as bots get new IDs per game
            if (! $isBot && ! $newUuidIsBot && ! $existingUuidIsBot) {
                Log::info('Player found by name but different autodarts_user_id', [
                    'name' => $name,
                    'existing_user_id' => $playerByName->autodarts_user_id,
                    'new_user_id' => $userId,
                ]);
            }

            // For bots: prefer our deterministic bot UUID over Autodarts' random UUIDs
            if ($isBot && $newUuidIsBot && ! $existingUuidIsBot) {
                // Replace Autodarts' random bot UUID with our deterministic one
                try {
                    $playerByName->update(array_merge([
                        'autodarts_user_id' => $userId,
                        'name' => $name,
                    ], $additionalValues));

                    return $playerByName->fresh();
                } catch (UniqueConstraintViolationException|QueryException $e) {
                    // Another player already has this UUID, just update values
                    $playerByName->update(array_merge(['name' => $name], $additionalValues));

                    return $playerByName;
                }
            }

            // Only update if the new userId is more "valid" (not a bot UUID)
            // Bot UUIDs start with 00000000, real user IDs don't
            if ($newUuidIsBot) {
                // New userId is a bot UUID, keep the existing one (unless we're replacing it above)
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
            $averageUntil170 = isset($legStats['averageUntil170']) ? round((float) $legStats['averageUntil170'], 2) : null;
            $first9Average = isset($legStats['first9Average']) ? round((float) $legStats['first9Average'], 2) : null;
            $checkoutRate = isset($legStats['checkoutPercent']) ? round((float) $legStats['checkoutPercent'], 4) : null;
            $dartsThrown = isset($legStats['dartsThrown']) ? (int) $legStats['dartsThrown'] : null;
            $checkoutAttempts = isset($legStats['checkouts']) ? (int) $legStats['checkouts'] : null;
            $checkoutHits = isset($legStats['checkoutsHit']) ? (int) $legStats['checkoutsHit'] : null;
            $bestCheckoutPoints = isset($legStats['checkoutPoints']) ? (int) $legStats['checkoutPoints'] : null;

            // Cricket-specific statistics
            $mpr = null;
            $first9MPR = null;
            if ($match->variant === 'Cricket') {
                // MPR (Marks Per Round) for Cricket
                $mpr = isset($legStats['mpr']) ? round((float) $legStats['mpr'], 2) : null;

                // First 9 MPR for Cricket
                $first9MPR = isset($legStats['first9MPR']) ? round((float) $legStats['first9MPR'], 2) : null;
            }

            // Update or create leg_player record
            DB::table('leg_player')->updateOrInsert(
                [
                    'leg_id' => $leg->id,
                    'player_id' => $player->id,
                ],
                [
                    'average' => $average,
                    'average_until_170' => $averageUntil170,
                    'first_9_average' => $first9Average,
                    'checkout_rate' => $checkoutRate,
                    'darts_thrown' => $dartsThrown,
                    'checkout_attempts' => $checkoutAttempts,
                    'checkout_hits' => $checkoutHits,
                    'best_checkout_points' => $bestCheckoutPoints,
                    'mpr' => $mpr,
                    'first_9_mpr' => $first9MPR,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }

    protected function updateFinalPositions(DartMatch $match, array $matchData): void
    {
        // Prefer gameWinner over winner as it's more reliable (array index vs player index confusion)
        $winnerIndex = $matchData['gameWinner'] ?? $matchData['winner'] ?? null;
        $players = $match->players;

        if ($winnerIndex !== null) {
            // Simple case: winner index is provided (as array index in players array)
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

    /**
     * Check if a turn from match_state is a Bull-Off turn
     */
    protected function isBullOffTurnFromMatchState(DartMatch $match, array $turnData, array $matchData): bool
    {
        // First, check if Bull-Off is enabled for this match
        // Bull-Off is enabled if gameScores contains negative values or stats.bullDistance exists
        $gameScores = $matchData['gameScores'] ?? [];
        $stats = $matchData['stats'] ?? [];
        $hasBullOffEnabled = false;

        // Check if gameScores has negative values (indicates Bull-Off)
        foreach ($gameScores as $score) {
            if ($score < 0) {
                $hasBullOffEnabled = true;
                break;
            }
        }

        // Also check if stats.bullDistance exists (another indicator)
        if (! $hasBullOffEnabled && ! empty($stats)) {
            foreach ($stats as $stat) {
                $bullDistance = $stat['legStats']['bullDistance'] ?? $stat['matchStats']['bullDistance'] ?? null;
                if ($bullDistance !== null) {
                    $hasBullOffEnabled = true;
                    break;
                }
            }
        }

        // If Bull-Off is not enabled, this cannot be a Bull-Off turn
        if (! $hasBullOffEnabled) {
            return false;
        }

        // Must be round 1, leg 1, set 1
        if (($matchData['leg'] ?? 1) !== 1 || ($matchData['set'] ?? 1) !== 1 || ($turnData['round'] ?? 1) !== 1) {
            return false;
        }

        // Check if this is a Bull-Off turn
        // Bull-Off can be:
        // 1. All throws on bull (25) - hit
        // 2. Negative score - miss (threw beside bull)
        $throws = $turnData['throws'] ?? [];
        $score = $turnData['score'] ?? null;

        // Check if all throws are on bull
        $allOnBull = true;
        if (! empty($throws)) {
            foreach ($throws as $throw) {
                $segmentNumber = $throw['segment']['number'] ?? null;
                if ($segmentNumber !== 25) {
                    $allOnBull = false;
                    break;
                }
            }
        } else {
            $allOnBull = false;
        }

        // If not all throws on bull, check if score is negative (miss)
        // Negative score indicates a miss during Bull-Off
        $isMiss = $score !== null && $score < 0;

        // Must be either all throws on bull OR a miss (negative score)
        if (! $allOnBull && ! $isMiss) {
            return false;
        }

        // If no throws and no negative score, this is not a Bull-Off
        if (empty($throws) && ! $isMiss) {
            return false;
        }

        // Check if there are any normal turns (with positive or zero score) in rounds > 1
        // Turns in round > 1 are definitely not Bull-Offs
        // Exclude the current turn if it already exists as a Turn
        // Note: We only check for turns in round > 1, because turns in round 1 might be Bull-Offs
        // If there are turns in round > 1, then this turn in round 1 cannot be a Bull-Off
        $hasNormalTurnsInLaterRounds = Turn::query()
            ->join('legs', 'turns.leg_id', '=', 'legs.id')
            ->where('legs.match_id', $match->id)
            ->where('turns.points', '>=', 0)
            ->where('turns.round_number', '>', 1)
            ->where('turns.autodarts_turn_id', '!=', $turnData['id'] ?? '')
            ->exists();

        if ($hasNormalTurnsInLaterRounds) {
            return false;
        }

        // Additional check: Count how many Bull-Off throws already exist for this match
        // But allow if this turn is already a Bull-Off (to handle reprocessing)
        $existingBullOffs = BullOff::where('match_id', $match->id)
            ->where('autodarts_turn_id', '!=', $turnData['id'] ?? '')
            ->count();
        if ($existingBullOffs >= 2) {
            return false;
        }

        // Bull-Off can have positive scores (25 for single bull, 50 for double bull)
        // or negative scores (miss). The key is: round 1, leg 1, set 1, all throws on bull, no normal turns yet
        return true;
    }

    /**
     * Check if a throw is a Bull-Off throw
     * Bull-Off occurs before the game starts, both players throw once at bull
     * Criteria: round 1, leg 1, set 1, throw on bull (25), and no normal turns exist yet
     * Score can be positive (25 for single bull, 50 for double bull) or negative (miss)
     * Note: For throw events, we need to check match_state webhooks to determine if Bull-Off is enabled
     */
    protected function isBullOffThrow(DartMatch $match, array $data): bool
    {
        // Check if Bull-Off is enabled by looking at match_state webhooks
        // Bull-Off is enabled if gameScores contains negative values or stats.bullDistance exists
        $hasBullOffEnabled = $this->isBullOffEnabledForMatch($match);

        // If Bull-Off is not enabled, this cannot be a Bull-Off throw
        if (! $hasBullOffEnabled) {
            return false;
        }

        // Must be round 1, leg 1, set 1
        if (($data['leg'] ?? 1) !== 1 || ($data['set'] ?? 1) !== 1 || ($data['round'] ?? 1) !== 1) {
            return false;
        }

        // Check if the throw is on bull (25) - Bull-Off is always on bull
        $throwSegment = $data['throw']['segment'] ?? [];
        $segmentNumber = $throwSegment['number'] ?? null;
        if ($segmentNumber !== 25) {
            return false;
        }

        // Check if there are any normal turns (with positive or zero score) in this match
        // Exclude the current turn if it already exists as a Turn
        // If yes, this is not a Bull-Off (it's a Bull-Out during the game)
        $hasNormalTurns = Turn::query()
            ->join('legs', 'turns.leg_id', '=', 'legs.id')
            ->where('legs.match_id', $match->id)
            ->where('turns.points', '>=', 0)
            ->where('turns.autodarts_turn_id', '!=', $data['turnId'] ?? '')
            ->exists();

        // If normal turns exist, this is not a Bull-Off
        if ($hasNormalTurns) {
            return false;
        }

        // Additional check: Count how many Bull-Off throws already exist for this match
        // Bull-Off should only have 2 throws (one per player)
        $existingBullOffs = BullOff::where('match_id', $match->id)->count();
        if ($existingBullOffs >= 2) {
            // Already have 2 Bull-Off throws, this must be something else
            return false;
        }

        // Bull-Off can have positive scores (25 for single bull, 50 for double bull)
        // or negative scores (miss). The key is: round 1, leg 1, set 1, throw on bull, no normal turns yet
        return true;
    }

    /**
     * Check if Bull-Off is enabled for a match by checking match_state webhooks
     */
    protected function isBullOffEnabledForMatch(DartMatch $match): bool
    {
        // Find a match_state webhook for this match
        $webhookCall = \Spatie\WebhookClient\Models\WebhookCall::whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.matchId')) = ?",
            [$match->autodarts_match_id]
        )
            ->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) = 'match_state'"
            )
            ->orderBy('created_at')
            ->first();

        if (! $webhookCall) {
            return false;
        }

        $matchData = $webhookCall->payload['data']['match'] ?? [];
        $gameScores = $matchData['gameScores'] ?? [];
        $stats = $matchData['stats'] ?? [];

        // Check if gameScores has negative values (indicates Bull-Off)
        foreach ($gameScores as $score) {
            if ($score < 0) {
                return true;
            }
        }

        // Also check if stats.bullDistance exists (another indicator)
        if (! empty($stats)) {
            foreach ($stats as $stat) {
                $bullDistance = $stat['legStats']['bullDistance'] ?? $stat['matchStats']['bullDistance'] ?? null;
                if ($bullDistance !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a match_state event exists for the given matchId
     */
    protected function matchStateEventExists(string $matchId): bool
    {
        // Use database-agnostic approach by loading all match_state webhooks and filtering in PHP
        $matchStateWebhooks = \Spatie\WebhookClient\Models\WebhookCall::whereNotNull('payload')
            ->get()
            ->filter(function ($webhook) use ($matchId) {
                $payload = $webhook->payload;

                return ($payload['event'] ?? null) === 'match_state'
                    && ($payload['matchId'] ?? null) === $matchId;
            });

        return $matchStateWebhooks->isNotEmpty();
    }

    /**
     * Find the unique userId from a playerId (spiel-spezifische ID) by looking up in match_state webhook
     */
    protected function findUserIdFromPlayerId(string $matchId, string $playerId, string $playerName): string
    {
        // Try to find a match_state webhook for this match
        // Use database-agnostic approach by loading all match_state webhooks and filtering in PHP
        $matchStateWebhooks = \Spatie\WebhookClient\Models\WebhookCall::whereNotNull('payload')
            ->get()
            ->filter(function ($webhook) use ($matchId) {
                $payload = $webhook->payload;

                return ($payload['event'] ?? null) === 'match_state'
                    && ($payload['matchId'] ?? null) === $matchId;
            })
            ->sortByDesc('created_at');

        $matchStateWebhook = $matchStateWebhooks->first();

        if ($matchStateWebhook && isset($matchStateWebhook->payload['data']['match']['players'])) {
            // Look for the player in the players array by matching the id (playerId)
            foreach ($matchStateWebhook->payload['data']['match']['players'] as $playerData) {
                if (isset($playerData['id']) && $playerData['id'] === $playerId) {
                    // Found the player - return the userId if available
                    if (isset($playerData['userId'])) {
                        return $playerData['userId'];
                    }
                    // If no userId, it's a bot - generate deterministic UUID
                    if (isset($playerData['name'])) {
                        return $this->generateBotUuid($playerData['name']);
                    }
                }
            }
        }

        // Fallback: if no match_state found or player not found, check if player exists by playerId
        // This handles edge cases where match_state hasn't arrived yet
        $existingPlayer = Player::where('autodarts_user_id', $playerId)->first();
        if ($existingPlayer) {
            // Player exists with this ID - return it (might be old data, but better than creating duplicate)
            return $existingPlayer->autodarts_user_id;
        }

        // Last resort: if no match_state exists and no player found, use playerId as userId
        // This allows throw events to work when match_state events are disabled
        // Note: This means playerId (spiel-spezifische ID) will be used as autodarts_user_id
        // which is not ideal, but necessary for backwards compatibility when only throw events are enabled
        return $playerId;
    }
}
