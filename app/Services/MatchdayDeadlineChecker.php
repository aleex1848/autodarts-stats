<?php

namespace App\Services;

use App\Enums\FixtureStatus;
use App\Models\MatchdayFixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MatchdayDeadlineChecker
{
    public function checkOverdueFixtures(): Collection
    {
        $overdueFixtures = MatchdayFixture::query()
            ->join('matchdays', 'matchday_fixtures.matchday_id', '=', 'matchdays.id')
            ->where('matchday_fixtures.status', FixtureStatus::Scheduled->value)
            ->where('matchdays.deadline_at', '<', now())
            ->whereNotNull('matchdays.deadline_at')
            ->select('matchday_fixtures.*')
            ->get();

        foreach ($overdueFixtures as $fixture) {
            $this->applyPenalty($fixture);
        }

        return $overdueFixtures;
    }

    public function applyPenalty(MatchdayFixture $fixture): void
    {
        $matchday = $fixture->matchday;
        $league = $matchday->league;

        // Mark fixture as overdue
        $fixture->update(['status' => FixtureStatus::Overdue->value]);

        // Apply penalty to both players
        $homeParticipant = $league->participants()
            ->where('player_id', $fixture->home_player_id)
            ->first();
            
        $awayParticipant = $league->participants()
            ->where('player_id', $fixture->away_player_id)
            ->first();

        if ($homeParticipant) {
            $homeParticipant->increment('penalty_points', 1);
        }

        if ($awayParticipant) {
            $awayParticipant->increment('penalty_points', 1);
        }
    }
}

