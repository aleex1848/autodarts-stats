@props(['leg'])

@php
    // Gruppiere alle Turns nach Runden und bereite die Daten auf
    $turnsByRound = $leg->turns
        ->sortBy('round_number')
        ->groupBy('round_number');
    
    // Hole alle Spieler aus dem Match (sortiert nach player_index)
    $players = $leg->match->players->sortBy('pivot.player_index')->values();
@endphp

<div 
    x-data="{ open: false }" 
    class="mt-6 rounded-lg border border-slate-600/50 bg-slate-800/30 dark:border-slate-600/50 dark:bg-slate-900/30"
>
    <!-- Accordion Header -->
    <button
        @click="open = !open"
        type="button"
        class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-slate-700/30 dark:hover:bg-slate-800/30"
    >
        <div class="flex items-center gap-2">
            <flux:icon.chart-bar class="size-4 text-slate-300" />
            <span class="font-medium text-slate-100">
                {{ __('Kreidetafel-Ansicht') }}
            </span>
        </div>
        <flux:icon.chevron-down 
            x-bind:class="open ? 'rotate-180' : ''" 
            class="size-4 text-slate-300 transition-transform duration-200"
        />
    </button>

    <!-- Accordion Content -->
    <div 
        x-show="open" 
        x-collapse
        class="border-t border-slate-600/50 dark:border-slate-600/50"
    >
        <div class="bg-gradient-to-br from-slate-700 to-slate-800 p-6 dark:from-slate-800 dark:to-slate-900">
            @if ($turnsByRound->isNotEmpty())
                <!-- Kreidetafel -->
                <div class="mx-auto max-w-2xl rounded-lg border-2 border-slate-500/50 bg-slate-800 p-8 shadow-2xl dark:bg-slate-900">
                    <!-- Spieler-Header -->
                    <div class="mb-6 grid grid-cols-2 gap-8 border-b-2 border-slate-500/50 pb-4">
                        @foreach ($players as $player)
                            <div class="text-center">
                                <div class="text-base font-bold tracking-wide text-slate-200">
                                    {{ $player->name }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Runden -->
                    <div class="space-y-0">
                        @foreach ($turnsByRound as $roundNumber => $turns)
                            <div class="grid grid-cols-2 gap-8 border-b border-slate-600/30 py-4">
                                <!-- Turns für jeden Spieler -->
                                @foreach ($players as $player)
                                    @php
                                        $turn = $turns->firstWhere('player_id', $player->id);
                                    @endphp
                                    
                                    <div class="text-center">
                                        @if ($turn)
                                            <!-- Punkte mit diagonaler Durchstreichung bei BUST -->
                                            <div class="relative inline-block">
                                                <div class="font-mono text-5xl font-bold tracking-tight {{ $turn->busted ? 'text-slate-400' : 'text-slate-100' }}">
                                                    {{ $turn->points }}
                                                </div>
                                                @if ($turn->busted)
                                                    <!-- Diagonale Durchstreichung -->
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <div class="h-0.5 w-full origin-center rotate-[25deg] bg-slate-400"></div>
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Einzelne Dart-Würfe (klein und dezent) -->
                                            @if ($turn->throws->isNotEmpty())
                                                <div class="mt-1 flex flex-wrap justify-center gap-1 text-xs text-slate-400">
                                                    @foreach ($turn->throws->sortBy('dart_number') as $throw)
                                                        @php
                                                            $throwLabel = '';
                                                            
                                                            // Formatiere den Wurf (z.B. T20, D16, S5)
                                                            if ($throw->multiplier == 3) {
                                                                $throwLabel = 'T' . $throw->segment_number;
                                                            } elseif ($throw->multiplier == 2) {
                                                                $throwLabel = 'D' . $throw->segment_number;
                                                            } elseif ($throw->segment_number == 25 && $throw->multiplier == 2) {
                                                                $throwLabel = 'Bull';
                                                            } elseif ($throw->segment_number == 25 && $throw->multiplier == 1) {
                                                                $throwLabel = '25';
                                                            } elseif ($throw->segment_number == 0) {
                                                                $throwLabel = 'Miss';
                                                            } else {
                                                                $throwLabel = 'S' . $throw->segment_number;
                                                            }
                                                        @endphp
                                                        
                                                        <span class="font-mono">{{ $throwLabel }}</span>
                                                        @if (!$loop->last)
                                                            <span>·</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            <!-- Verbleibender Score (klein und dezent) -->
                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ $turn->score_after ?? '—' }}
                                            </div>
                                        @else
                                            <!-- Kein Turn für diesen Spieler in dieser Runde -->
                                            <div class="font-mono text-5xl font-bold text-slate-600">
                                                —
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="py-8 text-center">
                    <p class="text-sm text-slate-400">
                        {{ __('Keine Würfe für dieses Leg aufgezeichnet.') }}
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>

