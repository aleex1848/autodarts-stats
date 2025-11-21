<?php

namespace App\Services;

use App\Enums\FixtureStatus;
use App\Models\League;
use App\Models\LeagueParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeagueStandingsCalculator
{
    public function calculateStandings(League $league): Collection
    {
        $participants = $league->participants()->with('player')->get();

        foreach ($participants as $participant) {
            $this->updateParticipantStats($participant);
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

    public function updateParticipantStats(LeagueParticipant $participant): void
    {
        $league = $participant->league;
        
        // Get all fixtures where this participant is playing
        $fixtures = DB::table('matchday_fixtures')
            ->join('matchdays', 'matchday_fixtures.matchday_id', '=', 'matchdays.id')
            ->where('matchdays.league_id', $league->id)
            ->where(function ($query) use ($participant) {
                $query->where('matchday_fixtures.home_player_id', $participant->player_id)
                    ->orWhere('matchday_fixtures.away_player_id', $participant->player_id);
            })
            ->where('matchday_fixtures.status', FixtureStatus::Completed->value)
            ->select('matchday_fixtures.*')
            ->get();

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

            $isHome = $fixture->home_player_id == $participant->player_id;
            
            if ($isHome) {
                $stats['points'] += $fixture->points_awarded_home;
                $stats['legs_won'] += $fixture->home_legs_won ?? 0;
                $stats['legs_lost'] += $fixture->away_legs_won ?? 0;
                
                if ($fixture->winner_player_id == $participant->player_id) {
                    $stats['matches_won']++;
                } elseif ($fixture->winner_player_id === null) {
                    $stats['matches_draw']++;
                } else {
                    $stats['matches_lost']++;
                }
            } else {
                $stats['points'] += $fixture->points_awarded_away;
                $stats['legs_won'] += $fixture->away_legs_won ?? 0;
                $stats['legs_lost'] += $fixture->home_legs_won ?? 0;
                
                if ($fixture->winner_player_id == $participant->player_id) {
                    $stats['matches_won']++;
                } elseif ($fixture->winner_player_id === null) {
                    $stats['matches_draw']++;
                } else {
                    $stats['matches_lost']++;
                }
            }
        }

        // Subtract penalty points
        $stats['points'] -= $participant->penalty_points;

        $participant->update($stats);
    }

    public function checkForTiebreaker(League $league): ?array
    {
        $standings = $this->calculateStandings($league);
        
        // Check if league is completed
        $allMatchesPlayed = $league->matchdays()
            ->where('is_playoff', false)
            ->get()
            ->every(function ($matchday) {
                return $matchday->fixtures()
                    ->where('status', '!=', FixtureStatus::Completed->value)
                    ->count() === 0;
            });

        if (!$allMatchesPlayed) {
            return null;
        }

        // Check for ties at the top position
        $topStanding = $standings->first();
        if (!$topStanding) {
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

