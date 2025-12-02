@props(['leg'])

@php
    // Gruppiere alle Turns nach Runden und bereite die Daten auf
    $turnsByRound = $leg->turns
        ->sortBy('round_number')
        ->groupBy('round_number');
    
    // Hole alle Spieler aus dem Match (sortiert nach player_index)
    $players = $leg->match->players->sortBy('pivot.player_index')->values();
    
    // Anzahl der Spieler für dynamische Spalten
    $playerCount = $players->count();
    
    // Hilfsfunktion zum Formatieren von Wurf-Labels
    $formatThrowLabel = function ($throw) {
        if ($throw->multiplier == 0 || $throw->segment_number == 0) {
            return 'Miss';
        } elseif ($throw->multiplier == 3) {
            return 'T' . $throw->segment_number;
        } elseif ($throw->multiplier == 2) {
            return 'D' . $throw->segment_number;
        } elseif ($throw->segment_number == 25 && $throw->multiplier == 2) {
            return 'Bull';
        } elseif ($throw->segment_number == 25 && $throw->multiplier == 1) {
            return '25';
        } else {
            return 'S' . $throw->segment_number;
        }
    };
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
                <div class="mx-auto max-w-7xl rounded-lg border-2 border-slate-500/50 bg-slate-800 p-8 shadow-2xl dark:bg-slate-900">
                    <!-- Spieler-Header -->
                    <div class="mb-6 grid gap-8 border-b-2 border-slate-500/50 pb-4" style="grid-template-columns: repeat({{ $playerCount }}, minmax(0, 1fr));">
                        @foreach ($players as $player)
                            <div class="text-center">
                                <div class="text-base font-bold tracking-wide text-slate-200">
                                    @if ($player->user)
                                        <a href="{{ route('users.show', $player->user) }}" target="_blank" class="text-blue-400 hover:text-blue-300">
                                            {{ $player->name }}
                                        </a>
                                    @else
                                        {{ $player->name }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Runden -->
                    <div class="space-y-0">
                        @foreach ($turnsByRound as $roundNumber => $turns)
                            <div class="grid gap-8 border-b border-slate-600/30 py-4" style="grid-template-columns: repeat({{ $playerCount }}, minmax(0, 1fr));">
                                <!-- Turns für jeden Spieler -->
                                @foreach ($players as $player)
                                    @php
                                        $turn = $turns->firstWhere('player_id', $player->id);
                                    @endphp
                                    
                                    <div class="text-center">
                                        @if ($turn)
                                            <!-- Punkte mit diagonaler Durchstreichung bei BUST -->
                                            <div class="relative inline-block">
                                                @if ($turn->busted)
                                                    <!-- BUST Anzeige -->
                                                    <div class="font-mono text-4xl font-bold tracking-tight text-red-500 dark:text-red-400">
                                                        BUST
                                                    </div>
                                                    <div class="mt-1 text-xs font-semibold text-slate-400">
                                                        {{ $turn->points }} Punkte
                                                    </div>
                                                @else
                                                    <!-- Normale Punkte-Anzeige -->
                                                    <div class="font-mono text-5xl font-bold tracking-tight text-slate-100">
                                                        {{ $turn->points }}
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- Einzelne Dart-Würfe (klein und dezent) -->
                                            @php
                                                // Alle Würfe anzeigen (auch korrigierte)
                                                $allThrows = $turn->throws;
                                                
                                                // Für jeden dart_number: finde den aktuellen Wurf (nicht korrigiert) und prüfe auf korrigierte Vorgänger
                                                $displayThrows = $allThrows
                                                    ->groupBy('dart_number')
                                                    ->map(function ($throws) {
                                                        // Finde den aktuellen Wurf (nicht korrigiert, neuester)
                                                        $currentThrow = $throws
                                                            ->where('is_corrected', false)
                                                            ->sortByDesc('id')
                                                            ->first();
                                                        
                                                        // Falls kein nicht-korrigierter Wurf existiert, nimm den neuesten (auch wenn korrigiert)
                                                        if (!$currentThrow) {
                                                            $currentThrow = $throws->sortByDesc('id')->first();
                                                        }
                                                        
                                                        // Finde den neuesten korrigierten Wurf (falls vorhanden)
                                                        $correctedThrow = $throws
                                                            ->where('is_corrected', true)
                                                            ->where('id', '!=', $currentThrow->id)
                                                            ->sortByDesc('id')
                                                            ->first();
                                                        
                                                        return [
                                                            'throw' => $currentThrow,
                                                            'has_correction' => $correctedThrow !== null,
                                                            'corrected_throw' => $correctedThrow,
                                                        ];
                                                    })
                                                    ->sortBy(function ($item) {
                                                        return $item['throw']->dart_number;
                                                    })
                                                    ->values();
                                            @endphp
                                            @if ($displayThrows->isNotEmpty())
                                                <div class="mt-1 flex flex-wrap justify-center gap-1 text-xs text-slate-400">
                                                    @foreach ($displayThrows as $item)
                                                        @php
                                                            $throw = $item['throw'];
                                                            $hasCorrection = $item['has_correction'];
                                                            $correctedThrow = $item['corrected_throw'];
                                                            
                                                            $throwLabel = $formatThrowLabel($throw);
                                                            $correctedLabel = $hasCorrection && $correctedThrow ? $formatThrowLabel($correctedThrow) : '';
                                                        @endphp
                                                        
                                                        <span class="inline-flex items-center gap-1">
                                                            @if ($hasCorrection && $correctedThrow)
                                                                <!-- Zeige zuerst den korrigierten Wurf (durchgestrichen) -->
                                                                <span class="font-mono line-through text-slate-500 dark:text-slate-500" title="{{ __('Korrigiert: :label', ['label' => $correctedLabel]) }}">
                                                                    {{ $correctedLabel }}
                                                                </span>
                                                                <!-- Dann den Pfeil -->
                                                                <span class="text-slate-500">→</span>
                                                            @endif
                                                            <!-- Der aktuelle/korrigierte Wurf -->
                                                            <span class="font-mono {{ $hasCorrection ? 'text-amber-400 dark:text-amber-300' : '' }}" title="{{ $hasCorrection ? __('Korrigiert') : '' }}">
                                                                {{ $throwLabel }}
                                                            </span>
                                                        </span>
                                                        @if (!$loop->last)
                                                            <span class="mx-1">·</span>
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

