<?php

use App\Models\MatchdayFixture;
use App\Services\SettingsService;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $limit = SettingsService::getUpcomingMatchesCount();
        
        $fixtures = MatchdayFixture::query()
            ->whereNull('dart_match_id')
            ->whereNull('played_at')
            ->where('status', 'scheduled')
            ->with([
                'homePlayer.user',
                'awayPlayer.user',
                'matchday.season.league',
            ])
            ->join('matchdays', 'matchday_fixtures.matchday_id', '=', 'matchdays.id')
            ->orderBy('matchdays.deadline_at', 'asc')
            ->orderBy('matchday_fixtures.id', 'asc')
            ->select('matchday_fixtures.*')
            ->limit($limit)
            ->get();

        $totalCount = MatchdayFixture::query()
            ->whereNull('dart_match_id')
            ->whereNull('played_at')
            ->where('status', 'scheduled')
            ->count();

        return [
            'fixtures' => $fixtures,
            'totalCount' => $totalCount,
            'limit' => $limit,
        ];
    }
}; ?>

<div class="relative h-full overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
    <div class="flex h-full flex-col">
        <div class="border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-700 dark:bg-zinc-800">
            <h3 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Upcoming Matches') }}</h3>
        </div>

        <div class="flex-1 overflow-y-auto p-6">
            @forelse ($fixtures as $fixture)
                <div
                    class="group mb-4 block rounded-lg border border-neutral-200 bg-neutral-50 p-4 transition-colors hover:border-neutral-300 hover:bg-neutral-100 dark:border-neutral-700 dark:bg-zinc-800 dark:hover:border-neutral-600 dark:hover:bg-zinc-700"
                    wire:key="upcoming-fixture-{{ $fixture->id }}"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            @if ($fixture->matchday)
                                <div class="flex items-center gap-1 mb-2">
                                    <a href="{{ route('leagues.show', $fixture->matchday->season->league) }}" wire:navigate onclick="event.stopPropagation()">
                                        <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                            {{ $fixture->matchday->season->league->slug }}
                                        </flux:badge>
                                    </a>
                                    <a href="{{ route('seasons.show', $fixture->matchday->season) }}" wire:navigate onclick="event.stopPropagation()">
                                        <flux:badge size="xs" variant="subtle" class="hover:bg-neutral-200 dark:hover:bg-neutral-700 transition-colors cursor-pointer">
                                            {{ __('Spieltag :number', ['number' => $fixture->matchday->matchday_number]) }}
                                        </flux:badge>
                                    </a>
                                </div>
                            @endif

                            {{-- Spieler --}}
                            <div class="mt-2 space-y-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm text-neutral-700 dark:text-neutral-300">
                                        @if ($fixture->homePlayer?->user)
                                            <a href="{{ route('users.show', $fixture->homePlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $fixture->homePlayer->name }}
                                            </a>
                                        @else
                                            {{ $fixture->homePlayer->name ?? __('Player #:id', ['id' => $fixture->home_player_id]) }}
                                        @endif
                                        vs
                                        @if ($fixture->awayPlayer?->user)
                                            <a href="{{ route('users.show', $fixture->awayPlayer->user) }}" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                {{ $fixture->awayPlayer->name }}
                                            </a>
                                        @else
                                            {{ $fixture->awayPlayer->name ?? __('Player #:id', ['id' => $fixture->away_player_id]) }}
                                        @endif
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
            @empty
                <div class="py-8 text-center">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ __('Keine anstehenden Matches vorhanden.') }}</p>
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
