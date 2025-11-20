<?php

use App\Models\DartMatch;
use App\Models\Player;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $playerId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->playerId = Auth::user()?->player?->id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'player' => $this->player(),
            'matches' => $this->playerId ? $this->matchesQuery()->paginate(10) : null,
        ];
    }

    protected function player(): ?Player
    {
        if (! $this->playerId) {
            return null;
        }

        return Player::with('user')->find($this->playerId);
    }

    protected function matchesQuery(): Builder
    {
        return DartMatch::query()
            ->with([
                'players' => fn ($query) => $query->orderBy('match_player.player_index'),
                'winner',
            ])
            ->whereHas('players', fn ($query) => $query->where('players.id', $this->playerId))
            ->when($this->statusFilter === 'finished', fn ($query) => $query->whereNotNull('finished_at'))
            ->when($this->statusFilter === 'ongoing', fn ($query) => $query->whereNull('finished_at'))
            ->when($this->search !== '', function ($query) {
                $searchTerm = '%' . $this->search . '%';

                $query->where(function ($inner) use ($searchTerm) {
                    $inner->where('autodarts_match_id', 'like', $searchTerm)
                        ->orWhere('variant', 'like', $searchTerm)
                        ->orWhereHas('players', function ($playerQuery) use ($searchTerm) {
                            $playerQuery->where('name', 'like', $searchTerm);
                        });
                });
            })
            ->orderByDesc('started_at')
            ->orderByDesc('id');
    }
}; ?>

<section class="w-full space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Meine Matches') }}</flux:heading>
            <flux:subheading>
                {{ $player ? __('Alle Spiele, an denen :name teilgenommen hat', ['name' => $player->name ?? __('dein Profil')]) : __('Verknüpfe zuerst dein Spielerprofil, um Matches zu sehen.') }}
            </flux:subheading>
        </div>
    </div>

    @if (! $player)
        <flux:callout variant="warning" icon="user">
            {{ __('Es ist kein Player mit deinem Benutzer verknüpft. Bitte wende dich an einen Admin, um dein Profil zu koppeln.') }}
        </flux:callout>
    @else
        <div class="grid gap-4 lg:grid-cols-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                class="lg:col-span-2"
                :placeholder="__('Nach Match-ID oder Gegner suchen...')"
            />

            <flux:select wire:model.live="statusFilter" :label="__('Status')" class="lg:col-span-1">
                <option value="all">{{ __('Alle') }}</option>
                <option value="ongoing">{{ __('Laufend') }}</option>
                <option value="finished">{{ __('Beendet') }}</option>
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">
                            {{ __('Match') }}
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
                    @forelse ($matches as $match)
                        <tr wire:key="player-match-{{ $match->id }}">
                            <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                <div class="flex flex-col">
                                    <span class="font-semibold">{{ $match->variant }} · {{ $match->type }}</span>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $match->started_at?->format('d.m.Y H:i') ?? __('Unbekannter Start') }}
                                    </span>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        @foreach ($match->players as $participant)
                                            <flux:badge
                                                size="sm"
                                                :variant="$participant->pivot->final_position === 1 ? 'primary' : 'subtle'"
                                            >
                                                {{ $participant->name ?? __('Player #:id', ['id' => $participant->id]) }}
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-zinc-900 dark:text-zinc-100">
                                @if ($match->finished_at)
                                    @if ($match->winner)
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ $match->winner->is($player) ? __('Gewonnen') : __('Verloren') }}
                                        </span>
                                    @else
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Beendet') }}</span>
                                    @endif
                                @else
                                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Noch offen') }}</span>
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
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keine Matches gefunden.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>
            {{ $matches->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</section>


