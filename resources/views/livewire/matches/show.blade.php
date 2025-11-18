<?php

use App\Models\DartMatch;
use App\Models\DartThrow;
use Illuminate\Contracts\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

    protected array $boardOrder = [20, 1, 18, 4, 13, 6, 10, 15, 2, 17, 3, 19, 7, 16, 8, 11, 14, 9, 12, 5];

    public function mount(DartMatch $match): void
    {
        $this->authorize('view', $match);

        $this->match = $match->load([
            'players' => fn ($query) => $query->orderBy('match_player.player_index'),
            'winner',
        ]);

        $this->legs = $match->legs()
            ->with(['winner', 'legPlayers'])
            ->orderBy('set_number')
            ->orderBy('leg_number')
            ->get();

        $user = Auth::user();
        $this->playerId = $user?->player?->id
            ?? optional($this->match->players->firstWhere('user_id', $user?->id))->id;

        $this->segmentOptions = range(1, 20);

        $this->loadTargetAnalysis();
    }

    public function updatedTargetNumber(): void
    {
        $this->loadTargetAnalysis();
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

        // Count all throws on target segments, but only if the score before the throw was >= targetNumber
        // score_after is the score AFTER the turn, so if score_after >= targetNumber,
        // the score before the turn (score_after + points) was also >= targetNumber
        $counts = DartThrow::query()
            ->selectRaw('throws.segment_number, COUNT(*) as total')
            ->join('turns', 'throws.turn_id', '=', 'turns.id')
            ->join('legs', 'turns.leg_id', '=', 'legs.id')
            ->whereIn('throws.segment_number', $this->targetSegments)
            ->whereNotNull('throws.segment_number')
            ->where('throws.is_corrected', false)
            ->where('turns.player_id', $this->playerId)
            ->where('legs.match_id', $this->match->id)
            ->where(function (QueryBuilder $scoreQuery) use ($targetNumber, $baseScore): void {
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
            })
            ->groupBy('throws.segment_number')
            ->pluck('total', 'segment_number')
            ->toArray();

        foreach ($counts as $segment => $total) {
            if (array_key_exists($segment, $this->segmentCounts)) {
                $this->segmentCounts[$segment] = (int) $total;
            }
        }

        // Count triple hits on all target segments
        $tripleCounts = DartThrow::query()
            ->selectRaw('throws.segment_number, COUNT(*) as total')
            ->join('turns', 'throws.turn_id', '=', 'turns.id')
            ->join('legs', 'turns.leg_id', '=', 'legs.id')
            ->whereIn('throws.segment_number', $this->targetSegments)
            ->where('throws.multiplier', 3)
            ->whereNotNull('throws.segment_number')
            ->where('throws.is_corrected', false)
            ->where('turns.player_id', $this->playerId)
            ->where('legs.match_id', $this->match->id)
            ->where(function (QueryBuilder $scoreQuery) use ($targetNumber, $baseScore): void {
                $scoreQuery->where(function (QueryBuilder $subQuery) use ($targetNumber): void {
                    $subQuery->whereNotNull('turns.score_after')
                        ->whereRaw('turns.score_after >= ?', [$targetNumber]);
                })
                    ->orWhere(function (QueryBuilder $subQuery) use ($baseScore, $targetNumber): void {
                        $subQuery->whereNull('turns.score_after')
                            ->whereRaw('? >= ?', [$baseScore, $targetNumber]);
                    });
            })
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
        'backUrl' => route('matches.index'),
        'backLabel' => __('Zurück zu meinen Matches'),
    ])

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
</div>

