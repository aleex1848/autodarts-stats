@php
    $inverted = $inverted ?? false;
    
    $chartData = collect($data)->map(function ($item) use ($key) {
        $value = $item[$key] ?? null;
        return [
            'matchday_number' => $item['matchday_number'],
            'matchday' => $item['matchday_number'] . ($item['is_return_round'] ?? false ? ' (R)' : ''),
            'value' => $value,
        ];
    })->filter(function ($item) {
        return $item['value'] !== null && $item['value'] !== '';
    })->sortBy('matchday_number')->values();

    $values = $chartData->pluck('value')->filter();
    $minValue = $values->min();
    $maxValue = $values->max();
    
    // Für Win/Loss Ratio (percentage): Y-Achse sollte immer von 0% bis 100% gehen
    if ($format === 'percentage') {
        $minValue = 0;
        $maxValue = 1; // 100%
    }
    
    // Für Position: Stelle sicher, dass minValue mindestens 1 ist und es einen sinnvollen Bereich gibt
    if ($inverted && $format === 'integer' && $minValue !== null && $maxValue !== null) {
        // Runde minValue ab und maxValue auf, um schöne Zahlen zu bekommen
        $actualMin = floor($values->min());
        $actualMax = ceil($values->max());
        
        // Stelle sicher, dass minValue mindestens 1 ist
        $minValue = max(1, $actualMin);
        // Stelle sicher, dass maxValue mindestens minValue + 1 ist
        $maxValue = max($minValue + 1, $actualMax);
        
        // Füge einen kleinen Puffer hinzu, damit die Werte nicht am Rand sind
        // Erweitere den Bereich um 0.5 nach oben und unten
        if ($minValue > 1) {
            $minValue = max(1, $minValue - 0.5);
        }
        $maxValue = $maxValue + 0.5;
        
        // Runde wieder auf ganze Zahlen für die Anzeige
        $minValue = floor($minValue);
        $maxValue = ceil($maxValue);
    }
    
    $range = $maxValue - $minValue;
    $range = $range > 0 ? $range : 1;

    $width = 800;
    $height = 200;
    $padding = 40;
    $chartWidth = $width - ($padding * 2);
    $chartHeight = $height - ($padding * 2);
@endphp

