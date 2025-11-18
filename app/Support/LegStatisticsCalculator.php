<?php

namespace App\Support;

use App\Models\DartThrow;
use App\Models\Leg;
use App\Models\Turn;
use Illuminate\Support\Facades\DB;

class LegStatisticsCalculator
{
    public static function calculateAndUpdate(Leg $leg): void
    {
        // Get all players in this leg's match
        $players = $leg->match->players;

        foreach ($players as $player) {
            // Calculate 3-dart average for this player in this leg
            // Average = (total points / number of darts) * 3
            $throwStats = DartThrow::query()
                ->join('turns', 'throws.turn_id', '=', 'turns.id')
                ->where('turns.leg_id', $leg->id)
                ->where('turns.player_id', $player->id)
                ->where('throws.is_corrected', false)
                ->selectRaw('COUNT(*) as throw_count, SUM(throws.points) as total_points')
                ->first();

            $average = null;
            if ($throwStats && $throwStats->throw_count > 0) {
                // 3-dart average: (total points / number of darts) * 3
                $average = round(((float) $throwStats->total_points / (int) $throwStats->throw_count) * 3, 2);
            }

            // Calculate checkout rate for this player in this leg
            $checkoutStats = Turn::query()
                ->where('leg_id', $leg->id)
                ->where('player_id', $player->id)
                ->whereNotNull('score_after')
                ->where('score_after', '<=', 170)
                ->selectRaw('COUNT(*) as checkout_attempts, SUM(CASE WHEN score_after = 0 THEN 1 ELSE 0 END) as successful_checkouts')
                ->first();

            $checkoutRate = null;
            $checkoutAttempts = null;
            $checkoutHits = null;
            if ($checkoutStats && $checkoutStats->checkout_attempts > 0) {
                $rate = (float) $checkoutStats->successful_checkouts / (int) $checkoutStats->checkout_attempts;
                $checkoutRate = round($rate, 4);
                $checkoutAttempts = (int) $checkoutStats->checkout_attempts;
                $checkoutHits = (int) $checkoutStats->successful_checkouts;
            }

            // Count darts thrown
            $dartsThrown = (int) ($throwStats->throw_count ?? 0);

            // Count busted turns (when player overthrows)
            $bustedCount = Turn::query()
                ->where('leg_id', $leg->id)
                ->where('player_id', $player->id)
                ->where('busted', true)
                ->count();

            // Update or create leg_player record
            DB::table('leg_player')->updateOrInsert(
                [
                    'leg_id' => $leg->id,
                    'player_id' => $player->id,
                ],
                [
                    'average' => $average,
                    'checkout_rate' => $checkoutRate,
                    'darts_thrown' => $dartsThrown > 0 ? $dartsThrown : null,
                    'checkout_attempts' => $checkoutAttempts,
                    'checkout_hits' => $checkoutHits,
                    'busted_count' => $bustedCount > 0 ? $bustedCount : null,
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }
}
