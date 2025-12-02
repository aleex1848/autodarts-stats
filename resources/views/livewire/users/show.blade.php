<?php

use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Locked]
    public User $user;

    public string $activeTab = 'overview';

    public function mount(User $user): void
    {
        $this->user = $user->load([
            'player',
            'player.matches',
        ]);
    }

    public function with(): array
    {
        $finishedMatches = $this->user->matches()
            ->finished()
            ->with([
                'players' => fn ($query) => $query->orderBy('match_player.player_index'),
                'winner',
                'fixture.matchday.season.league',
            ])
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->paginate(10);

        $leagues = $this->user->leagues();
        
        // Sammle alle Season-IDs für alle Ligen auf einmal (Performance-Optimierung)
        $leagueIds = $leagues->pluck('id')->all();
        $seasonIds = collect();
        
        // Seasons aus SeasonRegistrations (über user_id)
        $seasonRegistrationsByUserId = \App\Models\SeasonRegistration::where('user_id', $this->user->id)
            ->whereHas('season', function ($query) use ($leagueIds) {
                $query->whereIn('league_id', $leagueIds);
            })
            ->with('season')
            ->get();
        
        $seasonIds = $seasonIds->merge($seasonRegistrationsByUserId->pluck('season_id'));
        
        // Seasons aus SeasonRegistrations (über player_id, falls vorhanden)
        if ($this->user->player) {
            $seasonRegistrationsByPlayerId = \App\Models\SeasonRegistration::where('player_id', $this->user->player->id)
                ->whereHas('season', function ($query) use ($leagueIds) {
                    $query->whereIn('league_id', $leagueIds);
                })
                ->with('season')
                ->get();
            
            $seasonIds = $seasonIds->merge($seasonRegistrationsByPlayerId->pluck('season_id'));
            
            // Seasons aus SeasonParticipants
            $seasonParticipants = \App\Models\SeasonParticipant::where('player_id', $this->user->player->id)
                ->whereHas('season', function ($query) use ($leagueIds) {
                    $query->whereIn('league_id', $leagueIds);
                })
                ->with('season')
                ->get();
            
            $seasonIds = $seasonIds->merge($seasonParticipants->pluck('season_id'));
        }
        
        // Lade alle eindeutigen Seasons auf einmal
        $uniqueSeasonIds = $seasonIds->unique()->values()->all();
        $allSeasons = empty($uniqueSeasonIds) 
            ? collect() 
            : \App\Models\Season::whereIn('id', $uniqueSeasonIds)
                ->with('league')
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('league_id');
        
        // Ordne die Seasons den Ligen zu
        $leaguesWithSeasons = $leagues->map(function ($league) use ($allSeasons) {
            $league->userSeasons = $allSeasons->get($league->id, collect());
            return $league;
        });

        return [
            'finishedMatches' => $finishedMatches,
            'upcomingFixtures' => $this->user->upcomingFixtures(),
            'leagues' => $leaguesWithSeasons,
            'player' => $this->user->player,
        ];
    }
}; ?>