<div class="w-full overflow-x-auto text-neutral-500 dark:text-neutral-100">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" preserveAspectRatio="none">
        {{-- Grid Lines --}}
        @php
            // Berechne alle Y-Achsen-Werte vorher, um Duplikate zu vermeiden
            $yAxisValues = [];
            $lastValue = null;
            for ($i = 0; $i <= 4; $i++) {
                if ($inverted) {
                    // Für Position: invertiert (kleinere Positionen oben, größere unten)
                    // Y-Achse: oben (i=0) = minValue (beste Position, z.B. 1), unten (i=4) = maxValue (schlechteste Position, z.B. 9)
                    $value = $minValue + (($maxValue - $minValue) / 4) * $i;
                } else {
                    // Für Average/Ratio: normal (höhere Werte oben)
                    // Y-Achse: oben (i=0) = maxValue, unten (i=4) = minValue
                    $value = $maxValue - (($maxValue - $minValue) / 4) * $i;
                }
                
                // Runde den Wert entsprechend dem Format
                if ($format === 'percentage') {
                    $roundedValue = round($value * 100, 0);
                } elseif ($format === 'decimal') {
                    $roundedValue = round($value, 1);
                } else {
                    $roundedValue = round($value, 0);
                }
                
                // Nur hinzufügen, wenn sich der gerundete Wert vom vorherigen unterscheidet
                if ($roundedValue !== $lastValue) {
                    $yAxisValues[] = [
                        'i' => $i,
                        'y' => $padding + ($chartHeight / 4) * $i,
                        'value' => $roundedValue,
                        'rawValue' => $value,
                    ];
                    $lastValue = $roundedValue;
                }
            }
        @endphp
        
        @foreach($yAxisValues as $axisData)
            <line
                x1="{{ $padding }}"
                y1="{{ $axisData['y'] }}"
                x2="{{ $width - $padding }}"
                y2="{{ $axisData['y'] }}"
                stroke="currentColor"
                stroke-width="0.5"
                opacity="0.2"
            />
            <text
                x="{{ $padding - 10 }}"
                y="{{ $axisData['y'] + 4 }}"
                text-anchor="end"
                class="text-xs"
                fill="currentColor"
                font-size="10"
            >
                @if($format === 'percentage')
                    {{ number_format($axisData['value'], 0) }}%
                @elseif($format === 'decimal')
                    {{ number_format($axisData['value'], 1) }}
                @else
                    {{ number_format($axisData['value'], 0) }}
                @endif
            </text>
        @endforeach

        {{-- Data Points and Line --}}
        @if($chartData->count() > 0)
            @php
                // Verwende die tatsächlichen Matchday-Nummern aus den Daten, nicht die der gesamten Saison
                $firstMatchday = $chartData->first()['matchday_number'] ?? 1;
                $lastMatchday = $chartData->last()['matchday_number'] ?? 1;
                $matchdayRange = max(1, $lastMatchday - $firstMatchday);
                
                $points = $chartData->map(function ($item, $index) use ($chartData, $minValue, $maxValue, $range, $padding, $chartWidth, $chartHeight, $inverted, $firstMatchday, $lastMatchday, $matchdayRange) {
                    // X-Position basierend auf Spieltag-Nummer (nicht Index)
                    // Wenn nur ein Datenpunkt: zentriert
                    if ($matchdayRange === 0) {
                        $x = $padding + ($chartWidth / 2);
                    } else {
                        $x = $padding + (($item['matchday_number'] - $firstMatchday) / $matchdayRange) * $chartWidth;
                    }
                    
                    if ($inverted ?? false) {
                        // Für Position: niedrigere Werte sind besser (oben)
                        $normalized = ($maxValue - $item['value']) / $range;
                    } else {
                        // Für Average/Ratio: höhere Werte sind besser (oben)
                        $normalized = ($item['value'] - $minValue) / $range;
                    }
                    
                    $y = $padding + $chartHeight - ($normalized * $chartHeight);
                    
                    return [
                        'x' => $x,
                        'y' => $y,
                        'value' => $item['value'],
                        'matchday' => $item['matchday'],
                        'matchday_number' => $item['matchday_number'],
                    ];
                });
            @endphp

            {{-- Line --}}
            <polyline
                points="{{ $points->map(fn($p) => "{$p['x']},{$p['y']}")->join(' ') }}"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                class="text-primary-600 dark:text-primary-400"
            />

            {{-- Data Points --}}
            @foreach($points as $point)
                <circle
                    cx="{{ $point['x'] }}"
                    cy="{{ $point['y'] }}"
                    r="4"
                    fill="currentColor"
                    class="text-primary-600 dark:text-primary-400"
                />
                <circle
                    cx="{{ $point['x'] }}"
                    cy="{{ $point['y'] }}"
                    r="8"
                    fill="currentColor"
                    opacity="0.2"
                    class="text-primary-600 dark:text-primary-400"
                />
                
                {{-- Tooltip on hover --}}
                <g class="opacity-0 hover:opacity-100 transition-opacity">
                    <rect
                        x="{{ $point['x'] - 30 }}"
                        y="{{ $point['y'] - 30 }}"
                        width="60"
                        height="20"
                        rx="4"
                        fill="currentColor"
                        class="text-neutral-900 dark:text-neutral-100"
                    />
                    <text
                        x="{{ $point['x'] }}"
                        y="{{ $point['y'] - 15 }}"
                        text-anchor="middle"
                        class="text-xs fill-white dark:fill-neutral-900"
                        font-size="10"
                    >
                        @if($format === 'percentage')
                            {{ number_format($point['value'] * 100, 1) }}%
                        @elseif($format === 'decimal')
                            {{ number_format($point['value'], 2) }}
                        @else
                            {{ number_format($point['value'], 0) }}
                        @endif
                    </text>
                </g>
            @endforeach

            {{-- X-Axis Labels --}}
            @foreach($points as $index => $point)
                @php
                    // Zeige alle Labels wenn weniger als 10, sonst jeden 2./3. Spieltag
                    $showEveryNth = $points->count() > 10 ? max(2, floor($points->count() / 8)) : 1;
                    $showLabel = $index % $showEveryNth === 0 || $index === $points->count() - 1;
                @endphp
                @if($showLabel)
                    <text
                        x="{{ $point['x'] }}"
                        y="{{ $height - $padding + 20 }}"
                        text-anchor="middle"
                        class="text-xs"
                        fill="currentColor"
                        font-size="10"
                    >
                        {{ $point['matchday_number'] }}
                    </text>
                @endif
            @endforeach
        @endif

        {{-- Y-Axis Line --}}
        <line
            x1="{{ $padding }}"
            y1="{{ $padding }}"
            x2="{{ $padding }}"
            y2="{{ $height - $padding }}"
            stroke="currentColor"
            stroke-width="1"
            opacity="0.3"
        />

        {{-- X-Axis Line --}}
        @if($chartData->count() > 0)
            @php
                $firstPoint = $points->first();
                $lastPoint = $points->last();
            @endphp
            <line
                x1="{{ $firstPoint['x'] ?? $padding }}"
                y1="{{ $height - $padding }}"
                x2="{{ $lastPoint['x'] ?? ($width - $padding) }}"
                y2="{{ $height - $padding }}"
                stroke="currentColor"
                stroke-width="1"
                opacity="0.3"
            />
        @else
            <line
                x1="{{ $padding }}"
                y1="{{ $height - $padding }}"
                x2="{{ $width - $padding }}"
                y2="{{ $height - $padding }}"
                stroke="currentColor"
                stroke-width="1"
                opacity="0.3"
            />
        @endif
    </svg>
</div>
