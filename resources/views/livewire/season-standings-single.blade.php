<?php

use App\Enums\FixtureStatus;
use App\Models\MatchdayFixture;
use App\Models\MatchPlayer;
use App\Models\Season;
use App\Models\SeasonParticipant;
use App\Services\LeagueStandingsCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public Season $season;

    public function mount(Season $season): void
    {
        $this->season = $season;
    }

    public function with(): array
    {
        $user = Auth::user();
        
        if (!$user || !$user->player) {
            return [
                'data' => null,
            ];
        }

        $player = $user->player;
        $standingsCalculator = app(LeagueStandingsCalculator::class);
        
        // Prüfe, ob der User an dieser Season teilnimmt
        $participant = SeasonParticipant::where('season_id', $this->season->id)
            ->where('player_id', $player->id)
            ->with(['season.league'])
            ->first();

        if (!$participant) {
            return [
                'data' => null,
            ];
        }

        // Berechne Standings für die aktuelle Position
        $standings = $standingsCalculator->calculateStandings($this->season);
        $userPosition = $standings->search(function ($p) use ($player) {
            return $p->player_id === $player->id;
        });
        
        $currentPosition = $userPosition !== false ? $userPosition + 1 : null;

        // Gesamtanzahl Fixtures für diese Season, an denen der User teilnimmt
        $totalFixtures = MatchdayFixture::whereHas('matchday', function($q) {
            $q->where('season_id', $this->season->id);
        })
        ->where(function($query) use ($player) {
            $query->where('home_player_id', $player->id)
                ->orWhere('away_player_id', $player->id);
        })
        ->count();

        // Noch zu spielende Spiele
        $remainingFixtures = $totalFixtures - $participant->matches_played;

        // Season Average berechnen
        $seasonAverage = $this->calculateSeasonAverage($player->id, $this->season);

        // Tabellenausschnitt (2 über, User, 2 unter)
        $tableSlice = $this->getTableSlice($standings, $userPosition);

        // Nächstes Spiel finden
        $nextFixture = $this->getNextFixture($player->id, $this->season);

        // Zusätzliche Metriken
        $winLossRatio = $participant->matches_played > 0
            ? ($participant->matches_won / ($participant->matches_won + $participant->matches_lost))
            : 0;

        $legsWinLossRatio = ($participant->legs_won + $participant->legs_lost) > 0
            ? ($participant->legs_won / ($participant->legs_won + $participant->legs_lost))
            : 0;

        // Bestes Average
        $bestAverage = $this->getBestAverage($player->id, $this->season);

        // Anzahl 180er
        $total180s = $this->getTotal180s($player->id, $this->season);

        // Verlaufsdaten pro Spieltag
        $progressData = $this->calculateProgressData($player->id, $this->season, $standingsCalculator);

        return [
            'data' => [
                'season' => $this->season,
                'participant' => $participant,
                'currentPosition' => $currentPosition,
                'totalFixtures' => $totalFixtures,
                'remainingFixtures' => $remainingFixtures,
                'seasonAverage' => $seasonAverage,
                'tableSlice' => $tableSlice,
                'nextFixture' => $nextFixture,
                'winLossRatio' => $winLossRatio,
                'legsWinLossRatio' => $legsWinLossRatio,
                'bestAverage' => $bestAverage,
                'total180s' => $total180s,
                'progressData' => $progressData,
            ],
        ];
    }

    protected function calculateSeasonAverage(int $playerId, Season $season): ?float
    {
        $matchAverages = MatchPlayer::whereHas('match.fixture.matchday', function ($query) use ($season) {
            $query->where('season_id', $season->id);
        })
            ->where('player_id', $playerId)
            ->whereNotNull('match_average')
            ->pluck('match_average')
            ->filter();

        if ($matchAverages->isEmpty()) {
            return null;
        }

        return round($matchAverages->sum() / $matchAverages->count(), 2);
    }

    protected function getTableSlice($standings, ?int $userPosition): array
    {
        if ($userPosition === false || $userPosition === null) {
            return [];
        }

        $userPositionNumber = $userPosition + 1;
        $startIndex = max(0, $userPosition - 2);
        $endIndex = min($standings->count() - 1, $userPosition + 2);
        $count = $endIndex - $startIndex + 1;
        $slice = $standings->slice($startIndex, $count);
        return $slice->map(function ($participant, $originalKey) use ($userPosition) {
            $position = $originalKey + 1;
            return [
                'position' => $position,
                'participant' => $participant,
                'isCurrentUser' => $originalKey === $userPosition,
            ];
        })->values()->toArray();
    }

    protected function getNextFixture(int $playerId, Season $season): ?object
    {
        return MatchdayFixture::whereHas('matchday', function($q) use ($season) {
            $q->where('season_id', $season->id);
        })
        ->where(function($query) use ($playerId) {
            $query->where('home_player_id', $playerId)
                ->orWhere('away_player_id', $playerId);
        })
        ->where('status', FixtureStatus::Scheduled->value)
        ->whereNull('dart_match_id')
        ->with(['homePlayer', 'awayPlayer', 'matchday'])
        ->orderBy('matchday_id')
        ->first();
    }

    protected function getBestAverage(int $playerId, Season $season): ?float
    {
        $bestAverage = MatchPlayer::whereHas('match.fixture.matchday', function ($query) use ($season) {
            $query->where('season_id', $season->id);
        })
            ->where('player_id', $playerId)
            ->whereNotNull('match_average')
            ->max('match_average');

        return $bestAverage ? round($bestAverage, 2) : null;
    }

    protected function getTotal180s(int $playerId, Season $season): int
    {
        return MatchPlayer::whereHas('match.fixture.matchday', function ($query) use ($season) {
            $query->where('season_id', $season->id);
        })
            ->where('player_id', $playerId)
            ->sum('total_180s') ?? 0;
    }

    protected function calculateProgressData(int $playerId, Season $season, LeagueStandingsCalculator $standingsCalculator): array
    {
        $progressData = [];
        $cumulativeAverages = [];
        $cumulativeMatches = 0;
        $cumulativeLegsWon = 0;
        $cumulativeLegsLost = 0;

        $matchdays = $season->matchdays()
            ->with(['fixtures' => function ($query) {
                $query->where('status', FixtureStatus::Completed->value);
            }])
            ->orderBy('matchday_number')
            ->get();

        foreach ($matchdays as $matchday) {
            $userFixtures = $matchday->fixtures->filter(function ($fixture) use ($playerId) {
                return ($fixture->home_player_id === $playerId || $fixture->away_player_id === $playerId)
                    && $fixture->status === FixtureStatus::Completed->value
                    && $fixture->dart_match_id !== null;
            });

            if ($userFixtures->isEmpty()) {
                if (!empty($progressData)) {
                    break;
                }
                continue;
            }

            $matchdayLegsWon = 0;
            $matchdayLegsLost = 0;
            $matchdayAverages = [];
            
            foreach ($userFixtures as $fixture) {
                if ($fixture->dart_match_id) {
                    $matchPlayer = MatchPlayer::where('match_id', $fixture->dart_match_id)
                        ->where('player_id', $playerId)
                        ->first();

                    if ($matchPlayer && $matchPlayer->match_average) {
                        $cumulativeAverages[] = $matchPlayer->match_average;
                        $matchdayAverages[] = $matchPlayer->match_average;
                    }
                }

                $isHome = $fixture->home_player_id === $playerId;
                if ($isHome) {
                    $matchdayLegsWon += $fixture->home_legs_won ?? 0;
                    $matchdayLegsLost += $fixture->away_legs_won ?? 0;
                } else {
                    $matchdayLegsWon += $fixture->away_legs_won ?? 0;
                    $matchdayLegsLost += $fixture->home_legs_won ?? 0;
                }
            }

            $cumulativeMatches += $userFixtures->count();
            $cumulativeLegsWon += $matchdayLegsWon;
            $cumulativeLegsLost += $matchdayLegsLost;

            $seasonAverage = count($cumulativeAverages) > 0
                ? round(array_sum($cumulativeAverages) / count($cumulativeAverages), 2)
                : null;
            
            $gameAverage = count($matchdayAverages) > 0
                ? round(array_sum($matchdayAverages) / count($matchdayAverages), 2)
                : null;

            $winLossRatio = ($cumulativeLegsWon + $cumulativeLegsLost) > 0
                ? round($cumulativeLegsWon / ($cumulativeLegsWon + $cumulativeLegsLost), 3)
                : 0;

            $position = $this->calculatePositionUpToMatchday($playerId, $season, $matchday, $standingsCalculator);

            $progressData[] = [
                'matchday_number' => $matchday->matchday_number,
                'is_return_round' => $matchday->is_return_round,
                'season_average' => $seasonAverage,
                'game_average' => $gameAverage,
                'position' => $position,
                'win_loss_ratio' => $winLossRatio,
            ];
        }

        if (!empty($progressData)) {
            $standings = $standingsCalculator->calculateStandings($season);
            $userPosition = $standings->search(function ($p) use ($playerId) {
                return $p->player_id === $playerId;
            });
            $currentPosition = $userPosition !== false ? $userPosition + 1 : null;
            
            if ($currentPosition !== null) {
                $lastIndex = count($progressData) - 1;
                $progressData[$lastIndex]['position'] = $currentPosition;
            }
        }

        return $progressData;
    }

    protected function calculatePositionUpToMatchday(int $playerId, Season $season, $matchday, LeagueStandingsCalculator $standingsCalculator): ?int
    {
        // Lade alle Teilnehmer der Season
        $participants = SeasonParticipant::where('season_id', $season->id)
            ->with('player')
            ->get();

        // Berechne Stats für jeden Teilnehmer bis zu diesem Matchday
        foreach ($participants as $participant) {
            $fixtures = DB::table('matchday_fixtures')
                ->join('matchdays', 'matchday_fixtures.matchday_id', '=', 'matchdays.id')
                ->where('matchdays.season_id', $season->id)
                ->where('matchdays.matchday_number', '<=', $matchday->matchday_number)
                ->where(function ($query) use ($participant) {
                    $query->where('matchday_fixtures.home_player_id', $participant->player_id)
                        ->orWhere('matchday_fixtures.away_player_id', $participant->player_id);
                })
                ->where('matchday_fixtures.status', FixtureStatus::Completed->value)
                ->select('matchday_fixtures.*')
                ->get();

            $stats = $this->calculateStatsForFixtures($participant->player_id, $fixtures, $participant->penalty_points);
            
            // Temporäre Stats setzen
            $participant->points = $stats['points'];
            $participant->legs_won = $stats['legs_won'];
            $participant->legs_lost = $stats['legs_lost'];
        }

        // Sortiere wie in calculateStandings
        $sorted = $participants->sortBy([
            ['points', 'desc'],
            ['legs_won', 'desc'],
            ['legs_lost', 'asc'],
        ])->values();

        // Finde Position des Users
        $userIndex = $sorted->search(function ($p) use ($playerId) {
            return $p->player_id === $playerId;
        });

        return $userIndex !== false ? $userIndex + 1 : null;
    }

    protected function calculateStatsForFixtures(int $playerId, $fixtures, int $penaltyPoints): array
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

        $stats['points'] += $penaltyPoints;

        return $stats;
    }
};

?>

@if($data)
    @include('livewire.season-standings.partials.content', ['data' => $data])
@else
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:text class="text-neutral-500 dark:text-neutral-400">
            {{ __('Du nimmst nicht an dieser Saison teil.') }}
        </flux:text>
    </div>
@endif
