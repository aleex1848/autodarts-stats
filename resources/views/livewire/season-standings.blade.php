<?php

use App\Enums\FixtureStatus;
use App\Models\MatchdayFixture;
use App\Models\MatchPlayer;
use App\Models\SeasonParticipant;
use App\Services\LeagueStandingsCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $user = Auth::user();
        
        if (!$user || !$user->player) {
            return [
                'seasons' => collect(),
            ];
        }

        $player = $user->player;
        $standingsCalculator = app(LeagueStandingsCalculator::class);

        // Lade alle Seasons, an denen der User teilnimmt
        $participants = SeasonParticipant::where('player_id', $player->id)
            ->with([
                'season.league',
                'season.matchdays.fixtures' => function ($query) {
                    $query->where('status', FixtureStatus::Completed->value);
                },
            ])
            ->get();

        $seasons = $participants->map(function ($participant) use ($player, $standingsCalculator) {
            $season = $participant->season;
            
            // Berechne Standings für die aktuelle Position
            $standings = $standingsCalculator->calculateStandings($season);
            $userPosition = $standings->search(function ($p) use ($player) {
                return $p->player_id === $player->id;
            });
            
            $currentPosition = $userPosition !== false ? $userPosition + 1 : null;

            // Gesamtanzahl Fixtures für diese Season, an denen der User teilnimmt
            $totalFixtures = \App\Models\MatchdayFixture::whereHas('matchday', function($q) use ($season) {
                $q->where('season_id', $season->id);
            })
            ->where(function($query) use ($player) {
                $query->where('home_player_id', $player->id)
                    ->orWhere('away_player_id', $player->id);
            })
            ->count();

            // Noch zu spielende Spiele
            $remainingFixtures = $totalFixtures - $participant->matches_played;

            // Season Average berechnen
            $seasonAverage = $this->calculateSeasonAverage($player->id, $season);

            // Tabellenausschnitt (2 über, User, 2 unter)
            $tableSlice = $this->getTableSlice($standings, $userPosition);

            // Nächstes Spiel finden
            $nextFixture = $this->getNextFixture($player->id, $season);

            // Zusätzliche Metriken
            $winLossRatio = $participant->matches_played > 0
                ? ($participant->matches_won / ($participant->matches_won + $participant->matches_lost))
                : 0;

            $legsWinLossRatio = ($participant->legs_won + $participant->legs_lost) > 0
                ? ($participant->legs_won / ($participant->legs_won + $participant->legs_lost))
                : 0;

            // Bestes Average
            $bestAverage = $this->getBestAverage($player->id, $season);

            // Anzahl 180er
            $total180s = $this->getTotal180s($player->id, $season);

            // Verlaufsdaten pro Spieltag
            $progressData = $this->calculateProgressData($player->id, $season, $standingsCalculator);
            

            return [
                'season' => $season,
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
            ];
        })->filter(function ($data) {
            // Nur aktive oder abgeschlossene Seasons anzeigen
            return in_array($data['season']->status, ['active', 'completed']);
        })->sortByDesc(function ($data) {
            // Sortiere nach Season-Status (active zuerst) und dann nach Name
            return [$data['season']->status === 'active' ? 0 : 1, $data['season']->name];
        })->values();

        return [
            'seasons' => $seasons,
        ];
    }

    protected function calculateSeasonAverage(int $playerId, $season): ?float
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

        // $userPosition ist der Index (0-basiert) aus search()
        // Position ist Index + 1 (1-basiert)
        $userPositionNumber = $userPosition + 1;
        
        // Berechne Start- und End-Index (0-basiert) für den Slice
        // Zeige 2 Positionen über und 2 unter dem User
        $startIndex = max(0, $userPosition - 2);
        $endIndex = min($standings->count() - 1, $userPosition + 2);
        $count = $endIndex - $startIndex + 1;

        // Hole den Slice aus den Standings
        // slice() behält die ursprünglichen Collection-Keys bei!
        $slice = $standings->slice($startIndex, $count);

        // Mappe die Teilnehmer mit korrekten Positionen
        // Wichtig: Der Key im Slice ist der ursprüngliche Index, nicht der Index im Slice
        // Position ist Key + 1 (weil Keys 0-basiert sind, Positionen 1-basiert)
        return $slice->map(function ($participant, $originalKey) use ($userPosition) {
            // Position ist Key + 1 (weil Keys 0-basiert sind)
            $position = $originalKey + 1;
            return [
                'position' => $position,
                'participant' => $participant,
                'isCurrentUser' => $originalKey === $userPosition,
            ];
        })
        ->values()
        ->toArray();
    }

    protected function getNextFixture(int $playerId, $season): ?object
    {
        return DB::table('matchday_fixtures')
            ->join('matchdays', 'matchday_fixtures.matchday_id', '=', 'matchdays.id')
            ->where('matchdays.season_id', $season->id)
            ->where(function ($query) use ($playerId) {
                $query->where('matchday_fixtures.home_player_id', $playerId)
                    ->orWhere('matchday_fixtures.away_player_id', $playerId);
            })
            ->where('matchday_fixtures.status', FixtureStatus::Scheduled->value)
            ->whereNull('matchday_fixtures.dart_match_id')
            ->select('matchday_fixtures.*', 'matchdays.matchday_number')
            ->orderBy('matchdays.matchday_number')
            ->first();
    }

    protected function getBestAverage(int $playerId, $season): ?float
    {
        $bestAverage = MatchPlayer::whereHas('match.fixture.matchday', function ($query) use ($season) {
            $query->where('season_id', $season->id);
        })
            ->where('player_id', $playerId)
            ->whereNotNull('match_average')
            ->max('match_average');

        return $bestAverage ? round($bestAverage, 2) : null;
    }

    protected function getTotal180s(int $playerId, $season): int
    {
        return MatchPlayer::whereHas('match.fixture.matchday', function ($query) use ($season) {
            $query->where('season_id', $season->id);
        })
            ->where('player_id', $playerId)
            ->sum('total_180s') ?? 0;
    }

    protected function calculateProgressData(int $playerId, $season, LeagueStandingsCalculator $standingsCalculator): array
    {
        $matchdays = $season->matchdays()
            ->orderBy('matchday_number')
            ->orderBy('is_return_round')
            ->with(['fixtures' => function ($query) {
                $query->where('status', FixtureStatus::Completed->value);
            }])
            ->get();

        $progressData = [];
        $cumulativeMatches = 0;
        $cumulativeLegsWon = 0;
        $cumulativeLegsLost = 0;
        $cumulativeAverages = [];

        foreach ($matchdays as $matchday) {
            // Fixtures des Users für diesen Spieltag (nur abgeschlossene, die der User gespielt hat)
            $userFixtures = $matchday->fixtures->filter(function ($fixture) use ($playerId) {
                return ($fixture->home_player_id === $playerId || $fixture->away_player_id === $playerId)
                    && $fixture->status === FixtureStatus::Completed->value
                    && $fixture->dart_match_id !== null;
            });

            // Wenn der User keine abgeschlossenen Spiele an diesem Spieltag hat
            if ($userFixtures->isEmpty()) {
                // Wenn wir bereits Daten haben, ist dies der letzte Spieltag, an dem der User gespielt hat
                // Stoppe hier - keine weiteren Datenpunkte hinzufügen
                if (!empty($progressData)) {
                    break;
                }
                // Wenn noch keine Daten vorhanden sind, überspringe diesen Spieltag
                continue;
            }

            // Match-Averages und Legs für diesen Spieltag
            $matchdayLegsWon = 0;
            $matchdayLegsLost = 0;
            $matchdayAverages = []; // Game Averages für diesen Spieltag
            
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

                // Zähle Legs für Win/Loss Ratio
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

            // Season Average bis zu diesem Spieltag (kumulativ)
            $seasonAverage = count($cumulativeAverages) > 0
                ? round(array_sum($cumulativeAverages) / count($cumulativeAverages), 2)
                : null;
            
            // Game Average für diesen Spieltag (Durchschnitt der Averages der Spiele an diesem Spieltag)
            $gameAverage = count($matchdayAverages) > 0
                ? round(array_sum($matchdayAverages) / count($matchdayAverages), 2)
                : null;

            // Win/Loss Ratio für Legs bis zu diesem Spieltag
            $winLossRatio = ($cumulativeLegsWon + $cumulativeLegsLost) > 0
                ? round($cumulativeLegsWon / ($cumulativeLegsWon + $cumulativeLegsLost), 3)
                : 0;

            // Position nach diesem Spieltag berechnen
            // Erstelle temporäre Season mit nur bis zu diesem Spieltag abgeschlossenen Fixtures
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

        // Füge die aktuelle Position hinzu, damit sie im Diagramm angezeigt wird
        // Dies ist wichtig, da die aktuelle Position möglicherweise anders ist als die Position
        // nach dem letzten Spieltag, an dem der User gespielt hat (z.B. durch Spiele anderer Spieler)
        if (!empty($progressData)) {
            // Berechne die aktuelle Position basierend auf allen abgeschlossenen Fixtures
            $standings = $standingsCalculator->calculateStandings($season);
            $userPosition = $standings->search(function ($p) use ($playerId) {
                return $p->player_id === $playerId;
            });
            $currentPosition = $userPosition !== false ? $userPosition + 1 : null;
            
            if ($currentPosition !== null) {
                // Ersetze IMMER den letzten Eintrag mit der aktuellen Position
                // Das stellt sicher, dass die neueste Position im Diagramm angezeigt wird
                // Verwende die gleiche Spieltag-Nummer wie der letzte Eintrag
                $lastIndex = count($progressData) - 1;
                // Überschreibe die Position direkt im Array
                // Wichtig: Direkte Zuweisung, nicht array_merge, um sicherzustellen, dass es funktioniert
                if ($lastIndex >= 0) {
                    $progressData[$lastIndex]['position'] = $currentPosition;
                }
            }
        }

        return $progressData;
    }

    protected function calculatePositionUpToMatchday(int $playerId, $season, $matchday, LeagueStandingsCalculator $standingsCalculator): ?int
    {
        // Hole alle Teilnehmer
        $participants = $season->participants()->with('player')->get();

        // Berechne Stats nur für Fixtures bis zu diesem Spieltag
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
        // Wichtig: sortBy mit mehreren Kriterien erstellt eine Collection mit Indizes
        // Wir müssen values() verwenden, um die Indizes neu zu setzen
        $sorted = $participants->sortBy([
            ['points', 'desc'],
            ['legs_won', 'desc'],
            ['legs_lost', 'asc'],
        ])->values();

        // Finde Position des Users
        // search() gibt den Index zurück (0-basiert), Position ist Index + 1
        $userIndex = $sorted->search(function ($p) use ($playerId) {
            return $p->player_id === $playerId;
        });

        // Position ist Index + 1 (1-basiert)
        // Wenn nicht gefunden, return null
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

        $stats['points'] -= $penaltyPoints;

        return $stats;
    }
}; ?>

@if($seasons->isEmpty())
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">
            {{ __('Du nimmst aktuell an keiner aktiven Saison teil.') }}
        </p>
    </div>
@else
    <div class="space-y-6">
        @foreach($seasons as $data)
            @include('livewire.season-standings.partials.content', ['data' => $data])
        @endforeach
    </div>
@endif
