<?php

namespace App\Services;

use App\Enums\FixtureStatus;
use App\Enums\LeagueMode;
use App\Models\League;
use App\Models\Matchday;
use App\Models\MatchdayFixture;
use Illuminate\Support\Collection;

class LeagueScheduler
{
    public function generateMatchdays(League $league, Collection $participants): void
    {
        $playerIds = $participants->pluck('player_id')->toArray();
        $playerCount = count($playerIds);

        if ($playerCount < 2) {
            throw new \InvalidArgumentException('Es werden mindestens 2 Spieler benötigt.');
        }

        // Generate first round
        $this->generateRound($league, $playerIds, false);

        // Generate return round if double round mode
        if ($league->mode === LeagueMode::DoubleRound->value) {
            $this->generateReturnRound($league);
        }
    }

    protected function generateRound(League $league, array $playerIds, bool $isReturnRound): void
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
                'league_id' => $league->id,
                'matchday_number' => $matchdayNumber,
                'is_return_round' => $isReturnRound,
                'deadline_at' => $this->calculateDeadline($league, $matchdayNumber),
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

    public function generateReturnRound(League $league): void
    {
        // Get all participants
        $participants = $league->participants;
        $playerIds = $participants->pluck('player_id')->toArray();

        $this->generateRound($league, $playerIds, true);
    }

    public function generatePlayoffMatchday(League $league, array $players): Matchday
    {
        $lastMatchday = $league->matchdays()->orderByDesc('matchday_number')->first();
        $matchdayNumber = $lastMatchday ? $lastMatchday->matchday_number + 1 : 1;

        $matchday = Matchday::create([
            'league_id' => $league->id,
            'matchday_number' => $matchdayNumber,
            'is_return_round' => false,
            'deadline_at' => $this->calculateDeadline($league, $matchdayNumber),
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

    protected function calculateDeadline(League $league, int $matchdayNumber): ?\DateTime
    {
        if (!$league->days_per_matchday) {
            return null;
        }

        $startDate = $league->registration_deadline ?? now();
        
        return (clone $startDate)->modify("+{$matchdayNumber} weeks");
    }
}

