<?php

use App\Models\DartMatch;
use App\Services\SettingsService;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo:matches,.match.updated' => '$refresh',
        ];
    }

    public function with(): array
    {
        $limit = SettingsService::getLatestMatchesCount();
        $totalCount = DartMatch::query()->finished()->count();

        $matches = DartMatch::query()
            ->finished()
            ->with([
                'players' => fn ($query) => $query->orderBy('match_player.player_index'),
                'winner',
                'fixture.matchday.season.league',
            ])
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return [
            'matches' => $matches,
            'totalCount' => $totalCount,
            'limit' => $limit,
        ];
    }
}; ?>

<div class="relative h-full overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex h-full flex-col">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-zinc-800">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Latest Matches') }}</h3>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            @forelse ($matches as $match)
                <div
                    class="group mb-4 rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-zinc-800"
                    wire:key="latest-match-{{ $match->id }}"
                >
                    @if ($match->fixture?->matchday)
                        <div class="flex items-center gap-1 px-4 pt-4 pb-2">
                            <a href="{{ route('seasons.show', $match->fixture->matchday->season) }}?activeTab=standings" wire:navigate class="relative z-10">
                                <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                    {{ $match->fixture->matchday->season->league->slug }} {{ $match->fixture->matchday->season->slug }}
                                </flux:badge>
                            </a>
                            <a href="{{ route('seasons.show', $match->fixture->matchday->season) }}?activeTab=schedule" wire:navigate class="relative z-10">
                                <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                    {{ __('Spieltag :number', ['number' => $match->fixture->matchday->matchday_number]) }}
                                </flux:badge>
                            </a>
                        </div>
                    @endif
                    <a
                        href="{{ route('matches.show', $match) }}"
                        wire:navigate
                        class="block p-4 transition-colors hover:border-neutral-300 hover:bg-neutral-100 dark:hover:border-neutral-600 dark:hover:bg-zinc-700 {{ $match->fixture?->matchday ? 'pt-2' : '' }}"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <h4 class="truncate text-sm font-medium text-neutral-900 group-hover:text-neutral-700 dark:text-neutral-100 dark:group-hover:text-neutral-300">
                                        {{ $match->variant }} · {{ $match->type }}
                                    </h4>
                                    @php
                                        $totalDarts = $match->players->sum('pivot.darts_thrown');
                                    @endphp
                                    @if ($totalDarts > 0)
                                        <span class="shrink-0 inline-flex items-center gap-1 text-xs text-neutral-500 dark:text-neutral-400" title="{{ __('Geworfene Pfeile') }}">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M3 3v6m0-6h6M21 21v-6m0 6h-6" />
                                            </svg>
                                            {{ $totalDarts }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Spieler mit Ergebnis --}}
                                <div class="mt-2 space-y-1">
                                    @foreach ($match->players as $participant)
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="truncate text-sm text-neutral-700 dark:text-neutral-300">
                                                {{ $participant->name ?? __('Player #:id', ['id' => $participant->id]) }}
                                            </span>
                                            <span class="shrink-0 min-w-[3rem] text-right font-mono text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                                @if ($match->type === 'Sets')
                                                    {{ $participant->pivot->sets_won ?? 0 }}:{{ $participant->pivot->legs_won ?? 0 }}
                                                @else
                                                    {{ $participant->pivot->legs_won ?? 0 }}
                                                @endif
                                            </span>
                                        </div>
                                    @endforeach
                                </div>

                                <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                                    @if ($match->finished_at)
                                        {{ $match->finished_at->diffForHumans() }}
                                    @else
                                        {{ __('Endzeit unbekannt') }}
                                    @endif
                                </p>
                            </div>
                            <div class="shrink-0 self-center">
                                <svg class="size-5 text-neutral-400 transition-colors group-hover:text-neutral-600 dark:text-neutral-500 dark:group-hover:text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="py-8 text-center">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Keine beendeten Matches vorhanden.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($totalCount > $limit)
            <div class="border-t border-neutral-200 bg-neutral-50 px-6 py-3 dark:border-neutral-700 dark:bg-zinc-800">
                <a
                    href="{{ route('matches.index') }}"
                    wire:navigate
                    class="text-sm font-medium text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
                >
                    {{ __('Alle Matches anzeigen') }} →
                </a>
            </div>
        @endif
    </div>
</div>
