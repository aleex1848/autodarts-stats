<?php

namespace App\Services;

use App\Enums\FixtureStatus;
use App\Models\Season;
use App\Models\SeasonParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeagueStandingsCalculator
{
    public function calculateStandings(Season $season): Collection
    {
        $participants = $season->participants()->with('player')->get();

        foreach ($participants as $participant) {
            $this->updateSeasonParticipantStats($participant, $season);
        }

        // Sort by points (desc), then legs won (desc), then legs lost (asc)
        return $participants->sortBy([
            ['points', 'desc'],
            ['legs_won', 'desc'],
            ['legs_lost', 'asc'],
        ])->values()->map(function ($participant, $index) {
            $participant->final_position = $index + 1;
            $participant->saveQuietly();

            return $participant;
        });
    }

    public function updateSeasonParticipantStats(SeasonParticipant $participant, Season $season): void
    {
        // Get all fixtures where this participant is playing
        $fixtures = DB::table('matchday_fixtures')
            ->join('matchdays', 'matchday_fixtures.matchday_id', '=', 'matchdays.id')
            ->where('matchdays.season_id', $season->id)
            ->where(function ($query) use ($participant) {
                $query->where('matchday_fixtures.home_player_id', $participant->player_id)
                    ->orWhere('matchday_fixtures.away_player_id', $participant->player_id);
            })
            ->where('matchday_fixtures.status', FixtureStatus::Completed->value)
            ->select('matchday_fixtures.*')
            ->get();

        $this->updateStatsForSeasonParticipant($participant, $fixtures);
    }

    protected function updateStatsForSeasonParticipant(SeasonParticipant $participant, $fixtures): void
    {
        $stats = $this->calculateStats($participant->player_id, $fixtures, $participant->penalty_points);
        $participant->update($stats);
    }

    protected function calculateStats(int $playerId, $fixtures, int $penaltyPoints): array
    {
        $stats = [
            'points' => 0,
            'matches_played' => 0,
            'matches_won' => 0,
            'matches_lost' => 0,
            'matches_draw' => 0,
            'legs_won' => 0,
            'legs_lost' => 0,
        ];

        foreach ($fixtures as $fixture) {
            $stats['matches_played']++;

            $isHome = $fixture->home_player_id == $playerId;

            if ($isHome) {
                $stats['points'] += $fixture->points_awarded_home ?? 0;
                $stats['legs_won'] += $fixture->home_legs_won ?? 0;
                $stats['legs_lost'] += $fixture->away_legs_won ?? 0;

                if ($fixture->winner_player_id == $playerId) {
                    $stats['matches_won']++;
                } elseif ($fixture->winner_player_id === null) {
                    $stats['matches_draw']++;
                } else {
                    $stats['matches_lost']++;
                }
            } else {
                $stats['points'] += $fixture->points_awarded_away ?? 0;
                $stats['legs_won'] += $fixture->away_legs_won ?? 0;
                $stats['legs_lost'] += $fixture->home_legs_won ?? 0;

                if ($fixture->winner_player_id == $playerId) {
                    $stats['matches_won']++;
                } elseif ($fixture->winner_player_id === null) {
                    $stats['matches_draw']++;
                } else {
                    $stats['matches_lost']++;
                }
            }
        }

        // Subtract penalty points
        $stats['points'] -= $penaltyPoints;

        return $stats;
    }

    public function checkForTiebreaker(Season $season): ?array
    {
        $standings = $this->calculateStandings($season);

        // Check if season is completed
        $allMatchesPlayed = $season->matchdays()
            ->where('is_playoff', false)
            ->get()
            ->every(function ($matchday) {
                return $matchday->fixtures()
                    ->where('status', '!=', FixtureStatus::Completed->value)
                    ->count() === 0;
            });

        if (! $allMatchesPlayed) {
            return null;
        }

        // Check for ties at the top position
        $topStanding = $standings->first();
        if (! $topStanding) {
            return null;
        }

        $tiedPlayers = $standings->filter(function ($participant) use ($topStanding) {
            return $participant->points === $topStanding->points
                && $participant->legs_won === $topStanding->legs_won
                && $participant->legs_lost === $topStanding->legs_lost;
        });

        if ($tiedPlayers->count() > 1) {
            return $tiedPlayers->pluck('player_id')->toArray();
        }

        return null;
    }
}
