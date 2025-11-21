<?php

namespace App\Services;

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\MatchPlayer;
use App\Models\Player;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

class MatchImportService
{
    public function importMatch(array $data, bool $overwrite = false): DartMatch
    {
        return DB::transaction(function () use ($data, $overwrite) {
            // Validate structure
            $this->validateImportData($data);

            // Check if match already exists
            $existingMatch = DartMatch::where('autodarts_match_id', $data['match']['autodarts_match_id'])->first();

            if ($existingMatch && ! $overwrite) {
                throw new \Exception(__('Match mit dieser autodarts_match_id existiert bereits.'));
            }

            // Import match
            $match = $this->importMatchData($data['match'], $existingMatch);

            // Import players
            $playerMap = $this->importPlayers($data['players'] ?? []);

            // Update winner_player_id if it exists in playerMap
            if (isset($data['match']['winner_player_id']) && $data['match']['winner_player_id'] !== null) {
                if (isset($playerMap[$data['match']['winner_player_id']])) {
                    $match->winner_player_id = $playerMap[$data['match']['winner_player_id']];
                    $match->save();
                }
            } else {
                // If winner_player_id was null in export, ensure it's null
                $match->winner_player_id = null;
                $match->save();
            }

            // Import match_player pivot data
            $this->importMatchPlayers($match, $data['players'] ?? [], $playerMap);

            // Import legs
            $legMap = $this->importLegs($match, $data['legs'] ?? [], $playerMap);

            // Import leg_player pivot data
            $this->importLegPlayers($data['legs'] ?? [], $legMap, $playerMap);

            // Import turns
            $turnMap = $this->importTurns($data['turns'] ?? [], $legMap, $playerMap);

            // Import throws
            $this->importThrows($data['throws'] ?? [], $turnMap);

            // Import webhook calls
            $this->importWebhookCalls($data['webhook_calls'] ?? []);

            Log::info('Match imported successfully', [
                'match_id' => $match->id,
                'autodarts_match_id' => $match->autodarts_match_id,
            ]);

            return $match;
        });
    }

    protected function validateImportData(array $data): void
    {
        if (! isset($data['match'])) {
            throw new \Exception(__('Ungültiges Export-Format: Match-Daten fehlen.'));
        }

        if (! isset($data['match']['autodarts_match_id'])) {
            throw new \Exception(__('Ungültiges Export-Format: autodarts_match_id fehlt.'));
        }
    }

    protected function importMatchData(array $matchData, ?DartMatch $existingMatch): DartMatch
    {
        // Don't set winner_player_id here - it will be updated after players are imported
        $matchDataToSave = [
            'autodarts_match_id' => $matchData['autodarts_match_id'],
            'variant' => $matchData['variant'],
            'type' => $matchData['type'],
            'base_score' => $matchData['base_score'],
            'in_mode' => $matchData['in_mode'],
            'out_mode' => $matchData['out_mode'],
            'bull_mode' => $matchData['bull_mode'],
            'max_rounds' => $matchData['max_rounds'],
            'winner_player_id' => null, // Will be updated after players are imported
            'started_at' => $matchData['started_at'] ? \Carbon\Carbon::parse($matchData['started_at']) : null,
            'finished_at' => $matchData['finished_at'] ? \Carbon\Carbon::parse($matchData['finished_at']) : null,
        ];

        if ($existingMatch) {
            // For existing matches, also set winner_player_id to null initially
            $matchDataToSave['winner_player_id'] = null;
            $existingMatch->update($matchDataToSave);

            return $existingMatch;
        }

        return DartMatch::create($matchDataToSave);
    }

