<?php

namespace App\Services;

use App\Models\DartMatch;

class MatchDataExtractor
{
    public function extract(DartMatch $match): array
    {
        $match->load([
            'players',
            'legs.winner',
            'matchPlayers',
            'fixture.matchday.season.league',
            'fixture.homePlayer',
            'fixture.awayPlayer',
        ]);

        $fixture = $match->fixture;
        $homePlayer = $fixture?->homePlayer;
        $awayPlayer = $fixture?->awayPlayer;

        // Get match players with statistics
        $matchPlayers = $match->matchPlayers()->with('player')->get();
        $playerStats = [];
        foreach ($matchPlayers as $matchPlayer) {
            $playerStats[] = [
                'name' => $matchPlayer->player->name,
                'legs_won' => $matchPlayer->legs_won ?? 0,
                'sets_won' => $matchPlayer->sets_won ?? 0,
                'match_average' => round($matchPlayer->match_average ?? 0, 2),
                'checkout_rate' => round(($matchPlayer->checkout_rate ?? 0) * 100, 1),
                'total_180s' => $matchPlayer->total_180s ?? 0,
                'best_checkout_points' => $matchPlayer->best_checkout_points ?? 0,
                'darts_thrown' => $matchPlayer->darts_thrown ?? 0,
                'busted_count' => $matchPlayer->busted_count ?? 0,
            ];
        }

        // Get leg-by-leg progression
        $legs = $match->legs()->with('winner')->orderBy('set_number')->orderBy('leg_number')->get();
        $legProgression = [];
        $sets = [];
        $currentSet = null;

        foreach ($legs as $leg) {
            if ($currentSet === null || $currentSet !== $leg->set_number) {
                if ($currentSet !== null) {
                    $sets[] = [
                        'set_number' => $currentSet,
                        'legs' => $legProgression,
                    ];
                }
                $currentSet = $leg->set_number;
                $legProgression = [];
            }

            $legProgression[] = [
                'set_number' => $leg->set_number,
                'leg_number' => $leg->leg_number,
                'winner' => $leg->winner?->name ?? 'Unbekannt',
            ];
        }

        if ($currentSet !== null) {
            $sets[] = [
                'set_number' => $currentSet,
                'legs' => $legProgression,
            ];
        }

        // Find highlights
        $highlights = $this->extractHighlights($match, $matchPlayers);

        return [
            'match_info' => [
                'id' => $match->id,
                'variant' => $match->variant,
                'base_score' => $match->base_score,
                'match_format' => $match->match_mode_type ?? 'best_of',
                'started_at' => $match->started_at?->format('d.m.Y H:i'),
                'finished_at' => $match->finished_at?->format('d.m.Y H:i'),
                'winner' => $match->winner?->name ?? 'Unbekannt',
            ],
            'players' => $playerStats,
            'home_player' => $homePlayer?->name,
            'away_player' => $awayPlayer?->name,
            'sets' => $sets,
            'highlights' => $highlights,
            'season_info' => [
                'league_name' => $fixture?->matchday?->season?->league?->name,
                'season_name' => $fixture?->matchday?->season?->name,
                'matchday_number' => $fixture?->matchday?->matchday_number,
            ],
        ];
    }

    protected function extractHighlights(DartMatch $match, $matchPlayers): array
    {
        $highlights = [];

        // Find highest checkout
        $highestCheckout = 0;
        $highestCheckoutPlayer = null;
        foreach ($matchPlayers as $matchPlayer) {
            if (($matchPlayer->best_checkout_points ?? 0) > $highestCheckout) {
                $highestCheckout = $matchPlayer->best_checkout_points ?? 0;
                $highestCheckoutPlayer = $matchPlayer->player->name;
            }
        }

        if ($highestCheckout > 0) {
            $highlights[] = [
                'type' => 'high_checkout',
                'description' => "Höchstes Finish: {$highestCheckout} Punkte von {$highestCheckoutPlayer}",
                'value' => $highestCheckout,
                'player' => $highestCheckoutPlayer,
            ];
        }

        // Find most 180s
        $most180s = 0;
        $most180sPlayer = null;
        foreach ($matchPlayers as $matchPlayer) {
            if (($matchPlayer->total_180s ?? 0) > $most180s) {
                $most180s = $matchPlayer->total_180s ?? 0;
                $most180sPlayer = $matchPlayer->player->name;
            }
        }

        if ($most180s > 0) {
            $highlights[] = [
                'type' => 'most_180s',
                'description' => "{$most180s} x 180 von {$most180sPlayer}",
                'value' => $most180s,
                'player' => $most180sPlayer,
            ];
        }

        // Find highest average
        $highestAverage = 0;
        $highestAveragePlayer = null;
        foreach ($matchPlayers as $matchPlayer) {
            if (($matchPlayer->match_average ?? 0) > $highestAverage) {
                $highestAverage = $matchPlayer->match_average ?? 0;
                $highestAveragePlayer = $matchPlayer->player->name;
            }
        }

        if ($highestAverage > 0) {
            $highlights[] = [
                'type' => 'highest_average',
                'description' => "Höchster Durchschnitt: " . round($highestAverage, 2) . " von {$highestAveragePlayer}",
                'value' => round($highestAverage, 2),
                'player' => $highestAveragePlayer,
            ];
        }

        return $highlights;
    }
}

