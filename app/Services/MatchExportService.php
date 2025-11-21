<?php

namespace App\Services;

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Turn;
use Spatie\WebhookClient\Models\WebhookCall;

class MatchExportService
{
    public function exportMatch(DartMatch $match): array
    {
        // Load all related data
        $match->load([
            'players' => fn ($query) => $query->orderBy('match_player.player_index'),
            'winner',
            'legs.turns.throws.webhookCall',
            'legs.legPlayers',
        ]);

        // Get all legs with their turns and throws
        $legs = $match->legs()
            ->with(['turns.throws.webhookCall', 'legPlayers'])
            ->orderBy('set_number')
            ->orderBy('leg_number')
            ->get();

        // Collect all turns
        $turns = collect();
        foreach ($legs as $leg) {
            $turns = $turns->merge($leg->turns);
        }

        // Collect all throws
        $throws = collect();
        foreach ($turns as $turn) {
            $throws = $throws->merge($turn->throws);
        }

        // Find all WebhookCalls for this match
        $webhookCalls = WebhookCall::whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(payload, '$.matchId')) = ?",
            [$match->autodarts_match_id]
        )->orderBy('created_at')->get();

        // Build export data
        $exportData = [
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'match' => $this->exportMatchData($match),
            'players' => $this->exportPlayersData($match),
            'legs' => $this->exportLegsData($legs),
            'turns' => $this->exportTurnsData($turns),
            'throws' => $this->exportThrowsData($throws),
            'webhook_calls' => $this->exportWebhookCallsData($webhookCalls),
        ];

        return $exportData;
    }

    protected function exportMatchData(DartMatch $match): array
    {
        return [
            'id' => $match->id,
            'autodarts_match_id' => $match->autodarts_match_id,
            'variant' => $match->variant,
            'type' => $match->type,
            'base_score' => $match->base_score,
            'in_mode' => $match->in_mode,
            'out_mode' => $match->out_mode,
            'bull_mode' => $match->bull_mode,
            'max_rounds' => $match->max_rounds,
            'winner_player_id' => $match->winner_player_id,
            'started_at' => $match->started_at?->toIso8601String(),
            'finished_at' => $match->finished_at?->toIso8601String(),
            'created_at' => $match->created_at->toIso8601String(),
            'updated_at' => $match->updated_at->toIso8601String(),
        ];
    }

    protected function exportPlayersData(DartMatch $match): array
    {
        $players = [];

        foreach ($match->players as $player) {
            $players[] = [
                'id' => $player->id,
                'autodarts_user_id' => $player->autodarts_user_id,
                'name' => $player->name,
                'email' => $player->email,
                'country' => $player->country,
                'avatar_url' => $player->avatar_url,
                'user_id' => $player->user_id,
                'created_at' => $player->created_at->toIso8601String(),
                'updated_at' => $player->updated_at->toIso8601String(),
                'pivot' => [
                    'player_index' => $player->pivot->player_index,
                    'legs_won' => $player->pivot->legs_won,
                    'sets_won' => $player->pivot->sets_won,
                    'final_position' => $player->pivot->final_position,
                    'match_average' => $player->pivot->match_average,
                    'checkout_rate' => $player->pivot->checkout_rate,
                    'checkout_attempts' => $player->pivot->checkout_attempts,
                    'checkout_hits' => $player->pivot->checkout_hits,
                    'total_180s' => $player->pivot->total_180s,
                    'darts_thrown' => $player->pivot->darts_thrown,
                    'busted_count' => $player->pivot->busted_count,
                    'created_at' => $player->pivot->created_at->toIso8601String(),
                    'updated_at' => $player->pivot->updated_at->toIso8601String(),
                ],
            ];
        }

        return $players;
    }

    protected function exportLegsData($legs): array
    {
        $exportedLegs = [];

        foreach ($legs as $leg) {
            $legPlayers = [];
            foreach ($leg->legPlayers as $player) {
                $legPlayers[] = [
                    'player_id' => $player->id,
                    'pivot' => [
                        'average' => $player->pivot->average,
                        'checkout_rate' => $player->pivot->checkout_rate,
                        'darts_thrown' => $player->pivot->darts_thrown,
                        'checkout_attempts' => $player->pivot->checkout_attempts,
                        'checkout_hits' => $player->pivot->checkout_hits,
                        'busted_count' => $player->pivot->busted_count,
                        'created_at' => $player->pivot->created_at->toIso8601String(),
                        'updated_at' => $player->pivot->updated_at->toIso8601String(),
                    ],
                ];
            }

            $exportedLegs[] = [
                'id' => $leg->id,
                'match_id' => $leg->match_id,
                'leg_number' => $leg->leg_number,
                'set_number' => $leg->set_number,
                'winner_player_id' => $leg->winner_player_id,
                'started_at' => $leg->started_at?->toIso8601String(),
                'finished_at' => $leg->finished_at?->toIso8601String(),
                'created_at' => $leg->created_at->toIso8601String(),
                'updated_at' => $leg->updated_at->toIso8601String(),
                'leg_players' => $legPlayers,
            ];
        }

        return $exportedLegs;
    }

    protected function exportTurnsData($turns): array
    {
        $exportedTurns = [];

        foreach ($turns as $turn) {
            $exportedTurns[] = [
                'id' => $turn->id,
                'leg_id' => $turn->leg_id,
                'player_id' => $turn->player_id,
                'autodarts_turn_id' => $turn->autodarts_turn_id,
                'round_number' => $turn->round_number,
                'turn_number' => $turn->turn_number,
                'points' => $turn->points,
                'score_after' => $turn->score_after,
                'busted' => $turn->busted,
                'started_at' => $turn->started_at?->toIso8601String(),
                'finished_at' => $turn->finished_at?->toIso8601String(),
                'created_at' => $turn->created_at->toIso8601String(),
                'updated_at' => $turn->updated_at->toIso8601String(),
            ];
        }

        return $exportedTurns;
    }

    protected function exportThrowsData($throws): array
    {
        $exportedThrows = [];

        foreach ($throws as $throw) {
            $exportedThrows[] = [
                'id' => $throw->id,
                'turn_id' => $throw->turn_id,
                'autodarts_throw_id' => $throw->autodarts_throw_id,
                'webhook_call_id' => $throw->webhook_call_id,
                'dart_number' => $throw->dart_number,
                'segment_number' => $throw->segment_number,
                'multiplier' => $throw->multiplier,
                'points' => $throw->points,
                'segment_name' => $throw->segment_name,
                'segment_bed' => $throw->segment_bed,
                'coords_x' => $throw->coords_x,
                'coords_y' => $throw->coords_y,
                'is_corrected' => $throw->is_corrected,
                'corrected_at' => $throw->corrected_at?->toIso8601String(),
                'corrected_by_throw_id' => $throw->corrected_by_throw_id,
                'created_at' => $throw->created_at->toIso8601String(),
                'updated_at' => $throw->updated_at->toIso8601String(),
            ];
        }

        return $exportedThrows;
    }

    protected function exportWebhookCallsData($webhookCalls): array
    {
        $exportedWebhookCalls = [];

        foreach ($webhookCalls as $webhookCall) {
            $exportedWebhookCalls[] = [
                'id' => $webhookCall->id,
                'name' => $webhookCall->name,
                'url' => $webhookCall->url,
                'headers' => $webhookCall->headers,
                'payload' => $webhookCall->payload,
                'exception' => $webhookCall->exception,
                'created_at' => $webhookCall->created_at->toIso8601String(),
                'updated_at' => $webhookCall->updated_at->toIso8601String(),
            ];
        }

        return $exportedWebhookCalls;
    }
}