    protected function importPlayers(array $playersData): array
    {
        $playerMap = [];

        foreach ($playersData as $playerData) {
            // Validate user_id - only use it if the user exists
            $userId = null;
            if (isset($playerData['user_id']) && $playerData['user_id'] !== null) {
                $userExists = User::where('id', $playerData['user_id'])->exists();
                $userId = $userExists ? $playerData['user_id'] : null;
            }

            // Try to find existing player by autodarts_user_id or name
            $player = Player::where('autodarts_user_id', $playerData['autodarts_user_id'])
                ->orWhere('name', $playerData['name'])
                ->first();

            if (! $player) {
                $player = Player::create([
                    'autodarts_user_id' => $playerData['autodarts_user_id'],
                    'name' => $playerData['name'],
                    'email' => $playerData['email'],
                    'country' => $playerData['country'],
                    'avatar_url' => $playerData['avatar_url'],
                    'user_id' => $userId,
                ]);
            } else {
                // Update existing player
                $player->update([
                    'autodarts_user_id' => $playerData['autodarts_user_id'],
                    'name' => $playerData['name'],
                    'email' => $playerData['email'],
                    'country' => $playerData['country'],
                    'avatar_url' => $playerData['avatar_url'],
                    'user_id' => $userId,
                ]);
            }

            $playerMap[$playerData['id']] = $player->id;
        }

        return $playerMap;
    }

    protected function importMatchPlayers(DartMatch $match, array $playersData, array $playerMap): void
    {
        // Delete existing match_player entries
        DB::table('match_player')->where('match_id', $match->id)->delete();

        foreach ($playersData as $playerData) {
            if (! isset($playerMap[$playerData['id']])) {
                continue;
            }

            $newPlayerId = $playerMap[$playerData['id']];
            $pivotData = $playerData['pivot'] ?? [];

            MatchPlayer::create([
                'match_id' => $match->id,
                'player_id' => $newPlayerId,
                'player_index' => $pivotData['player_index'] ?? null,
                'legs_won' => $pivotData['legs_won'] ?? null,
                'sets_won' => $pivotData['sets_won'] ?? null,
                'final_position' => $pivotData['final_position'] ?? null,
                'match_average' => $pivotData['match_average'] ?? null,
                'checkout_rate' => $pivotData['checkout_rate'] ?? null,
                'checkout_attempts' => $pivotData['checkout_attempts'] ?? null,
                'checkout_hits' => $pivotData['checkout_hits'] ?? null,
                'total_180s' => $pivotData['total_180s'] ?? null,
                'darts_thrown' => $pivotData['darts_thrown'] ?? null,
                'busted_count' => $pivotData['busted_count'] ?? null,
            ]);
        }
    }

    protected function importLegs(DartMatch $match, array $legsData, array $playerMap): array
    {
        $legMap = [];

        // Delete existing legs
        Leg::where('match_id', $match->id)->delete();

        foreach ($legsData as $legData) {
            $winnerPlayerId = null;
            if (isset($legData['winner_player_id']) && isset($playerMap[$legData['winner_player_id']])) {
                $winnerPlayerId = $playerMap[$legData['winner_player_id']];
            }

            $leg = Leg::create([
                'match_id' => $match->id,
                'leg_number' => $legData['leg_number'],
                'set_number' => $legData['set_number'],
                'winner_player_id' => $winnerPlayerId,
                'started_at' => $legData['started_at'] ? \Carbon\Carbon::parse($legData['started_at']) : null,
                'finished_at' => $legData['finished_at'] ? \Carbon\Carbon::parse($legData['finished_at']) : null,
            ]);

            $legMap[$legData['id']] = $leg->id;
        }

        return $legMap;
    }

