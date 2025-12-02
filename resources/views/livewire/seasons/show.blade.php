<?php

use App\Models\Season;
use App\Services\LeagueStandingsCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    #[Locked]
    public Season $season;

    public string $activeTab = 'overview';
    public ?int $playerId = null;

    public function mount(Season $season): void
    {
        $this->season = $season->load([
            'league',
            'participants.player',
            'matchdays.fixtures.homePlayer.user',
            'matchdays.fixtures.awayPlayer.user',
            'matchdays.fixtures.dartMatch',
        ]);
        
        $this->playerId = Auth::user()?->player?->id;
    }

    public function with(): array
    {
        return [
            'matchdays' => $this->season->matchdays()->orderBy('matchday_number')->get(),
            'standings' => app(LeagueStandingsCalculator::class)->calculateStandings($this->season),
            'myParticipant' => $this->playerId
                ? $this->season->participants()->where('player_id', $this->playerId)->first()
                : null,
            'myFixtures' => $this->playerId
                ? $this->season->matchdays()
                    ->with(['fixtures' => function ($query) {
                        $query->where('home_player_id', $this->playerId)
                            ->orWhere('away_player_id', $this->playerId);
                    }])
                    ->get()
                    ->pluck('fixtures')
                    ->flatten()
                : collect(),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $season->name }}</flux:heading>
            <flux:subheading>
                {{ $season->description ?? __('Saison-Details') }}
                @if ($season->league)
                    - <a href="{{ route('leagues.show', $season->league) }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">{{ $season->league->name }}</a>
                @endif
            </flux:subheading>
        </div>

        <flux:button variant="ghost" :href="route('leagues.show', $season->league)" wire:navigate>
            {{ __('Zurück zur Liga') }}
        </flux:button>
    </div>

    @if ($season->getBannerPath())
        <div class="overflow-hidden rounded-xl">
            <img src="{{ Storage::url($season->getBannerPath()) }}" alt="{{ $season->name }}" class="w-full h-auto max-h-64 object-cover" />
        </div>
    @endif

    <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
        <button
            wire:click="$set('activeTab', 'overview')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'overview' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Übersicht') }}
        </button>

        <button
            wire:click="$set('activeTab', 'schedule')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'schedule' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Spielplan') }}
        </button>

        <button
            wire:click="$set('activeTab', 'standings')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'standings' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Tabelle') }}
        </button>

        <button
            wire:click="$set('activeTab', 'results')"
            class="px-4 py-2 text-sm font-medium transition-colors {{ $activeTab === 'results' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100' }}"
        >
            {{ __('Ergebnisse') }}
        </button>
    </div>

    @if ($activeTab === 'overview')
        <div class="space-y-6">
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">{{ __('Saison-Informationen') }}</flux:heading>

                <dl class="grid gap-4 md:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                        <dd class="mt-1">
                            <flux:badge :variant="match($season->status) {
                                'registration' => 'primary',
                                'active' => 'success',
                                'completed' => 'subtle',
                                'cancelled' => 'danger',
                                default => 'subtle'
                            }">
                                {{ __(ucfirst($season->status)) }}
                            </flux:badge>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Teilnehmer') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ $season->participants->count() }} / {{ $season->max_players }} {{ __('Spieler') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Modus') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ match($season->mode) {
                                'single_round' => __('Nur Hinrunde'),
                                'double_round' => __('Hin & Rückrunde'),
                                default => $season->mode
                            } }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Spielformat') }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            {{ match($season->match_format) {
                                'best_of_3' => 'Best of 3',
                                'best_of_5' => 'Best of 5',
                                default => $season->match_format
                            } }}
                        </dd>
                    </div>
                </dl>
            </div>

            @if ($myParticipant)
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg" class="mb-4">{{ __('Deine Position') }}</flux:heading>

                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                {{ $myParticipant->final_position ?? '-' }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Platz') }}</div>
                        </div>

                        <div class="text-center">
                            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $myParticipant->points }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Punkte') }}</div>
                        </div>

                        <div class="text-center">
                            <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                                {{ $myParticipant->matches_won }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Siege') }}</div>
                        </div>

                        <div class="text-center">
                            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                                {{ $myParticipant->legs_won }}:{{ $myParticipant->legs_lost }}
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Legs') }}</div>
                        </div>
                    </div>
                </div>

                @if ($myFixtures->where('status', 'scheduled')->count() > 0)
                    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="lg" class="mb-4">{{ __('Anstehende Spiele') }}</flux:heading>

                        <div class="space-y-2">
                            @foreach ($myFixtures->where('status', 'scheduled')->take(3) as $fixture)
                                <div wire:key="upcoming-fixture-{{ $fixture->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <div class="flex-1">
                                        <span class="font-medium {{ $fixture->home_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : '' }}">
                                            @if ($fixture->homePlayer?->user)
                                                <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ $fixture->homePlayer->name }}
                                                </a>
                                            @else
                                                {{ $fixture->homePlayer->name }}
                                            @endif
                                        </span>
                                        <span class="mx-2 text-zinc-500">vs</span>
                                        <span class="font-medium {{ $fixture->away_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : '' }}">
                                            @if ($fixture->awayPlayer?->user)
                                                <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                    {{ $fixture->awayPlayer->name }}
                                                </a>
                                            @else
                                                {{ $fixture->awayPlayer->name }}
                                            @endif
                                        </span>
                                    </div>

                                    @if ($fixture->matchday->deadline_at)
                                        <div class="text-sm text-zinc-500">
                                            {{ __('bis :date', ['date' => $fixture->matchday->deadline_at->format('d.m.')]) }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if ($activeTab === 'schedule')
        <div class="space-y-4">
            @forelse ($matchdays as $matchday)
                <div wire:key="matchday-{{ $matchday->id }}" class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <flux:heading size="md">
                            {{ __('Spieltag :number', ['number' => $matchday->matchday_number]) }}
                            @if ($matchday->is_return_round)
                                <span class="text-sm font-normal text-zinc-500">({{ __('Rückrunde') }})</span>
                            @endif
                        </flux:heading>
                        @if ($matchday->deadline_at)
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Deadline: :date', ['date' => $matchday->deadline_at->format('d.m.Y')]) }}
                            </p>
                        @endif
                    </div>

                    <div class="space-y-2">
                        @forelse ($matchday->fixtures as $fixture)
                            <div wire:key="fixture-{{ $fixture->id }}" class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700 {{ ($fixture->home_player_id == $playerId || $fixture->away_player_id == $playerId) ? 'bg-blue-50 dark:bg-blue-950' : '' }}">
                                <div class="flex-1">
                                    <span class="font-medium {{ $fixture->home_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : '' }}">
                                        @if ($fixture->homePlayer?->user)
                                            <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $fixture->homePlayer->name }}
                                            </a>
                                        @else
                                            {{ $fixture->homePlayer->name }}
                                        @endif
                                    </span>
                                    <span class="mx-2 text-zinc-500">vs</span>
                                    <span class="font-medium {{ $fixture->away_player_id == $playerId ? 'text-blue-600 dark:text-blue-400' : '' }}">
                                        @if ($fixture->awayPlayer?->user)
                                            <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $fixture->awayPlayer->name }}
                                            </a>
                                        @else
                                            {{ $fixture->awayPlayer->name }}
                                        @endif
                                    </span>
                                </div>

                                <div class="flex items-center gap-4">
                                    @if ($fixture->status === 'completed')
                                        <span class="text-sm font-medium">
                                            {{ $fixture->home_legs_won }} : {{ $fixture->away_legs_won }}
                                        </span>
                                        @if ($fixture->dartMatch)
                                            <flux:button
                                                size="xs"
                                                variant="outline"
                                                :href="route('matches.show', $fixture->dartMatch)"
                                                wire:navigate
                                            >
                                                {{ __('Match') }}
                                            </flux:button>
                                        @endif
                                    @else
                                        <flux:badge size="sm" :variant="$fixture->status === 'overdue' ? 'danger' : 'subtle'">
                                            {{ __(ucfirst($fixture->status)) }}
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500">{{ __('Keine Spiele geplant') }}</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Spieltage verfügbar.') }}
                </flux:callout>
            @endforelse
        </div>
    @endif

    @if ($activeTab === 'standings')
        <div class="space-y-4">
            @if ($standings->count() > 0)
                <div class="rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Pos.') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Spieler') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Pkt.') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Sp.') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('S') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('N') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                                    {{ __('Legs') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($standings as $standing)
                                <tr wire:key="standing-{{ $standing->id }}" class="{{ $standing->player_id == $playerId ? 'bg-blue-50 dark:bg-blue-950' : '' }}">
                                    <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->final_position ?? $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->player->name }}
                                        @if ($standing->player_id == $playerId)
                                            <flux:badge size="sm" variant="primary" class="ml-2">{{ __('Du') }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $standing->points }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_played }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_won }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->matches_lost }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-zinc-600 dark:text-zinc-300">
                                        {{ $standing->legs_won }}:{{ $standing->legs_lost }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Tabelle verfügbar.') }}
                </flux:callout>
            @endif
        </div>
    @endif

    @if ($activeTab === 'results')
        <div class="space-y-4">
            @php
                $completedFixtures = $matchdays->pluck('fixtures')->flatten()->filter(fn($f) => $f->status === 'completed');
            @endphp

            @forelse ($completedFixtures as $fixture)
                <div wire:key="result-{{ $fixture->id }}" class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Spieltag :number', ['number' => $fixture->matchday->matchday_number]) }}
                            </div>
                            <div class="mt-1 font-medium">
                                <span class="{{ $fixture->winner_player_id == $fixture->home_player_id ? 'text-green-600 dark:text-green-400' : '' }}">
                                    @if ($fixture->homePlayer?->user)
                                        <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                            {{ $fixture->homePlayer->name }}
                                        </a>
                                    @else
                                        {{ $fixture->homePlayer->name }}
                                    @endif
                                </span>
                                <span class="mx-2">vs</span>
                                <span class="{{ $fixture->winner_player_id == $fixture->away_player_id ? 'text-green-600 dark:text-green-400' : '' }}">
                                    @if ($fixture->awayPlayer?->user)
                                        <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                            {{ $fixture->awayPlayer->name }}
                                        </a>
                                    @else
                                        {{ $fixture->awayPlayer->name }}
                                    @endif
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-xl font-bold">
                                    {{ $fixture->home_legs_won }} : {{ $fixture->away_legs_won }}
                                </div>
                                @if ($fixture->played_at)
                                    <div class="text-xs text-zinc-500">
                                        {{ $fixture->played_at->format('d.m.Y') }}
                                    </div>
                                @endif
                            </div>

                            @if ($fixture->dartMatch)
                                <flux:button
                                    size="xs"
                                    variant="outline"
                                    :href="route('matches.show', $fixture->dartMatch)"
                                    wire:navigate
                                >
                                    {{ __('Details') }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <flux:callout variant="info" icon="information-circle">
                    {{ __('Noch keine Ergebnisse vorhanden.') }}
                </flux:callout>
            @endforelse
        </div>
    @endif
</section>
