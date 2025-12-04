<?php

namespace App\Services;

use App\Enums\FixtureStatus;
use App\Models\Matchday;
use App\Services\LeagueStandingsCalculator;

class MatchdayDataExtractor
{
    public function __construct(
        protected LeagueStandingsCalculator $standingsCalculator
    ) {
    }

    public function extract(Matchday $matchday): array
    {
        $matchday->load([
            'season.league',
            'fixtures.homePlayer',
            'fixtures.awayPlayer',
            'fixtures.dartMatch.players',
            'fixtures.dartMatch.matchPlayers.player',
            'fixtures.dartMatch.legs.winner',
        ]);

        $season = $matchday->season;

        // Get all fixtures with results
        $fixtures = $matchday->fixtures()->with([
            'homePlayer',
            'awayPlayer',
            'dartMatch.matchPlayers.player',
            'dartMatch.legs.winner',
        ])->get();

        $completedFixtures = $fixtures->filter(fn ($fixture) => $fixture->status === FixtureStatus::Completed->value);

        // Extract match results
        $matchResults = [];
        $highlights = [];

        foreach ($completedFixtures as $fixture) {
            $homePlayer = $fixture->homePlayer;
            $awayPlayer = $fixture->awayPlayer;
            $winner = $fixture->winner;

            $matchResult = [
                'home_player' => $homePlayer?->name ?? 'Unbekannt',
                'away_player' => $awayPlayer?->name ?? 'Unbekannt',
                'home_legs_won' => $fixture->home_legs_won ?? 0,
                'away_legs_won' => $fixture->away_legs_won ?? 0,
                'winner' => $winner?->name ?? 'Unbekannt',
            ];

            $matchResults[] = $matchResult;

            // Check for surprise wins (underdog wins)
            if ($fixture->dartMatch) {
                $matchPlayers = $fixture->dartMatch->matchPlayers()->with('player')->get();
                foreach ($matchPlayers as $matchPlayer) {
                    if ($matchPlayer->player_id === $winner?->id) {
                        // Check for high finishes
                        if (($matchPlayer->best_checkout_points ?? 0) >= 100) {
                            $highlights[] = [
                                'type' => 'high_checkout',
                                'match' => "{$homePlayer?->name} vs {$awayPlayer?->name}",
                                'player' => $matchPlayer->player->name,
                                'value' => $matchPlayer->best_checkout_points,
                                'description' => "High Finish von {$matchPlayer->best_checkout_points} Punkten",
                            ];
                        }

                        // Check for many 180s
                        if (($matchPlayer->total_180s ?? 0) >= 3) {
                            $highlights[] = [
                                'type' => 'many_180s',
                                'match' => "{$homePlayer?->name} vs {$awayPlayer?->name}",
                                'player' => $matchPlayer->player->name,
                                'value' => $matchPlayer->total_180s,
                                'description' => "{$matchPlayer->total_180s} x 180 geworfen",
                            ];
                        }
                    }
                }
            }
        }

        // Get standings before and after matchday
        $standingsBefore = $this->getStandingsBeforeMatchday($season, $matchday);
        $standingsAfter = $this->standingsCalculator->calculateStandings($season);

        // Find table changes
        $tableChanges = $this->calculateTableChanges($standingsBefore, $standingsAfter);

        return [
            'matchday_info' => [
                'matchday_number' => $matchday->matchday_number,
                'is_return_round' => $matchday->is_return_round,
                'deadline_at' => $matchday->deadline_at?->format('d.m.Y'),
                'total_fixtures' => $fixtures->count(),
                'completed_fixtures' => $completedFixtures->count(),
            ],
            'season_info' => [
                'league_name' => $season->league->name,
                'season_name' => $season->name,
            ],
            'match_results' => $matchResults,
            'highlights' => $highlights,
            'table_changes' => $tableChanges,
        ];
    }

    protected function getStandingsBeforeMatchday($season, Matchday $matchday): array
    {
        // Get all completed matchdays before this one
        $previousMatchdays = $season->matchdays()
            ->where('matchday_number', '<', $matchday->matchday_number)
            ->where('is_return_round', $matchday->is_return_round)
            ->orderBy('matchday_number')
            ->get();

        // Calculate standings based on previous matchdays
        $participants = $season->participants()->with('player')->get();
        $standings = [];

        foreach ($participants as $participant) {
            $points = 0;
            $legsWon = 0;
            $legsLost = 0;

            foreach ($previousMatchdays as $prevMatchday) {
                $fixtures = $prevMatchday->fixtures()
                    ->where('status', FixtureStatus::Completed->value)
                    ->get();

                foreach ($fixtures as $fixture) {
                    $isHome = $fixture->home_player_id === $participant->player_id;
                    $isAway = $fixture->away_player_id === $participant->player_id;

                    if ($isHome) {
                        $points += $fixture->points_awarded_home ?? 0;
                        $legsWon += $fixture->home_legs_won ?? 0;
                        $legsLost += $fixture->away_legs_won ?? 0;
                    } elseif ($isAway) {
                        $points += $fixture->points_awarded_away ?? 0;
                        $legsWon += $fixture->away_legs_won ?? 0;
                        $legsLost += $fixture->home_legs_won ?? 0;
                    }
                }
            }

            $standings[] = [
                'player_id' => $participant->player_id,
                'player_name' => $participant->player?->name ?? 'Unbekannt',
                'points' => $points,
                'legs_won' => $legsWon,
                'legs_lost' => $legsLost,
            ];
        }

        // Sort by points, then legs_won, then legs_lost
        usort($standings, function ($a, $b) {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['legs_won'] !== $b['legs_won']) {
                return $b['legs_won'] <=> $a['legs_won'];
            }
            return $a['legs_lost'] <=> $b['legs_lost'];
        });

        return $standings;
    }

    protected function calculateTableChanges(array $standingsBefore, $standingsAfter): array
    {
        $changes = [];

        foreach ($standingsAfter as $index => $participant) {
            $playerId = $participant->player_id;
            $playerName = $participant->player?->name ?? 'Unbekannt';

            // Find position in before standings
            $beforeIndex = null;
            foreach ($standingsBefore as $idx => $standing) {
                if ($standing['player_id'] === $playerId) {
                    $beforeIndex = $idx;
                    break;
                }
            }

            $currentPosition = $index + 1;
            $previousPosition = $beforeIndex !== null ? $beforeIndex + 1 : null;

            if ($previousPosition !== null && $currentPosition !== $previousPosition) {
                $positionChange = $previousPosition - $currentPosition; // Positive = moved up, Negative = moved down

                $changes[] = [
                    'player_name' => $playerName,
                    'previous_position' => $previousPosition,
                    'current_position' => $currentPosition,
                    'position_change' => $positionChange,
                    'points' => $participant->points,
                    'legs_won' => $participant->legs_won,
                    'legs_lost' => $participant->legs_lost,
                ];
            }
        }

        return $changes;
    }
}

