<?php

use App\Models\SeasonParticipant;
use App\Services\LeagueStandingsCalculator;
use Illuminate\Support\Facades\Auth;

new class extends \Livewire\Volt\Component {
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
            ->with(['season.league'])
            ->get();

        $seasons = $participants->map(function ($participant) use ($player, $standingsCalculator) {
            $season = $participant->season;
            
            // Berechne Standings für die aktuelle Position
            $standings = $standingsCalculator->calculateStandings($season);
            $userPosition = $standings->search(function ($p) use ($player) {
                return $p->player_id === $player->id;
            });
            
            $currentPosition = $userPosition !== false ? $userPosition + 1 : null;
            $totalPlayers = $standings->count();

            // Season Average berechnen
            $seasonAverage = $this->calculateSeasonAverage($player->id, $season);

            return [
                'season' => $season,
                'participant' => $participant,
                'currentPosition' => $currentPosition,
                'totalPlayers' => $totalPlayers,
                'seasonAverage' => $seasonAverage,
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
        $matchAverages = \App\Models\MatchPlayer::whereHas('match.fixture.matchday', function ($query) use ($season) {
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
};

?>

@if($seasons->isNotEmpty())
    <div class="space-y-4">
        @foreach($seasons as $data)
            <div class="relative h-full overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
                <div class="flex h-full flex-col">
                    {{-- Header --}}
                    <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-zinc-800">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                                    {{ $data['season']->league->name }} · {{ $data['season']->name }}
                                </h3>
                            </div>
                            <flux:button 
                                variant="ghost" 
                                size="sm"
                                :href="route('seasons.show', $data['season']) . '?activeTab=overview'"
                            >
                                {{ __('Details anzeigen') }}
                                <flux:icon icon="arrow-right" class="ms-2" />
                            </flux:button>
                        </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 p-6">
                        <div class="flex flex-wrap items-start gap-6">
                            {{-- Aktuelle Position --}}
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                                    {{ __('Position') }}
                                </flux:text>
                                <flux:heading size="lg" class="mt-1">
                                    @if($data['currentPosition'])
                                        {{ $data['currentPosition'] }} / {{ $data['totalPlayers'] }}
                                    @else
                                        -
                                    @endif
                                </flux:heading>
                            </div>

                            {{-- Aktuelle Punkte --}}
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                                    {{ __('Punkte') }}
                                </flux:text>
                                <flux:heading size="lg" class="mt-1">
                                    {{ $data['participant']->points }}
                                </flux:heading>
                            </div>

                            {{-- Siege --}}
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                                    {{ __('Siege') }}
                                </flux:text>
                                <flux:heading size="lg" class="mt-1">
                                    {{ $data['participant']->matches_won }}
                                </flux:heading>
                            </div>

                            {{-- Season-Average --}}
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                                    {{ __('Season-Average') }}
                                </flux:text>
                                <flux:heading size="lg" class="mt-1">
                                    @if($data['seasonAverage'])
                                        {{ number_format($data['seasonAverage'], 2) }}
                                    @else
                                        -
                                    @endif
                                </flux:heading>
                            </div>

                            {{-- Legs --}}
                            <div class="flex-1 min-w-0">
                                <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                                    {{ __('Legs') }}
                                </flux:text>
                                <flux:heading size="lg" class="mt-1">
                                    {{ $data['participant']->legs_won }}:{{ $data['participant']->legs_lost }}
                                </flux:heading>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