    protected function importLegPlayers(array $legsData, array $legMap, array $playerMap): void
    {
        foreach ($legsData as $legData) {
            if (! isset($legMap[$legData['id']])) {
                continue;
            }

            $newLegId = $legMap[$legData['id']];
            $legPlayers = $legData['leg_players'] ?? [];

            // Delete existing leg_player entries for this leg
            DB::table('leg_player')->where('leg_id', $newLegId)->delete();

            foreach ($legPlayers as $legPlayer) {
                if (! isset($playerMap[$legPlayer['player_id']])) {
                    continue;
                }

                $newPlayerId = $playerMap[$legPlayer['player_id']];
                $pivotData = $legPlayer['pivot'] ?? [];

                DB::table('leg_player')->insert([
                    'leg_id' => $newLegId,
                    'player_id' => $newPlayerId,
                    'average' => $pivotData['average'] ?? null,
                    'checkout_rate' => $pivotData['checkout_rate'] ?? null,
                    'darts_thrown' => $pivotData['darts_thrown'] ?? null,
                    'checkout_attempts' => $pivotData['checkout_attempts'] ?? null,
                    'checkout_hits' => $pivotData['checkout_hits'] ?? null,
                    'busted_count' => $pivotData['busted_count'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    protected function importTurns(array $turnsData, array $legMap, array $playerMap): array
    {
        $turnMap = [];

        // Get all leg IDs for this match to delete existing turns
        $legIds = array_values($legMap);
        if (! empty($legIds)) {
            Turn::whereIn('leg_id', $legIds)->delete();
        }

        foreach ($turnsData as $turnData) {
            if (! isset($legMap[$turnData['leg_id']]) || ! isset($playerMap[$turnData['player_id']])) {
                continue;
            }

            $turn = Turn::create([
                'leg_id' => $legMap[$turnData['leg_id']],
                'player_id' => $playerMap[$turnData['player_id']],
                'autodarts_turn_id' => $turnData['autodarts_turn_id'],
                'round_number' => $turnData['round_number'],
                'turn_number' => $turnData['turn_number'],
                'points' => $turnData['points'],
                'score_after' => $turnData['score_after'],
                'busted' => $turnData['busted'] ?? false,
                'started_at' => $turnData['started_at'] ? \Carbon\Carbon::parse($turnData['started_at']) : null,
                'finished_at' => $turnData['finished_at'] ? \Carbon\Carbon::parse($turnData['finished_at']) : null,
            ]);

            $turnMap[$turnData['id']] = $turn->id;
        }

        return $turnMap;
    }

    protected function importThrows(array $throwsData, array $turnMap): void
    {
        // Get all turn IDs to delete existing throws
        $turnIds = array_values($turnMap);
        if (! empty($turnIds)) {
            DartThrow::whereIn('turn_id', $turnIds)->delete();
        }

        foreach ($throwsData as $throwData) {
            if (! isset($turnMap[$throwData['turn_id']])) {
                continue;
            }

            DartThrow::create([
                'turn_id' => $turnMap[$throwData['turn_id']],
                'autodarts_throw_id' => $throwData['autodarts_throw_id'],
                'webhook_call_id' => $throwData['webhook_call_id'],
                'dart_number' => $throwData['dart_number'],
                'segment_number' => $throwData['segment_number'],
                'multiplier' => $throwData['multiplier'],
                'points' => $throwData['points'],
                'segment_name' => $throwData['segment_name'],
                'segment_bed' => $throwData['segment_bed'],
                'coords_x' => $throwData['coords_x'],
                'coords_y' => $throwData['coords_y'],
                'is_corrected' => $throwData['is_corrected'] ?? false,
                'corrected_at' => $throwData['corrected_at'] ? \Carbon\Carbon::parse($throwData['corrected_at']) : null,
                'corrected_by_throw_id' => $throwData['corrected_by_throw_id'],
            ]);
        }
    }

    protected function importWebhookCalls(array $webhookCallsData): void
    {
        foreach ($webhookCallsData as $webhookCallData) {
            // Check if webhook call already exists
            $existing = WebhookCall::find($webhookCallData['id']);

            if ($existing) {
                // Update existing webhook call
                $existing->update([
                    'name' => $webhookCallData['name'],
                    'url' => $webhookCallData['url'],
                    'headers' => $webhookCallData['headers'],
                    'payload' => $webhookCallData['payload'],
                    'exception' => $webhookCallData['exception'],
                ]);
            } else {
                // Create new webhook call (may need to handle ID conflicts)
                try {
                    WebhookCall::create([
                        'id' => $webhookCallData['id'],
                        'name' => $webhookCallData['name'],
                        'url' => $webhookCallData['url'],
                        'headers' => $webhookCallData['headers'],
                        'payload' => $webhookCallData['payload'],
                        'exception' => $webhookCallData['exception'],
                    ]);
                } catch (\Exception $e) {
                    // If ID conflict, create without ID
                    WebhookCall::create([
                        'name' => $webhookCallData['name'],
                        'url' => $webhookCallData['url'],
                        'headers' => $webhookCallData['headers'],
                        'payload' => $webhookCallData['payload'],
                        'exception' => $webhookCallData['exception'],
                    ]);
                }
            }
        }
    }
}

