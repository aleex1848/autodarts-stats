<?php

use App\Models\DartMatch;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $totalCount = DartMatch::query()->ongoing()->count();

        $matches = DartMatch::query()
            ->ongoing()
            ->with([
                'players' => fn ($query) => $query->orderBy('match_player.player_index'),
                'winner',
            ])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'matches' => $matches,
            'totalCount' => $totalCount,
        ];
    }
}; ?>

<div class="relative h-full overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex h-full flex-col">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-zinc-800">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Running Matches') }}</h3>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            @forelse ($matches as $match)
                <a
                    href="{{ route('matches.show', $match) }}"
                    wire:navigate
                    class="group mb-4 block rounded-lg border border-neutral-200 bg-neutral-50 p-4 transition-colors hover:border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-zinc-800 dark:hover:border-neutral-600 dark:hover:bg-zinc-700"
                    wire:key="running-match-{{ $match->id }}"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <h4 class="truncate text-sm font-medium text-neutral-900 group-hover:text-neutral-700 dark:text-neutral-100 dark:group-hover:text-neutral-300">
                                {{ $match->variant }} · {{ $match->type }}
                            </h4>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($match->players as $participant)
                                    <span class="inline-flex items-center rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-800 dark:bg-neutral-700 dark:text-neutral-200">
                                        {{ $participant->name ?? __('Player #:id', ['id' => $participant->id]) }}
                                    </span>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                @if ($match->started_at)
                                    {{ $match->started_at->diffForHumans() }}
                                @else
                                    {{ __('Startzeit unbekannt') }}
                                @endif
                            </p>
                        </div>
                        <div class="shrink-0">
                            <svg class="size-5 text-neutral-400 transition-colors group-hover:text-neutral-600 dark:text-neutral-500 dark:group-hover:text-neutral-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                            </svg>
                        </div>
                    </div>
                </a>
            @empty
                <div class="py-8 text-center">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aktuell werden keine Matches aufgezeichnet.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($totalCount > 5)
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