<section class="w-full space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                @if ($user->player?->avatar_url)
                    <flux:avatar :src="$user->player->avatar_url" :name="$user->name" size="lg" />
                @else
                    <flux:avatar :name="$user->name" size="lg" />
                @endif
                <div>
                    <flux:heading size="xl">{{ $user->name }}</flux:heading>
                    <flux:subheading>
                        @if ($user->player)
                            {{ $user->player->name }}
                        @else
                            {{ __('Benutzerprofil') }}
                        @endif
                    </flux:subheading>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @if ($user->discordProfileUrl())
                    <flux:button
                        variant="outline"
                        :href="$user->discordProfileUrl()"
                        target="_blank"
                        icon="chat-bubble-left-right"
                    >
                        {{ __('Discord Profil') }}
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
            <button
                wire:click="$set('activeTab', 'overview')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'overview' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
            >
                {{ __('Übersicht') }}
            </button>

            <button
                wire:click="$set('activeTab', 'matches')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'matches' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
            >
                {{ __('Gemachte Spiele') }}
            </button>

            <button
                wire:click="$set('activeTab', 'upcoming')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'upcoming' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
            >
                {{ __('Kommende Spiele') }}
            </button>

            <button
                wire:click="$set('activeTab', 'leagues')"
                class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'leagues' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
            >
                {{ __('Ligen') }}
            </button>
        </div>

        @if ($activeTab === 'overview')
            <div class="space-y-6">
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-4">{{ __('Benutzerinformationen') }}</flux:heading>

                    <dl class="grid gap-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Benutzername') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $user->name }}
                            </dd>
                        </div>

                        @if ($user->player)
                            <div>
                                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Spielername') }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $user->player->name }}
                                </dd>
                            </div>
                        @endif

                        @if ($user->autodarts_name)
                            <div>
                                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('AutoDarts Name') }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $user->autodarts_name }}
                                </dd>
                            </div>
                        @endif

                        @if ($user->discord_username)
                            <div>
                                <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Discord Username') }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $user->discord_username }}
                                </dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Gemachte Spiele') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $user->finishedMatches()->count() }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Kommende Spiele') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $user->upcomingFixtures()->count() }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Teilgenommene Ligen') }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $user->leagues()->count() }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        @endif

        @if ($activeTab === 'matches')
            <div class="space-y-4">
                @if ($finishedMatches->count() > 0)
                    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                        {{ __('Match') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                        {{ __('Spieler') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                        {{ __('Ergebnis') }}
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                        {{ __('Aktionen') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @foreach ($finishedMatches as $match)
                                    <tr wire:key="match-{{ $match->id }}">
                                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold">{{ $match->variant }} · {{ $match->type }}</span>
                                                    @if ($match->fixture?->matchday?->season)
                                                        <div class="flex items-center gap-1">
                                                            <a href="{{ route('seasons.show', $match->fixture->matchday->season) }}?activeTab=standings" wire:navigate>
                                                                <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                                                    {{ $match->fixture->matchday->season->league->slug }} {{ $match->fixture->matchday->season->slug }}
                                                                </flux:badge>
                                                            </a>
                                                            <a href="{{ route('seasons.show', $match->fixture->matchday->season) }}?activeTab=schedule" wire:navigate>
                                                                <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                                                    {{ __('Spieltag :number', ['number' => $match->fixture->matchday->matchday_number]) }}
                                                                </flux:badge>
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ $match->finished_at?->format('d.m.Y H:i') ?? __('Unbekannt') }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($match->players as $participant)
                                                    <flux:badge
                                                        size="sm"
                                                        :variant="$participant->pivot->final_position === 1 ? 'primary' : 'subtle'"
                                                    >
                                                        {{ $participant->name ?? __('Player #:id', ['id' => $participant->id]) }}
                                                    </flux:badge>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                            @if ($match->winner)
                                                <span class="font-semibold {{ $match->winner->user_id === $user->id ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                    {{ $match->winner->user_id === $user->id ? __('Gewonnen') : __('Verloren') }}
                                                </span>
                                            @else
                                                <span class="text-zinc-500 dark:text-zinc-400">{{ __('Beendet') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm">
                                            <flux:button
                                                size="sm"
                                                variant="outline"
                                                :href="route('matches.show', $match)"
                                                wire:navigate
                                                icon="arrow-top-right-on-square"
                                            >
                                                {{ __('Details') }}
                                            </flux:button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $finishedMatches->links(data: ['scrollTo' => false]) }}
                    </div>
                @else
                    <flux:callout variant="info" icon="information-circle">
                        {{ __('Keine beendeten Spiele gefunden.') }}
                    </flux:callout>
                @endif
            </div>
        @endif

        @if ($activeTab === 'upcoming')
            <div class="space-y-4">
                @if ($upcomingFixtures->count() > 0)
                    <div class="space-y-2">
                        @foreach ($upcomingFixtures as $fixture)
                            <div
                                wire:key="upcoming-fixture-{{ $fixture->id }}"
                                class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        @if ($fixture->matchday?->season)
                                            <div class="flex items-center gap-1 mb-2">
                                                <a href="{{ route('seasons.show', $fixture->matchday->season) }}?activeTab=standings" wire:navigate>
                                                    <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                                        {{ $fixture->matchday->season->league->slug }} {{ $fixture->matchday->season->slug }}
                                                    </flux:badge>
                                                </a>
                                                <a href="{{ route('seasons.show', $fixture->matchday->season) }}?activeTab=schedule" wire:navigate>
                                                    <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                                        {{ __('Spieltag :number', ['number' => $fixture->matchday->matchday_number]) }}
                                                    </flux:badge>
                                                </a>
                                            </div>
                                        @endif

                                        <div class="mt-2 space-y-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate text-sm text-neutral-700 dark:text-neutral-300">
                                                    {{ $fixture->homePlayer->name ?? __('Player #:id', ['id' => $fixture->home_player_id]) }} vs {{ $fixture->awayPlayer->name ?? __('Player #:id', ['id' => $fixture->away_player_id]) }}
                                                </span>
                                            </div>
                                        </div>

                                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                            @if ($fixture->matchday?->deadline_at)
                                                {{ __('Deadline: :date', ['date' => $fixture->matchday->deadline_at->format('d.m.Y H:i')]) }}
                                            @else
                                                {{ __('Nicht terminiert') }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:callout variant="info" icon="information-circle">
                        {{ __('Keine anstehenden Spiele vorhanden.') }}
                    </flux:callout>
                @endif
            </div>
        @endif

        @if ($activeTab === 'leagues')
            <div class="space-y-4">
                @if ($leagues->count() > 0)
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ($leagues as $league)
                            <div
                                wire:key="league-{{ $league->id }}"
                                class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900"
                            >
                                <div class="mb-4">
                                    <flux:heading size="md">{{ $league->name }}</flux:heading>
                                    @if ($league->description)
                                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $league->description }}
                                        </p>
                                    @endif
                                </div>

                                @if (isset($league->userSeasons) && $league->userSeasons->count() > 0)
                                    <div class="mb-4 space-y-2">
                                        <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                            {{ __('Angemeldete Seasons') }}
                                        </p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($league->userSeasons as $season)
                                                <a 
                                                    href="{{ route('seasons.show', $season) }}" 
                                                    wire:navigate
                                                    class="inline-block"
                                                >
                                                    <flux:badge 
                                                        size="sm" 
                                                        variant="subtle"
                                                        class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer"
                                                    >
                                                        {{ $season->name ?? $season->slug }}
                                                    </flux:badge>
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <div class="flex items-center justify-end">
                                    <flux:button
                                        size="sm"
                                        variant="outline"
                                        :href="route('leagues.show', $league)"
                                        wire:navigate
                                    >
                                        {{ __('Details') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:callout variant="info" icon="information-circle">
                        {{ __('Keine Ligen gefunden.') }}
                    </flux:callout>
                @endif
            </div>
        @endif
    </section>