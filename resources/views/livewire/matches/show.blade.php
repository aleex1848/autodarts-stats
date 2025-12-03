<?php

use App\Models\DartMatch;
use App\Models\DartThrow;
use App\Services\MatchReprocessingService;
use Illuminate\Contracts\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Volt\Component;

new class extends Component
{
    use AuthorizesRequests;

    public DartMatch $match;

    public Collection $legs;

    public ?int $playerId = null;

    public int $targetNumber = 20;

    public array $segmentOptions = [];

    public array $targetSegments = [];

    public array $segmentCounts = [];

    public array $tripleCounts = [];

    public int $totalTargetThrows = 0;

    public int $maxSegmentCount = 0;

    public ?string $sortBy = null;

    public string $sortDirection = 'asc';

    protected array $boardOrder = [20, 1, 18, 4, 13, 6, 10, 15, 2, 17, 3, 19, 7, 16, 8, 11, 14, 9, 12, 5];

    public function getListeners(): array
    {
        return [
            "echo:match.{$this->match->id},.match.updated" => 'refreshMatch',
        ];
    }

    public function mount(DartMatch $match): void
    {
        $this->authorize('view', $match);

        $this->match = $match->load([
            'players' => fn ($query) => $query->orderBy('match_player.player_index'),
            'players.user',
            'winner',
            'bullOffs.player.user',
            'fixture.matchday.season.league',
            'fixture.homePlayer.user',
            'fixture.awayPlayer.user',
            'fixture.winner',
        ]);
        
        // Ensure season is loaded for comparison
        if ($this->match->fixture?->matchday?->season) {
            $this->match->fixture->matchday->load('season');
        }

        $this->legs = $match->legs()
            ->with([
                'winner',
                'legPlayers',
                'turns' => fn ($query) => $query->orderBy('round_number'),
                'turns.player',
                'turns.throws' => fn ($query) => $query->orderBy('dart_number'),
            ])
            ->orderBy('set_number')
            ->orderBy('leg_number')
            ->get();

        $user = Auth::user();
        $this->playerId = $user?->player?->id
            ?? optional($this->match->players->firstWhere('user_id', $user?->id))->id;

        $this->segmentOptions = range(1, 20);

        $this->loadTargetAnalysis();
    }

    public function reprocessMatch(): void
    {
        $this->authorize('view', $this->match);

        if (! Auth::user()->hasAnyRole(['Super-Admin', 'Admin'])) {
            abort(403);
        }

        try {
            $service = app(MatchReprocessingService::class);
            $service->reprocessMatch($this->match);

            // Reload match and legs after reprocessing
            $this->match->refresh();
            $this->match->load([
                'players' => fn ($query) => $query->orderBy('match_player.player_index'),
                'players.user',
                'winner',
                'bullOffs.player.user',
                'fixture.matchday.season.league',
                'fixture.homePlayer.user',
                'fixture.awayPlayer.user',
                'fixture.winner',
            ]);

            $this->legs = $this->match->legs()
                ->with([
                    'winner',
                    'legPlayers',
                    'turns' => fn ($query) => $query->orderBy('round_number'),
                    'turns.player',
                    'turns.throws' => fn ($query) => $query->orderBy('dart_number'),
                ])
                ->orderBy('set_number')
                ->orderBy('leg_number')
                ->get();

            $user = Auth::user();
            $this->playerId = $user?->player?->id
                ?? optional($this->match->players->firstWhere('user_id', $user?->id))->id;

            $this->loadTargetAnalysis();

            Session::flash('success', __('Match wurde erfolgreich erneut verarbeitet.'));
        } catch (\Exception $e) {
            Session::flash('error', __('Fehler beim erneuten Verarbeiten des Matches: :message', ['message' => $e->getMessage()]));
        }
    }

    public function refreshMatch(): void
    {
        $this->match->refresh();
        $this->match->load([
            'players' => fn ($query) => $query->orderBy('match_player.player_index'),
            'players.user',
            'winner',
            'bullOffs.player.user',
            'fixture.matchday.season.league',
            'fixture.homePlayer.user',
            'fixture.awayPlayer.user',
            'fixture.winner',
        ]);

        $this->legs = $this->match->legs()
            ->with([
                'winner',
                'legPlayers',
                'turns' => fn ($query) => $query->orderBy('round_number'),
                'turns.player',
                'turns.throws' => fn ($query) => $query->orderBy('dart_number'),
            ])
            ->orderBy('set_number')
            ->orderBy('leg_number')
            ->get();

        $user = Auth::user();
        $this->playerId = $user?->player?->id
            ?? optional($this->match->players->firstWhere('user_id', $user?->id))->id;

        $this->loadTargetAnalysis();
    }

    public function updatedTargetNumber(): void
    {
        $this->loadTargetAnalysis();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function getSortedLegRows(): Collection
    {
        $rows = collect();

        foreach ($this->legs as $leg) {
            foreach ($leg->legPlayers as $player) {
                $rows->push([
                    'leg' => $leg,
                    'player' => $player,
                    'set_number' => $leg->set_number,
                    'leg_number' => $leg->leg_number,
                    'player_name' => $player->name,
                    'winner' => $leg->winner && $leg->winner->id === $player->id,
                    'average' => $player->pivot->average ?? null,
                    'average_until_170' => $player->pivot->average_until_170 ?? null,
                    'first_9_average' => $player->pivot->first_9_average ?? null,
                    'best_checkout_points' => $player->pivot->best_checkout_points ?? null,
                    'darts_thrown' => $player->pivot->darts_thrown ?? null,
                    'checkout_rate' => $player->pivot->checkout_rate ? ($player->pivot->checkout_rate * 100) : null,
                    'busted_count' => $player->pivot->busted_count ?? 0,
                    'mpr' => $player->pivot->mpr ?? null,
                    'first_9_mpr' => $player->pivot->first_9_mpr ?? null,
                ]);
            }
        }

        if ($this->sortBy) {
            $rows = $rows->sortBy(function ($row) {
                return match ($this->sortBy) {
                    'set' => $row['set_number'],
                    'leg' => $row['leg_number'],
                    'player' => $row['player_name'],
                    'winner' => $row['winner'] ? 1 : 0,
                    'average' => $row['average'] ?? 0,
                    'average_until_170' => $row['average_until_170'] ?? 0,
                    'first_9_average' => $row['first_9_average'] ?? 0,
                    'best_checkout' => $row['best_checkout_points'] ?? 0,
                    'darts_thrown' => $row['darts_thrown'] ?? 0,
                    'checkout_rate' => $row['checkout_rate'] ?? 0,
                    'busted' => $row['busted_count'] ?? 0,
                    'mpr' => $row['mpr'] ?? 0,
                    'first_9_mpr' => $row['first_9_mpr'] ?? 0,
                    default => 0,
                };
            }, SORT_REGULAR, $this->sortDirection === 'desc');
        }

        return $rows->values();
    }

    public function with(): array
    {
        return [
            'match' => $this->match,
            'legs' => $this->legs,
            'targetSegments' => $this->targetSegments,
            'segmentCounts' => $this->segmentCounts,
            'tripleCounts' => $this->tripleCounts,
            'targetNumber' => $this->targetNumber,
            'segmentOptions' => $this->segmentOptions,
            'maxSegmentCount' => $this->maxSegmentCount,
            'totalTargetThrows' => $this->totalTargetThrows,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'sortedLegRows' => $this->getSortedLegRows(),
        ];
    }

    protected function loadTargetAnalysis(): void
    {
        $this->targetSegments = $this->resolveTargetSegments();
        $this->segmentCounts = collect($this->targetSegments)->mapWithKeys(fn ($segment) => [$segment => 0])->toArray();
        $this->tripleCounts = collect($this->targetSegments)->mapWithKeys(fn ($segment) => [$segment => 0])->toArray();
        $this->totalTargetThrows = 0;
        $this->maxSegmentCount = 0;

        if (! $this->playerId || empty($this->targetSegments)) {
            return;
        }

        $baseScore = $this->match->base_score ?? 501;
        $targetNumber = $this->targetNumber;
        $isX01 = $this->match->variant === 'X01';

        // For X01 games: Count all throws on target segments, but only if the score before the throw was >= targetNumber
        // For Cricket and other variants: Count all throws on target segments (no score filtering)
        $countsQuery = DartThrow::query()
            ->selectRaw('throws.segment_number, COUNT(*) as total')
            ->join('turns', 'throws.turn_id', '=', 'turns.id')
            ->join('legs', 'turns.leg_id', '=', 'legs.id')
            ->whereIn('throws.segment_number', $this->targetSegments)
            ->whereNotNull('throws.segment_number')
            ->where('throws.is_corrected', false)
            ->where('turns.player_id', $this->playerId)
            ->where('legs.match_id', $this->match->id);

        if ($isX01) {
            // Only filter by score for X01 games
            $countsQuery->where(function (QueryBuilder $scoreQuery) use ($targetNumber, $baseScore): void {
                // Include throws where score_after >= targetNumber (score was high enough before the turn)
                // OR score_after is null (ongoing turn) and base_score >= targetNumber
                $scoreQuery->where(function (QueryBuilder $subQuery) use ($targetNumber): void {
                    $subQuery->whereNotNull('turns.score_after')
                        ->whereRaw('turns.score_after >= ?', [$targetNumber]);
                })
                    ->orWhere(function (QueryBuilder $subQuery) use ($baseScore, $targetNumber): void {
                        $subQuery->whereNull('turns.score_after')
                            ->whereRaw('? >= ?', [$baseScore, $targetNumber]);
                    });
            });
        }

        $counts = $countsQuery
            ->groupBy('throws.segment_number')
            ->pluck('total', 'segment_number')
            ->toArray();

        foreach ($counts as $segment => $total) {
            if (array_key_exists($segment, $this->segmentCounts)) {
                $this->segmentCounts[$segment] = (int) $total;
            }
        }

        // Count triple hits on all target segments
        $tripleCountsQuery = DartThrow::query()
            ->selectRaw('throws.segment_number, COUNT(*) as total')
            ->join('turns', 'throws.turn_id', '=', 'turns.id')
            ->join('legs', 'turns.leg_id', '=', 'legs.id')
            ->whereIn('throws.segment_number', $this->targetSegments)
            ->where('throws.multiplier', 3)
            ->whereNotNull('throws.segment_number')
            ->where('throws.is_corrected', false)
            ->where('turns.player_id', $this->playerId)
            ->where('legs.match_id', $this->match->id);

        if ($isX01) {
            // Only filter by score for X01 games
            $tripleCountsQuery->where(function (QueryBuilder $scoreQuery) use ($targetNumber, $baseScore): void {
                $scoreQuery->where(function (QueryBuilder $subQuery) use ($targetNumber): void {
                    $subQuery->whereNotNull('turns.score_after')
                        ->whereRaw('turns.score_after >= ?', [$targetNumber]);
                })
                    ->orWhere(function (QueryBuilder $subQuery) use ($baseScore, $targetNumber): void {
                        $subQuery->whereNull('turns.score_after')
                            ->whereRaw('? >= ?', [$baseScore, $targetNumber]);
                    });
            });
        }

        $tripleCounts = $tripleCountsQuery
            ->groupBy('throws.segment_number')
            ->pluck('total', 'segment_number')
            ->toArray();

        foreach ($tripleCounts as $segment => $total) {
            if (array_key_exists($segment, $this->tripleCounts)) {
                $this->tripleCounts[$segment] = (int) $total;
            }
        }

        $this->totalTargetThrows = array_sum($this->segmentCounts);
        $this->maxSegmentCount = (int) collect($this->segmentCounts)->max() ?: 0;
    }

    protected function resolveTargetSegments(): array
    {
        $index = array_search($this->targetNumber, $this->boardOrder, true);

        if ($index === false) {
            return [];
        }

        $total = count($this->boardOrder);
        $leftOne = $this->boardOrder[($index - 1 + $total) % $total];
        $leftTwo = $this->boardOrder[($index - 2 + $total) % $total];
        $rightOne = $this->boardOrder[($index + 1) % $total];
        $rightTwo = $this->boardOrder[($index + 2) % $total];

        return [$leftTwo, $leftOne, $this->targetNumber, $rightOne, $rightTwo];
    }
}; ?>

<div class="space-y-10">
    @include('livewire.matches.partials.details', [
        'title' => __('Matchdetails'),
        'subtitle' => __('Alle verfügbaren Statistiken zu diesem Match'),
    ])

    @if (session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/50 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/50 dark:text-red-200">
            {{ session('error') }}
        </div>
    @endif

    @if ($match->is_incomplete)
        <flux:callout variant="warning" icon="exclamation-triangle">
            {{ __('Dieses Match wurde als unvollständig markiert. Das Spiel wurde möglicherweise nicht vollständig aufgezeichnet oder abgebrochen.') }}
        </flux:callout>
    @endif

    @hasanyrole('Super-Admin|Admin')
        <div class="flex justify-end gap-2">
            <flux:button
                variant="outline"
                icon="arrow-down-tray"
                :href="route('matches.export', $match)"
            >
                {{ __('Match exportieren') }}
            </flux:button>
            <flux:button
                variant="danger"
                icon="arrow-path"
                wire:click="reprocessMatch"
                wire:confirm="{{ __('Sind Sie sicher, dass Sie dieses Match erneut verarbeiten möchten? Alle verarbeiteten Daten werden gelöscht und die Rohdaten werden erneut verarbeitet. Diese Aktion kann nicht rückgängig gemacht werden.') }}"
            >
                {{ __('Match erneut verarbeiten') }}
            </flux:button>
        </div>
    @endhasanyrole

@if ($match->variant === 'X01')
    <section class="w-full space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Zielfeld-Analyse') }}</flux:heading>
                <flux:subheading>
                    {{ __('Vergleiche deine Trefferhäufigkeit auf dem gewählten Feld und den beiden Nachbarn links sowie rechts.') }}
                </flux:subheading>
            </div>

            <div class="w-full max-w-xs">
                <flux:select
                    wire:model.live="targetNumber"
                    :label="__('Zielfeld wählen')"
                >
                    @foreach ($segmentOptions as $option)
                        <option value="{{ $option }}">{{ __('Feld :value', ['value' => $option]) }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        @if ($targetSegments)
            @if ($totalTargetThrows === 0)
                <flux:callout variant="neutral" icon="chart-bar">
                    {{ __('Für dieses Zielfeld liegen noch keine verwertbaren Würfe vor.') }}
                </flux:callout>
            @else
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="md">{{ __('Treffer auf angrenzende Felder') }}</flux:heading>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left">{{ __('Feld') }}</th>
                                        <th class="px-4 py-3 text-right">{{ __('Anzahl') }}</th>
                                        @if (collect($tripleCounts)->sum() > 0)
                                            <th class="px-4 py-3 text-right">{{ __('Triple') }}</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                    @foreach ($targetSegments as $segment)
                                        <tr>
                                            <td class="px-4 py-3">
                                                <span class="{{ $segment === $targetNumber ? 'font-semibold text-emerald-600 dark:text-emerald-400' : 'text-zinc-900 dark:text-zinc-100' }}">
                                                    {{ $segment === $targetNumber ? __('Ziel :segment', ['segment' => $segment]) : __('Feld :segment', ['segment' => $segment]) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $segmentCounts[$segment] ?? 0 }}
                                            </td>
                                            @if (collect($tripleCounts)->sum() > 0)
                                                <td class="px-4 py-3 text-right text-zinc-600 dark:text-zinc-400">
                                                    {{ $tripleCounts[$segment] ?? 0 }}
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="px-4 py-3 text-right font-semibold text-zinc-600 dark:text-zinc-300">
                                            {{ __('Gesamt') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $totalTargetThrows }}
                                        </td>
                                        @if (collect($tripleCounts)->sum() > 0)
                                            <td class="px-4 py-3 text-right font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ collect($tripleCounts)->sum() }}
                                            </td>
                                        @endif
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <flux:heading size="md">{{ __('Visualisierung') }}</flux:heading>
                        <flux:subheading class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Verteilung der Treffer relativ zum stärksten Feld (:max Treffer).', ['max' => $maxSegmentCount]) }}
                        </flux:subheading>

                        <div class="space-y-4">
                            @foreach ($targetSegments as $segment)
                                @php
                                    $count = $segmentCounts[$segment] ?? 0;
                                    $percentage = $maxSegmentCount > 0 ? ($count / $maxSegmentCount) * 100 : 0;
                                    $barWidth = $count > 0 ? max($percentage, 8) : 0;
                                @endphp
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="{{ $segment === $targetNumber ? 'font-semibold text-emerald-600 dark:text-emerald-400' : 'text-zinc-600 dark:text-zinc-300' }}">
                                            {{ $segment === $targetNumber ? __('Ziel :segment', ['segment' => $segment]) : __('Feld :segment', ['segment' => $segment]) }}
                                        </span>
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ trans_choice(':count Treffer|:count Treffer', $count, ['count' => $count]) }}
                                        </span>
                                    </div>
                                    <div class="h-3 rounded-full bg-zinc-200 dark:bg-zinc-800">
                                        <div
                                            class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                                            style="width: {{ $barWidth }}%;"
                                        ></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </section>
@endif
</div>
