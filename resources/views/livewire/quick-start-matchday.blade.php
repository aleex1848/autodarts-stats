<?php

use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $playingMatchdayId = null;
    public ?string $message = null;
    public bool $success = false;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    public function getListeners(): array
    {
        return [
            "echo-private:user." . Auth::id() . ",.matchday.game.started" => 'handleMatchdayGameStarted',
        ];
    }

    public function refreshStatus(): void
    {
        $user = Auth::user();
        $this->playingMatchdayId = $user->playing_matchday_id;
    }

    public function handleMatchdayGameStarted(array $data): void
    {
        $this->playingMatchdayId = $data['playing_matchday_id'] ?? null;
        $this->success = $data['success'] ?? false;
        $this->message = $data['message'] ?? null;

        Auth::user()->refresh();
        $this->refreshStatus();
    }

    public function startMatchday(int $matchdayId): void
    {
        $user = Auth::user();
        $matchday = Matchday::with('season')->find($matchdayId);

        if (! $matchday) {
            $this->addError('matchday', 'Spieltag nicht gefunden.');
            return;
        }

        // Validate user is participant of the season
        if (! $user->player) {
            $this->addError('matchday', 'Du musst zuerst einen Spieler mit deinem Account verknüpfen.');
            return;
        }

        $isParticipant = $matchday->season->participants()
            ->where('player_id', $user->player->id)
            ->exists();

        if (! $isParticipant) {
            $this->addError('matchday', 'Du bist kein Teilnehmer dieser Saison.');
            return;
        }

        // Validate matchday is relevant (not past)
        if (! $matchday->isCurrentlyActive() && ! $matchday->isUpcoming()) {
            $this->addError('matchday', 'Dieser Spieltag ist bereits vorbei.');
            return;
        }

        // If order is required, check if all previous matchdays are complete
        if ($matchday->season->requiresMatchdayOrder()) {
            $previousMatchdays = $matchday->season->matchdays()
                ->where('matchday_number', '<', $matchday->matchday_number)
                ->where('is_return_round', $matchday->is_return_round)
                ->get();

            $incompleteMatchdays = $previousMatchdays->filter(function ($previousMatchday) {
                return ! $previousMatchday->isComplete();
            });

            if ($incompleteMatchdays->isNotEmpty()) {
                $incompleteNumbers = $incompleteMatchdays->pluck('matchday_number')->join(', ');
                $this->addError('matchday', "Spieltag {$matchday->matchday_number} kann erst gestartet werden, wenn alle vorherigen Spieltage komplett sind. Noch nicht komplett: {$incompleteNumbers}.");
                return;
            }
        }

        // Set playing_matchday_id
        $user->update(['playing_matchday_id' => $matchday->id]);
        $this->playingMatchdayId = $matchday->id;
        $this->message = "Spieltag {$matchday->matchday_number} gestartet. Das nächste eingehende Spiel wird diesem Spieltag zugeordnet.";
        $this->success = true;
        
        // Clear any previous errors
        $this->resetErrorBag();
    }

    public function stopMatchday(): void
    {
        $user = Auth::user();
        $user->update(['playing_matchday_id' => null]);
        $this->playingMatchdayId = null;
        $this->message = 'Spieltag-Modus beendet.';
        $this->success = true;
    }

    public function with(): array
    {
        $user = Auth::user();
        if (! $user || ! $user->player) {
            return [
                'relevantMatchdays' => collect(),
            ];
        }

        // Get all seasons where user is participant
        $seasonIds = \App\Models\SeasonParticipant::where('player_id', $user->player->id)
            ->pluck('season_id')
            ->unique();

        $seasons = Season::whereIn('id', $seasonIds)
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->with(['matchdays' => function ($query) {
                $query->orderBy('matchday_number');
            }])
            ->get();

        $relevantMatchdays = collect();
        $hasUncompletedMatchday = false;

        foreach ($seasons as $season) {
            // Different logic based on schedule mode
            if ($season->matchday_schedule_mode === \App\Enums\MatchdayScheduleMode::UnlimitedNoOrder) {
                // For unlimited no order: show all incomplete matchdays where user has a fixture
                $matchdays = $season->matchdays()
                    ->where('is_playoff', false)
                    ->orderBy('matchday_number')
                    ->get()
                    ->filter(function ($matchday) {
                        return ! $matchday->isComplete();
                    });

                foreach ($matchdays as $matchday) {
                    // Load fixtures for this matchday where user is involved
                    $fixture = \App\Models\MatchdayFixture::where('matchday_id', $matchday->id)
                        ->where(function ($query) use ($user) {
                            $query->where('home_player_id', $user->player->id)
                                ->orWhere('away_player_id', $user->player->id);
                        })
                        ->with(['homePlayer.user', 'awayPlayer.user', 'dartMatch'])
                        ->first();

                    // Only add if user has a fixture in this matchday
                    if ($fixture) {
                        $fixtureCompleted = $fixture->dart_match_id !== null || $fixture->status === 'completed';
                        
                        if (!$fixtureCompleted) {
                            $hasUncompletedMatchday = true;
                        }

                        $relevantMatchdays->push([
                            'matchday' => $matchday,
                            'season' => $season,
                            'fixture' => $fixture,
                        ]);
                    }
                }
            } else {
                // For timed and unlimited with order: show next relevant matchday
                $nextMatchday = $season->getNextRelevantMatchday($user);
                if ($nextMatchday) {
                    // Load fixtures for this matchday where user is involved
                    $fixture = \App\Models\MatchdayFixture::where('matchday_id', $nextMatchday->id)
                        ->where(function ($query) use ($user) {
                            $query->where('home_player_id', $user->player->id)
                                ->orWhere('away_player_id', $user->player->id);
                        })
                        ->with(['homePlayer.user', 'awayPlayer.user', 'dartMatch'])
                        ->first();

                    $fixtureCompleted = $fixture && ($fixture->dart_match_id !== null || $fixture->status === 'completed');
                    
                    if (!$fixtureCompleted) {
                        $hasUncompletedMatchday = true;
                    }

                    $relevantMatchdays->push([
                        'matchday' => $nextMatchday,
                        'season' => $season,
                        'fixture' => $fixture,
                    ]);
                }
            }
        }

        return [
            'relevantMatchdays' => $relevantMatchdays,
            'hasUncompletedMatchday' => $hasUncompletedMatchday,
        ];
    }
}; ?>
<div>
@if ($relevantMatchdays->isNotEmpty())
    @php
        $useGreenDesign = $hasUncompletedMatchday;
    @endphp
    <div class="relative overflow-hidden rounded-2xl border-2 {{ $useGreenDesign ? 'border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 shadow-lg dark:border-green-800 dark:from-green-950/30 dark:to-emerald-950/30' : 'border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-zinc-900' }}">
        <div class="flex flex-col">
            <div class="border-b {{ $useGreenDesign ? 'border-green-200 bg-gradient-to-r from-green-100 to-emerald-100 dark:border-green-800 dark:from-green-900/30 dark:to-emerald-900/30' : 'border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-zinc-800' }} px-8 py-5">
                <h3 class="text-xl font-bold {{ $useGreenDesign ? 'text-green-900 dark:text-green-100' : 'text-neutral-900 dark:text-neutral-100' }}">{{ __('Dein Spieltag') }}</h3>
                @if ($useGreenDesign)
                    <p class="mt-1 text-sm {{ $useGreenDesign ? 'text-green-700 dark:text-green-300' : 'text-neutral-600 dark:text-neutral-400' }}">{{ __('Starte dein Spiel für diesen Spieltag') }}</p>
                @endif
            </div>

            <div class="p-8">
                @if ($message)
                    <div class="mb-4 rounded-lg border p-3 {{ $success ? 'border-green-300 bg-green-100 dark:border-green-700 dark:bg-green-900/30' : 'border-red-300 bg-red-100 dark:border-red-700 dark:bg-red-900/30' }}">
                        <p class="text-sm font-medium {{ $success ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">
                            {{ $message }}
                        </p>
                    </div>
                @endif
                
                @error('matchday')
                    <div class="mb-4 rounded-lg border border-red-300 bg-red-100 p-3 dark:border-red-700 dark:bg-red-900/30">
                        <p class="text-sm font-medium text-red-900 dark:text-red-100">
                            {{ $message }}
                        </p>
                    </div>
                @enderror

                @foreach ($relevantMatchdays as $item)
                    @php
                        $matchday = $item['matchday'];
                        $season = $item['season'];
                        $fixture = $item['fixture'];
                        $isActive = $matchday->isCurrentlyActive();
                        $isPlaying = $playingMatchdayId === $matchday->id;
                        $playerId = Auth::user()->player?->id;
                        $fixtureCompleted = $fixture && ($fixture->dart_match_id !== null || $fixture->status === 'completed');
                        $displayType = $season->dashboard_display_type ?? 'none';
                        $badgeColor = $season->dashboard_badge_color ?? 'green';
                        $bannerPath = $displayType === 'banner' ? $season->getBannerPath() : null;
                        $logoPath = $displayType === 'logo' ? $season->getLogoPath() : null;
                    @endphp

                    <div
                        class="mb-4 rounded-xl border-2 {{ $fixtureCompleted ? 'border-neutral-200 dark:border-neutral-700' : 'border-green-200 dark:border-green-800' }} {{ ($bannerPath || $logoPath) ? 'relative overflow-hidden' : '' }} {{ $bannerPath ? 'px-6' : ($fixtureCompleted ? 'bg-neutral-50 dark:bg-zinc-800' : 'bg-white dark:bg-zinc-900') }} {{ $bannerPath ? '' : 'p-6' }} shadow-md"
                        @if($bannerPath)
                            style="background-image: url('{{ \Illuminate\Support\Facades\Storage::url($bannerPath) }}'); background-size: 100% auto; background-position: center; background-repeat: no-repeat;"
                        @endif
                        wire:key="matchday-{{ $matchday->id }}"
                    >
                        @if($bannerPath)
                            <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/60 to-black/70 dark:from-black/80 dark:via-black/70 dark:to-black/80 rounded-xl"></div>
                        @endif
                        @if($logoPath)
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                <img 
                                    src="{{ \Illuminate\Support\Facades\Storage::url($logoPath) }}" 
                                    alt="{{ $season->name }} Logo" 
                                    class="h-full w-auto object-contain"
                                />
                            </div>
                        @endif
                        <div class="relative flex items-center justify-between gap-4">
                            <div class="relative flex-1 min-w-0 {{ $bannerPath ? 'bg-black/50 dark:bg-black/60 rounded-lg p-4 backdrop-blur-md' : '' }}">
                                <div class="flex items-center gap-2 mb-2">
                                    <a href="{{ route('leagues.show', $season->league) }}" wire:navigate>
                                        @if($bannerPath)
                                            <flux:badge size="xs" variant="solid" color="{{ $badgeColor }}">
                                                {{ $season->league->slug }}
                                            </flux:badge>
                                        @else
                                            <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                                {{ $season->league->slug }}
                                            </flux:badge>
                                        @endif
                                    </a>
                                    <a href="{{ route('seasons.show', $season) }}" wire:navigate>
                                        @if($bannerPath)
                                            <flux:badge size="xs" variant="solid" color="{{ $badgeColor }}">
                                                {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                                            </flux:badge>
                                        @else
                                            <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                                {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                                            </flux:badge>
                                        @endif
                                    </a>
                                </div>

                                <div class="mt-2">
                                    @if($bannerPath)
                                        <a href="{{ route('leagues.show', $season->league) }}" wire:navigate>
                                            <flux:badge size="sm" variant="solid" color="{{ $badgeColor }}">
                                                {{ $season->name }}
                                            </flux:badge>
                                        </a>
                                    @else
                                        <a href="{{ route('leagues.show', $season->league) }}" wire:navigate class="block">
                                            <h4 class="text-lg font-bold text-neutral-900 dark:text-neutral-100 hover:text-green-600 dark:hover:text-green-400 transition-colors">
                                                {{ $season->name }}
                                            </h4>
                                        </a>
                                    @endif
                                </div>

                                @if ($fixture)
                                    <div class="mt-2">
                                        @if($bannerPath)
                                            <flux:badge size="xs" variant="solid" color="{{ $badgeColor }}">
                                                @if ($fixture->homePlayer?->user)
                                                    <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="hover:underline">
                                                        {{ $fixture->homePlayer->name }}
                                                    </a>
                                                @else
                                                    {{ $fixture->homePlayer->name }}
                                                @endif
                                                <span class="mx-1">vs</span>
                                                @if ($fixture->awayPlayer?->user)
                                                    <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="hover:underline">
                                                        {{ $fixture->awayPlayer->name }}
                                                    </a>
                                                @else
                                                    {{ $fixture->awayPlayer->name }}
                                                @endif
                                            </flux:badge>
                                        @else
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="font-medium {{ $fixture->home_player_id == $playerId ? 'text-green-600 dark:text-green-400' : 'text-neutral-700 dark:text-neutral-300' }}">
                                                    @if ($fixture->homePlayer?->user)
                                                        <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300">
                                                            {{ $fixture->homePlayer->name }}
                                                        </a>
                                                    @else
                                                        {{ $fixture->homePlayer->name }}
                                                    @endif
                                                </span>
                                                <span class="text-neutral-500 dark:text-neutral-400">vs</span>
                                                <span class="font-medium {{ $fixture->away_player_id == $playerId ? 'text-green-600 dark:text-green-400' : 'text-neutral-700 dark:text-neutral-300' }}">
                                                    @if ($fixture->awayPlayer?->user)
                                                        <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300">
                                                            {{ $fixture->awayPlayer->name }}
                                                        </a>
                                                    @else
                                                        {{ $fixture->awayPlayer->name }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if ($matchday->deadline_at)
                                    <p class="mt-1 text-xs {{ $bannerPath ? 'text-white drop-shadow' : 'text-neutral-500 dark:text-neutral-400' }}">
                                        {{ __('Deadline: :date', ['date' => $matchday->deadline_at->format('d.m.Y H:i')]) }}
                                    </p>
                                @endif

                                @if ($isPlaying)
                                    <p class="mt-2 text-sm font-medium {{ $bannerPath ? 'text-green-300 drop-shadow' : 'text-green-600 dark:text-green-400' }}">
                                        {{ __('Warte auf eingehendes Spiel...') }}
                                    </p>
                                @endif
                            </div>

                            <div class="relative flex items-center gap-4">
                                @if (!$fixtureCompleted)
                                    <div class="flex items-center">
                                        @if ($isPlaying)
                                            <flux:button
                                                variant="danger"
                                                wire:click="stopMatchday"
                                                wire:loading.attr="disabled"
                                            >
                                                {{ __('Abbrechen') }}
                                            </flux:button>
                                        @else
                                            <flux:button
                                                variant="primary"
                                                color="{{ $isActive ? 'green' : 'blue' }}"
                                                wire:click="startMatchday({{ $matchday->id }})"
                                                wire:loading.attr="disabled"
                                                icon="play"
                                                class="font-semibold"
                                            >
                                                {{ $isActive ? __('Jetzt spielen') : __('Spiel starten') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                @elseif ($fixture->dartMatch)
                                    <div class="flex items-center">
                                        <flux:button
                                            variant="outline"
                                            :href="route('matches.show', $fixture->dartMatch)"
                                            wire:navigate
                                            class="{{ $bannerPath ? 'bg-white/10 border-white/30 text-white hover:bg-white/20' : '' }}"
                                        >
                                            {{ __('Match ansehen') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
</div>
