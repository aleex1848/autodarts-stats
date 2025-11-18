<?php

namespace App\Support;

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Models\Turn;

class MatchStatisticsCalculator
{
    public static function calculateAndUpdate(DartMatch $match): void
    {
        // Get all players in this match
        $players = $match->players;

        foreach ($players as $player) {
            // Calculate 3-dart average: (total points / number of darts) * 3
            $throwStats = DartThrow::query()
                ->join('turns', 'throws.turn_id', '=', 'turns.id')
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->where('legs.match_id', $match->id)
                ->where('turns.player_id', $player->id)
                ->where('throws.is_corrected', false)
                ->selectRaw('COUNT(*) as throw_count, SUM(throws.points) as total_points')
                ->first();

            $matchAverage = null;
            if ($throwStats && $throwStats->throw_count > 0) {
                // 3-dart average: (total points / number of darts) * 3
                $matchAverage = round(((float) $throwStats->total_points / (int) $throwStats->throw_count) * 3, 2);
            }

            // Calculate checkout rate: successful checkouts / checkout attempts
            // A checkout attempt is a turn where score_after <= 170 (theoretically finishable)
            // A successful checkout is a turn where score_after = 0
            $checkoutStats = Turn::query()
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->where('legs.match_id', $match->id)
                ->where('turns.player_id', $player->id)
                ->whereNotNull('turns.score_after')
                ->where('turns.score_after', '<=', 170)
                ->selectRaw('COUNT(*) as checkout_attempts, SUM(CASE WHEN turns.score_after = 0 THEN 1 ELSE 0 END) as successful_checkouts')
                ->first();

            $checkoutRate = null;
            if ($checkoutStats && $checkoutStats->checkout_attempts > 0) {
                // Store as decimal between 0 and 1 (e.g., 0.7142 for 71.42%)
                $rate = (float) $checkoutStats->successful_checkouts / (int) $checkoutStats->checkout_attempts;
                $checkoutRate = round($rate, 4);
            }

            // Count 180s (three triple 20s in one turn)
            $turnsWithTriples = Turn::query()
                ->join('legs', 'turns.leg_id', '=', 'legs.id')
                ->join('throws', 'turns.id', '=', 'throws.turn_id')
                ->where('legs.match_id', $match->id)
                ->where('turns.player_id', $player->id)
                ->where('throws.is_corrected', false)
                ->where('throws.segment_number', 20)
                ->where('throws.multiplier', 3)
                ->selectRaw('turns.id')
                ->groupBy('turns.id')
                ->havingRaw('COUNT(*) = 3')
                ->pluck('turns.id');

            $total180s = $turnsWithTriples->count();

            // Update match_player pivot table
            $match->players()->updateExistingPivot($player->id, [
                'match_average' => $matchAverage,
                'checkout_rate' => $checkoutRate,
                'total_180s' => $total180s,
            ]);
        }
    }
}
