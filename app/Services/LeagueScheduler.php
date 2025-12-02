<?php

namespace App\Services;

use App\Enums\FixtureStatus;
use App\Enums\LeagueMode;
use App\Models\Season;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use Illuminate\Support\Collection;

class LeagueScheduler
{
    public function generateMatchdays(Season $season, Collection $participants): void
    {
        $playerIds = $participants->pluck('player_id')->toArray();
        $playerCount = count($playerIds);

        if ($playerCount < 2) {
            throw new \InvalidArgumentException('Es werden mindestens 2 Spieler benötigt.');
        }

        // Generate first round
        $this->generateRound($season, $playerIds, false);

        // Generate return round if double round mode
        if ($season->mode === LeagueMode::DoubleRound->value) {
            $this->generateReturnRound($season);
        }
    }

    protected function generateRound(Season $season, array $playerIds, bool $isReturnRound): void
    {
        $playerCount = count($playerIds);

        // If odd number of players, add a "bye" (null)
        if ($playerCount % 2 !== 0) {
            $playerIds[] = null;
            $playerCount++;
        }

        $rounds = $playerCount - 1;
        $matchesPerRound = $playerCount / 2;

        // Round-robin algorithm (Circle method)
        for ($round = 0; $round < $rounds; $round++) {
            $matchdayNumber = $isReturnRound ? $rounds + $round + 1 : $round + 1;

            $matchday = Matchday::create([
                'season_id' => $season->id,
                'matchday_number' => $matchdayNumber,
                'is_return_round' => $isReturnRound,
                'deadline_at' => $this->calculateDeadline($season, $matchdayNumber),
                'is_playoff' => false,
            ]);

            for ($match = 0; $match < $matchesPerRound; $match++) {
                $home = ($round + $match) % ($playerCount - 1);
                $away = ($playerCount - 1 - $match + $round) % ($playerCount - 1);

                // Last player stays in the same place
                if ($match == 0) {
                    $away = $playerCount - 1;
                }

                // Skip if one of the players is a "bye"
                if ($playerIds[$home] === null || $playerIds[$away] === null) {
                    continue;
                }

                // In return round, swap home and away
                if ($isReturnRound) {
                    [$home, $away] = [$away, $home];
                }

                MatchdayFixture::create([
                    'matchday_id' => $matchday->id,
                    'home_player_id' => $playerIds[$home],
                    'away_player_id' => $playerIds[$away],
                    'status' => FixtureStatus::Scheduled->value,
                ]);
            }
        }
    }

    public function generateReturnRound(Season $season): void
    {
        // Get all participants
        $participants = $season->participants;
        $playerIds = $participants->pluck('player_id')->toArray();

        $this->generateRound($season, $playerIds, true);
    }

    public function generatePlayoffMatchday(Season $season, array $players): Matchday
    {
        $lastMatchday = $season->matchdays()->orderByDesc('matchday_number')->first();
        $matchdayNumber = $lastMatchday ? $lastMatchday->matchday_number + 1 : 1;

        $matchday = Matchday::create([
            'season_id' => $season->id,
            'matchday_number' => $matchdayNumber,
            'is_return_round' => false,
            'deadline_at' => $this->calculateDeadline($season, $matchdayNumber),
            'is_playoff' => true,
        ]);

        // Create fixture between the tied players
        for ($i = 0; $i < count($players); $i += 2) {
            if (isset($players[$i + 1])) {
                MatchdayFixture::create([
                    'matchday_id' => $matchday->id,
                    'home_player_id' => $players[$i],
                    'away_player_id' => $players[$i + 1],
                    'status' => FixtureStatus::Scheduled->value,
                ]);
            }
        }

        return $matchday;
    }

    protected function calculateDeadline(Season $season, int $matchdayNumber): ?\DateTime
    {
        if (! $season->days_per_matchday) {
            return null;
        }

        $startDate = $season->registration_deadline ?? now();

        return (clone $startDate)->modify("+{$matchdayNumber} weeks");
    }
}
