<?php

namespace App\Actions;

use App\Enums\FixtureStatus;
use App\Models\DartMatch;
use App\Models\MatchdayFixture;
use App\Services\LeagueStandingsCalculator;

class AssignMatchToFixture
{
    public function __construct(
        protected LeagueStandingsCalculator $standingsCalculator
    ) {}

    public function handle(DartMatch $match, MatchdayFixture $fixture): bool
    {
        // Validate that the match is finished
        if ($match->finished_at === null) {
            throw new \InvalidArgumentException('Das Match ist noch nicht beendet.');
        }

        // Validate that both players from the fixture are in the match
        $playerIds = $match->players->pluck('id')->toArray();

        if (! in_array($fixture->home_player_id, $playerIds) || ! in_array($fixture->away_player_id, $playerIds)) {
            throw new \InvalidArgumentException('Die Spieler des Matches stimmen nicht mit dem Fixture überein.');
        }

        // Get legs won for each player
        $homePlayer = $match->players->firstWhere('id', $fixture->home_player_id);
        $awayPlayer = $match->players->firstWhere('id', $fixture->away_player_id);

        if (! $homePlayer || ! $awayPlayer) {
            throw new \InvalidArgumentException('Spieler nicht im Match gefunden.');
        }

        $homeLegsWon = $homePlayer->pivot->legs_won ?? 0;
        $awayLegsWon = $awayPlayer->pivot->legs_won ?? 0;

        // Determine winner and award points (3 points for win, 1 for draw, 0 for loss)
        $winnerId = null;
        $homePoints = 0;
        $awayPoints = 0;

        if ($homeLegsWon > $awayLegsWon) {
            $winnerId = $fixture->home_player_id;
            $homePoints = 3;
        } elseif ($awayLegsWon > $homeLegsWon) {
            $winnerId = $fixture->away_player_id;
            $awayPoints = 3;
        } else {
            // Draw
            $homePoints = 1;
            $awayPoints = 1;
        }

        // Update fixture
        $fixture->update([
            'dart_match_id' => $match->id,
            'status' => FixtureStatus::Completed->value,
            'home_legs_won' => $homeLegsWon,
            'away_legs_won' => $awayLegsWon,
            'winner_player_id' => $winnerId,
            'points_awarded_home' => $homePoints,
            'points_awarded_away' => $awayPoints,
            'played_at' => $match->finished_at,
        ]);

        // Update participant stats for Season
        $season = $fixture->matchday->season;

        $homeParticipant = $season->participants()
            ->where('player_id', $fixture->home_player_id)
            ->first();

        $awayParticipant = $season->participants()
            ->where('player_id', $fixture->away_player_id)
            ->first();

        if ($homeParticipant) {
            $this->standingsCalculator->updateSeasonParticipantStats($homeParticipant, $season);
        }

        if ($awayParticipant) {
            $this->standingsCalculator->updateSeasonParticipantStats($awayParticipant, $season);
        }

        // Recalculate standings for Season
        $this->standingsCalculator->calculateStandings($season);

        return true;
    }
}
